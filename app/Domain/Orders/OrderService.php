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
use Illuminate\Database\UniqueConstraintViolationException;
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
     * When an $idempotencyKey is provided, duplicate submissions with the same key
     * for the same billing group are detected and safely return the existing order
     * instead of creating a duplicate.
     *
     * @param  array<int, array{menu_item_id:int, quantity:int, delivery_seat_pair_id?:int|null, variant_name?:string|null, modifier_name?:string|null}>  $lines
     */
    public function submit(
        BillingGroup $group,
        User $actor,
        array $lines,
        ?OccupiedZone $zone = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
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

        // Idempotency check: if a key is provided, return the existing order instead of creating a duplicate.
        if ($idempotencyKey !== null) {
            $existing = OrderHeader::where('billing_group_id', $group->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($group, $actor, $lines, $zone, $notes, $idempotencyKey) {
            try {
                $header = OrderHeader::create([
                    'billing_group_id' => $group->id,
                    'occupied_zone_id' => $zone?->id,
                    'ordered_by_user_id' => $actor->id,
                    'ordered_at' => now(),
                    'submission_status' => 'SUBMITTED',
                    'notes' => $notes,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Race condition: another request inserted this idempotency key
                // between our pre-check and the insert. Return the existing order.
                return OrderHeader::where('billing_group_id', $group->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }

            $createdItems = [];
            foreach ($lines as $line) {
                /** @var MenuItem $menuItem */
                $menuItem = MenuItem::with('category')->findOrFail($line['menu_item_id']);
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = (float) $menuItem->unit_price;
                $variantName = $line['variant_name'] ?? null;
                $modifierName = $line['modifier_name'] ?? null;

                // Validate variant requirement
                if ($menuItem->hasVariants() && empty($variantName)) {
                    throw new RuntimeException("A variant must be selected for '{$menuItem->display_name}'.");
                }

                // Validate variant exists
                if ($variantName && ! $menuItem->activeVariants()->where('display_name', $variantName)->exists()) {
                    throw new RuntimeException("Invalid variant '{$variantName}' for '{$menuItem->display_name}'.");
                }

                // Validate modifier if present
                if ($modifierName && $menuItem->modifierSet) {
                    $set = $menuItem->modifierSet;
                    $modifierNames = array_map('trim', explode(',', $modifierName));

                    if ($set->isSingle() && count($modifierNames) > 1) {
                        throw new RuntimeException("Only one modifier may be selected for '{$menuItem->display_name}'.");
                    }

                    $validNames = $set->items()->where('is_active', true)->pluck('display_name')->all();
                    foreach ($modifierNames as $name) {
                        if (! in_array($name, $validNames, true)) {
                            throw new RuntimeException("Invalid modifier '{$name}' for '{$menuItem->display_name}'.");
                        }
                    }
                }

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
                    'order_header_id' => $header->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => round($unitPrice * $qty, 2),
                    'fulfillment_route' => $menuItem->category?->route_type ?? 'NONE',
                    'delivery_seat_pair_id' => $deliveryPairId,
                    'delivery_reference_label' => $deliveryLabel,
                    'sent_to_production_at' => now(),
                    'variant_name' => $variantName,
                    'modifier_name' => $modifierName,
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
                    'service_session_id' => $group->service_session_id,
                    'billing_group_id' => $group->id,
                    'occupied_zone_id' => $zone?->id,
                    'printer_id' => $printer->id,
                    'ticket_type' => $route,
                    'ticket_status' => 'PENDING',
                    'delivery_reference_label' => $deliveryLabel ?? null,
                    'requested_at' => now(),
                    'is_void_slip' => false,
                    'is_reprint' => false,
                    'created_by_user_id' => $actor->id,
                ]);
                $ticket->items()->sync(collect($items)->pluck('id'));

                $this->printQueue->enqueueProductionTicket($ticket, $actor);

                Audit::record(
                    'PRODUCTION_TICKET_QUEUED',
                    "Ticket {$route} #{$ticket->id} criado para grupo {$group->display_code}",
                    ['route' => $route, 'lines' => count($items)],
                    [
                        'billing_group_id' => $group->id,
                        'service_session_id' => $group->service_session_id,
                        'occupied_zone_id' => $zone?->id,
                        'production_ticket_id' => $ticket->id,
                        'order_header_id' => $header->id,
                        'actor_user_id' => $actor->id,
                    ],
                );
            }

            Audit::record(
                'ORDER_SUBMITTED',
                "Pedido #{$header->id} submetido para grupo {$group->display_code} ({".count($createdItems).' linhas)',
                ['line_count' => count($createdItems)],
                [
                    'billing_group_id' => $group->id,
                    'service_session_id' => $group->service_session_id,
                    'occupied_zone_id' => $zone?->id,
                    'order_header_id' => $header->id,
                    'actor_user_id' => $actor->id,
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
                'voided_at' => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason' => $reason,
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
            // No hardcoded route guard — resolvePrinterForRoute returns null
            // for any route without an active PrinterRoute, and we skip below.
            $printer = $this->resolvePrinterForRoute(
                $header->billingGroup,
                $item->fulfillment_route,
                PrinterRoute::DOC_PRODUCTION_TICKET,
            );
            if ($printer) {
                $voidTicket = ProductionTicket::create([
                    'service_session_id' => $header->billingGroup->service_session_id,
                    'billing_group_id' => $header->billing_group_id,
                    'occupied_zone_id' => $header->occupied_zone_id,
                    'printer_id' => $printer->id,
                    'ticket_type' => 'VOID',
                    'ticket_status' => 'PENDING',
                    'requested_at' => now(),
                    'is_void_slip' => true,
                    'is_reprint' => false,
                    'created_by_user_id' => $actor->id,
                    'delivery_reference_label' => $item->delivery_reference_label,
                ]);
                $voidTicket->items()->sync([$item->id]);
                $this->printQueue->enqueueProductionTicket($voidTicket, $actor);
            }

            Audit::record(
                'ORDER_ITEM_VOIDED',
                "Linha #{$item->id} anulada",
                ['reason' => $reason],
                [
                    'billing_group_id' => $header->billing_group_id,
                    'order_header_id' => $header->id,
                    'order_item_id' => $item->id,
                    'service_session_id' => $header->billingGroup?->service_session_id,
                    'actor_user_id' => $actor->id,
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
