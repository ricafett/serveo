<?php

namespace App\Domain\Orders;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Domain\Printing\PrintQueueService;
use App\Models\BillingGroup;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\SeatPair;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    use ChecksPermissions;

    public function __construct(private readonly PrintQueueService $printQueue) {}

    /**
     * Create one OrderHeader plus items and immediately group lines by route into
     * ProductionTicket records, then queue print jobs.
     *
     * @param  array<int, array{menu_item_id:int, quantity:int, delivery_seat_pair_id?:int|null}>  $lines
     */
    public function submit(
        BillingGroup $group,
        User $actor,
        array $lines,
        ?OccupiedZone $zone = null,
        ?string $notes = null,
    ): OrderHeader {
        $this->ensureCan($actor, 'order.create');
        if (empty($lines)) {
            throw new RuntimeException('Cannot submit an empty order.');
        }
        if ($group->is_closed) {
            throw new RuntimeException('Cannot order on a closed billing group.');
        }
        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }
        if ($zone && $zone->billing_group_id !== $group->id) {
            throw new RuntimeException('Occupied zone does not belong to this billing group.');
        }

        return DB::transaction(function () use ($group, $actor, $lines, $zone, $notes) {
            $header = OrderHeader::create([
                'billing_group_id'    => $group->id,
                'occupied_zone_id'    => $zone?->id,
                'ordered_by_user_id'  => $actor->id,
                'ordered_at'          => now(),
                'submission_status'   => 'SUBMITTED',
                'notes'               => $notes,
            ]);

            $createdItems = [];
            foreach ($lines as $line) {
                /** @var MenuItem $menuItem */
                $menuItem = MenuItem::with('category')->findOrFail($line['menu_item_id']);
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = (float) $menuItem->unit_price;

                $deliveryPairId = $line['delivery_seat_pair_id'] ?? null;
                if ($deliveryPairId && $zone) {
                    $this->validateDeliveryPair((int) $deliveryPairId, $zone);
                }

                $deliveryLabel = null;
                if ($deliveryPairId) {
                    $pair = SeatPair::find($deliveryPairId);
                    $deliveryLabel = $pair ? "Pair {$pair->pair_sequence}" : null;
                } elseif ($zone) {
                    $deliveryLabel = $zone->defaultDeliveryLabel();
                }

                $item = OrderItem::create([
                    'order_header_id'           => $header->id,
                    'menu_item_id'              => $menuItem->id,
                    'quantity'                  => $qty,
                    'unit_price'                => $unitPrice,
                    'line_subtotal'             => round($unitPrice * $qty, 2),
                    'fulfillment_route'         => $menuItem->category?->route_type ?? 'NONE',
                    'delivery_seat_pair_id'     => $deliveryPairId,
                    'delivery_reference_label'  => $deliveryLabel,
                    'sent_to_production_at'     => now(),
                ]);

                $createdItems[] = $item;
            }

            // Group by route and create production tickets.
            $byRoute = collect($createdItems)->groupBy('fulfillment_route');
            foreach ($byRoute as $route => $items) {
                if ($route === 'NONE') {
                    continue;
                }
                $printer = $this->resolvePrinterForRoute($group, $route, PrinterRoute::DOC_PRODUCTION_TICKET);
                if (! $printer) {
                    throw new RuntimeException("No printer route configured for {$route}.");
                }

                $ticket = ProductionTicket::create([
                    'service_session_id'     => $group->service_session_id,
                    'billing_group_id'       => $group->id,
                    'occupied_zone_id'       => $zone?->id,
                    'printer_id'             => $printer->id,
                    'ticket_type'            => $route,
                    'ticket_status'          => 'PENDING',
                    'delivery_reference_label' => $zone?->defaultDeliveryLabel(),
                    'requested_at'           => now(),
                    'is_void_slip'           => false,
                    'is_reprint'             => false,
                    'created_by_user_id'     => $actor->id,
                ]);
                $ticket->items()->sync(collect($items)->pluck('id'));

                $this->printQueue->enqueueProductionTicket($ticket, $actor);

                Audit::record(
                    'PRODUCTION_TICKET_QUEUED',
                    "Ticket {$route} #{$ticket->id} criado para grupo {$group->display_code}",
                    ['route' => $route, 'lines' => count($items)],
                    [
                        'billing_group_id'      => $group->id,
                        'service_session_id'    => $group->service_session_id,
                        'occupied_zone_id'      => $zone?->id,
                        'production_ticket_id'  => $ticket->id,
                        'order_header_id'       => $header->id,
                        'actor_user_id'         => $actor->id,
                    ],
                );
            }

            Audit::record(
                'ORDER_SUBMITTED',
                "Pedido #{$header->id} submetido para grupo {$group->display_code} ({" . count($createdItems) . " linhas)",
                ['line_count' => count($createdItems)],
                [
                    'billing_group_id'   => $group->id,
                    'service_session_id' => $group->service_session_id,
                    'occupied_zone_id'   => $zone?->id,
                    'order_header_id'    => $header->id,
                    'actor_user_id'      => $actor->id,
                ],
            );

            return $header->refresh();
        });
    }

    public function voidItem(OrderItem $item, User $actor, ?string $reason = null): void
    {
        $this->ensureCan($actor, 'order.void_item');

        if (! $item->header->billingGroup?->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($item->voided_at) {
            return;
        }

        DB::transaction(function () use ($item, $actor, $reason) {
            $item->update([
                'voided_at'         => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason'       => $reason,
            ]);

            $header = $item->header;
            // Update header status if all items voided.
            $remaining = $header->items()->whereNull('voided_at')->count();
            if ($remaining === 0) {
                $header->update(['submission_status' => 'VOIDED']);
            } else {
                $header->update(['submission_status' => 'PARTIALLY_VOIDED']);
            }

            // Generate a void slip ticket for this single item.
            if (in_array($item->fulfillment_route, ['KITCHEN', 'BAR'], true)) {
                $printer = $this->resolvePrinterForRoute(
                    $header->billingGroup,
                    $item->fulfillment_route,
                    PrinterRoute::DOC_VOID_SLIP,
                );
                if ($printer) {
                    $voidTicket = ProductionTicket::create([
                        'service_session_id'        => $header->billingGroup->service_session_id,
                        'billing_group_id'          => $header->billing_group_id,
                        'occupied_zone_id'          => $header->occupied_zone_id,
                        'printer_id'                => $printer->id,
                        'ticket_type'               => 'VOID',
                        'ticket_status'             => 'PENDING',
                        'requested_at'              => now(),
                        'is_void_slip'              => true,
                        'is_reprint'                => false,
                        'created_by_user_id'        => $actor->id,
                        'delivery_reference_label'  => $item->delivery_reference_label,
                    ]);
                    $voidTicket->items()->sync([$item->id]);
                    $this->printQueue->enqueueProductionTicket($voidTicket, $actor);
                }
            }

            Audit::record(
                'ORDER_ITEM_VOIDED',
                "Linha #{$item->id} anulada",
                ['reason' => $reason],
                [
                    'billing_group_id' => $header->billing_group_id,
                    'order_header_id'  => $header->id,
                    'order_item_id'    => $item->id,
                    'service_session_id' => $header->billingGroup?->service_session_id,
                    'actor_user_id'    => $actor->id,
                ],
            );
        });
    }

    private function validateDeliveryPair(int $pairId, OccupiedZone $zone): void
    {
        $pair = SeatPair::find($pairId);
        if (! $pair) {
            throw new RuntimeException('Delivery pair not found.');
        }
        if ($pair->row_id !== $zone->row_id) {
            throw new RuntimeException('Delivery pair must be in the same row as the occupied zone.');
        }
        if ($pair->pair_sequence < $zone->start_seat_pair_sequence
            || $pair->pair_sequence > $zone->end_seat_pair_sequence) {
            throw new RuntimeException('Delivery pair must fall inside the occupied zone range.');
        }
    }

    private function resolvePrinterForRoute(BillingGroup $group, string $route, string $documentType): ?Printer
    {
        $venueId = $group->serviceSession?->venue_id;
        if (! $venueId) {
            return null;
        }
        $route = PrinterRoute::with('printer')
            ->where('venue_id', $venueId)
            ->where('document_type', $documentType)
            ->where('fulfillment_route', $route)
            ->where('is_active', true)
            ->first();

        return $route?->printer;
    }
}
