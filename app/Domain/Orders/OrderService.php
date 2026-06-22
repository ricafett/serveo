<?php

namespace App\Domain\Orders;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Domain\Printing\PrintQueueService;
use App\Models\BillingGroup;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\SeatPair;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    use ChecksPermissions;

    private const STATUS_DRAFT = 'DRAFT';

    private const STATUS_SUBMITTED = 'SUBMITTED';

    public function __construct(private readonly PrintQueueService $printQueue) {}

    /**
     * Create one OrderHeader plus items and immediately group lines by route into
     * ProductionTicket records, then queue print jobs.
     *
     * When an $idempotencyKey is provided, duplicate submissions with the same key
     * for the same billing group are detected and safely return the existing order
     * instead of creating a duplicate.
     *
     * @param  array<int, array{menu_item_id:int, quantity:int, delivery_seat_pair_id?:int|null, variant_name?:string|null, modifier_name?:string|null, note?:string|null}>  $lines
     */
    public function submit(
        BillingGroup $group,
        User $actor,
        array $lines,
        ?OccupiedZone $zone = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        bool $printServerOrder = false,
    ): OrderHeader {
        return $this->createOrder(
            $group,
            $actor,
            $lines,
            $zone,
            $notes,
            $idempotencyKey,
            self::STATUS_SUBMITTED,
            $printServerOrder,
        );
    }

    /**
     * @param  array<int, array{menu_item_id:int, quantity:int, delivery_seat_pair_id?:int|null, variant_name?:string|null, modifier_name?:string|null, note?:string|null}>  $lines
     */
    public function saveDraft(
        BillingGroup $group,
        User $actor,
        array $lines,
        ?OccupiedZone $zone = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): OrderHeader {
        return $this->createOrder(
            $group,
            $actor,
            $lines,
            $zone,
            $notes,
            $idempotencyKey,
            self::STATUS_DRAFT,
            false,
        );
    }

    public function submitDraft(OrderHeader $header, User $actor): OrderHeader
    {
        $this->ensureCan($actor, 'order.create');

        $header->loadMissing([
            'billingGroup.serviceSession',
            'occupiedZone',
            'items.menuItem.category',
        ]);

        if ($header->submission_status !== self::STATUS_DRAFT) {
            throw new RuntimeException('Only saved orders can be submitted to production.');
        }

        if ($header->items->isEmpty()) {
            throw new RuntimeException('Cannot submit an empty order.');
        }

        $group = $header->billingGroup;
        if (! $group) {
            throw new RuntimeException('Billing group not found.');
        }

        $zone = $header->occupiedZone;

        if ($group->is_closed) {
            throw new RuntimeException('Cannot order on a closed billing group.');
        }
        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }
        if ($zone && $zone->billing_group_id !== $group->id) {
            throw new RuntimeException('Occupied zone does not belong to this billing group.');
        }

        return DB::transaction(function () use ($header, $actor, $group, $zone) {
            $header->items->each(function (OrderItem $item) {
                $item->update(['sent_to_production_at' => now()]);
            });

            $this->queueProductionTickets($header, $group, $actor, $zone, $header->items);

            $header->update(['submission_status' => self::STATUS_SUBMITTED]);

            Audit::record(
                'ORDER_SUBMITTED',
                "Pedido #{$header->id} submetido para grupo {$group->display_code} (".count($header->items).') linhas)',
                ['line_count' => count($header->items), 'from_draft' => true],
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

    /**
     * @param  array<int, array{menu_item_id:int, quantity:int, delivery_seat_pair_id?:int|null, variant_name?:string|null, modifier_name?:string|null, note?:string|null}>  $lines
     */
    private function createOrder(
        BillingGroup $group,
        User $actor,
        array $lines,
        ?OccupiedZone $zone,
        ?string $notes,
        ?string $idempotencyKey,
        string $submissionStatus,
        bool $printServerOrder,
    ): OrderHeader {
        $this->ensureCan($actor, 'order.create');

        if (empty($lines)) {
            throw new RuntimeException('Cannot submit an empty order.');
        }

        if (! in_array($submissionStatus, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true)) {
            throw new RuntimeException('Invalid order submission status.');
        }

        $isDraft = $submissionStatus === self::STATUS_DRAFT;

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
                ->where('submission_status', $submissionStatus)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($group, $actor, $lines, $zone, $notes, $idempotencyKey, $submissionStatus, $isDraft, $printServerOrder) {
            try {
                $header = OrderHeader::create([
                    'billing_group_id' => $group->id,
                    'occupied_zone_id' => $zone?->id,
                    'ordered_by_user_id' => $actor->id,
                    'ordered_at' => now(),
                    'submission_status' => $submissionStatus,
                    'notes' => $notes,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Race condition: another request inserted this idempotency key
                // between our pre-check and the insert. Return the existing order.
                return OrderHeader::where('billing_group_id', $group->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('submission_status', $submissionStatus)
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
                    'sent_to_production_at' => $isDraft ? null : now(),
                    'variant_name' => $variantName,
                    'modifier_name' => $modifierName,
                    'note' => $line['note'] ?? null,
                ]);

                $createdItems[] = $item;
            }

            if (! $isDraft) {
                $this->queueProductionTickets($header, $group, $actor, $zone, collect($createdItems));

                if ($printServerOrder) {
                    $this->queueServerOrder($header, $actor);
                }
            }

            Audit::record(
                $isDraft ? 'ORDER_DRAFT_SAVED' : 'ORDER_SUBMITTED',
                $isDraft
                    ? "Pedido #{$header->id} guardado sem envio para produção"
                    : "Pedido #{$header->id} submetido para grupo {$group->display_code} ({".count($createdItems).' linhas)',
                $isDraft
                    ? ['line_count' => count($createdItems)]
                    : ['line_count' => count($createdItems)],
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

    /**
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     */
    private function queueProductionTickets(
        OrderHeader $header,
        BillingGroup $group,
        User $actor,
        ?OccupiedZone $zone,
        \Illuminate\Support\Collection $items,
    ): void {
        $byRoute = $items->groupBy('fulfillment_route');

        foreach ($byRoute as $route => $routeItems) {
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
                'ticket_sequence_route' => $route,
                'route_ticket_number' => ProductionTicket::nextRouteTicketNumber($group->service_session_id, $route),
                'ticket_status' => 'PENDING',
                'delivery_reference_label' => $routeItems->first()?->delivery_reference_label,
                'requested_at' => now(),
                'is_void_slip' => false,
                'is_reprint' => false,
                'created_by_user_id' => $actor->id,
            ]);
            $ticket->items()->sync($routeItems->pluck('id'));

            $this->printQueue->enqueueProductionTicket($ticket, $actor);

            Audit::record(
                'PRODUCTION_TICKET_QUEUED',
                "Ticket {$route} #{$ticket->id} criado para grupo {$group->display_code}",
                ['route' => $route, 'lines' => $routeItems->count()],
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
    }

    /**
     * Mark a single order item as delivered. No-op if already delivered or voided.
     */
    public function markDelivered(OrderItem $item, User $actor): void
    {
        if ($item->isVoided()) {
            throw new RuntimeException('Cannot mark a voided item as delivered.');
        }

        if ($item->isDelivered()) {
            return;
        }

        $item->update([
            'delivered_at' => now(),
            'delivered_by_user_id' => $actor->id,
        ]);

        $header = $item->header()->with('billingGroup')->first();

        Audit::record(
            'ORDER_ITEM_DELIVERED',
            "Item #{$item->id} marcado como entregue",
            [],
            [
                'billing_group_id' => $header?->billing_group_id,
                'order_header_id' => $header?->id,
                'order_item_id' => $item->id,
                'service_session_id' => $header?->billingGroup?->service_session_id,
                'actor_user_id' => $actor->id,
            ],
        );
    }

    /**
     * Unmark a delivered order item. No-op if not delivered.
     */
    public function unmarkDelivered(OrderItem $item, User $actor): void
    {
        if (! $item->isDelivered()) {
            return;
        }

        $item->update([
            'delivered_at' => null,
            'delivered_by_user_id' => null,
        ]);

        $header = $item->header()->with('billingGroup')->first();

        Audit::record(
            'ORDER_ITEM_UNDELIVERED',
            "Item #{$item->id} desmarcado como entregue",
            [],
            [
                'billing_group_id' => $header?->billing_group_id,
                'order_header_id' => $header?->id,
                'order_item_id' => $item->id,
                'service_session_id' => $header?->billingGroup?->service_session_id,
                'actor_user_id' => $actor->id,
            ],
        );
    }

    public function voidItem(OrderItem $item, User $actor, ?string $reason = null): void
    {
        $this->assertCanVoidOrder($actor, $item->header);

        if ($item->header->billingGroup?->is_closed) {
            throw new RuntimeException('Cannot void items on a closed billing group.');
        }

        if (! $item->header->billingGroup?->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($item->voided_at) {
            return;
        }

        DB::transaction(function () use ($item, $actor, $reason) {
            $this->voidSingleItem($item->fresh(['header.billingGroup']), $actor, $reason);
        });
    }

    public function voidOrder(OrderHeader $header, User $actor, ?string $reason = null): OrderHeader
    {
        $this->assertCanVoidOrder($actor, $header);

        if ($header->billingGroup?->is_closed) {
            throw new RuntimeException('Cannot void orders on a closed billing group.');
        }

        if (! $header->billingGroup?->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        return DB::transaction(function () use ($header, $actor, $reason) {
            $header = $header->fresh(['items', 'billingGroup']);
            $itemsToVoid = $header->items->filter(fn (OrderItem $item) => ! $item->isVoided())->values();

            if ($itemsToVoid->isEmpty()) {
                return $header->refresh();
            }

            foreach ($itemsToVoid as $item) {
                $this->voidSingleItem($item->fresh(['header.billingGroup']), $actor, $reason);
            }

            Audit::record(
                'ORDER_VOIDED',
                "Pedido #{$header->id} anulado",
                [
                    'reason' => $reason,
                    'item_count' => $itemsToVoid->count(),
                ],
                [
                    'billing_group_id' => $header->billing_group_id,
                    'order_header_id' => $header->id,
                    'service_session_id' => $header->billingGroup?->service_session_id,
                    'actor_user_id' => $actor->id,
                ],
            );

            return $header->refresh();
        });
    }

    /**
     * Void one or more items from the same order, optionally grouped into a single
     * void ticket per fulfillment route.
     *
     * @param  int[]  $itemIds
     */
    public function voidItems(array $itemIds, User $actor, ?string $reason = null): OrderHeader
    {
        if (empty($itemIds)) {
            throw new RuntimeException('No items selected for void.');
        }

        $items = OrderItem::with('header.billingGroup.serviceSession')
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('No items found.');
        }

        $orderIds = $items->pluck('header.id')->unique();
        if ($orderIds->count() !== 1) {
            throw new RuntimeException('All items must belong to the same order.');
        }

        $header = $items->first()->header;
        $this->assertCanVoidOrder($actor, $header);

        if ($header->billingGroup?->is_closed) {
            throw new RuntimeException('Cannot void items on a closed billing group.');
        }

        if (! $header->billingGroup?->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        // Only void items that are not already voided and not delivered.
        $toVoid = $items->filter(fn (OrderItem $item) => ! $item->isVoided() && ! $item->isDelivered());
        if ($toVoid->isEmpty()) {
            return $header->refresh();
        }

        return DB::transaction(function () use ($header, $toVoid, $actor, $reason) {
            // Group by fulfillment route.
            $byRoute = $toVoid->groupBy('fulfillment_route');

            foreach ($byRoute as $route => $routeItems) {
                foreach ($routeItems as $item) {
                    $item->update([
                        'voided_at' => now(),
                        'voided_by_user_id' => $actor->id,
                        'void_reason' => $reason,
                    ]);
                }

                $printer = $this->resolvePrinterForRoute(
                    $header->billingGroup,
                    $route,
                    PrinterRoute::DOC_PRODUCTION_TICKET,
                );

                $voidTicket = null;

                if ($printer) {
                    $voidTicket = ProductionTicket::create([
                        'service_session_id' => $header->billingGroup->service_session_id,
                        'billing_group_id' => $header->billing_group_id,
                        'occupied_zone_id' => $header->occupied_zone_id,
                        'printer_id' => $printer->id,
                        'ticket_type' => 'VOID',
                        'ticket_sequence_route' => $route,
                        'route_ticket_number' => $this->originalRouteTicketNumber($routeItems->first(), $route),
                        'ticket_status' => 'PENDING',
                        'requested_at' => now(),
                        'is_void_slip' => true,
                        'is_reprint' => false,
                        'created_by_user_id' => $actor->id,
                        'delivery_reference_label' => $routeItems->first()->delivery_reference_label,
                    ]);
                    $voidTicket->items()->sync($routeItems->pluck('id'));
                    $this->printQueue->enqueueProductionTicket($voidTicket, $actor);
                }

                Audit::record(
                    'ORDER_ITEMS_VOIDED',
                    "{$routeItems->count()} itens anulados na rota {$route} (pedido #{$header->id})",
                    [
                        'reason' => $reason,
                        'item_count' => $routeItems->count(),
                        'void_slip_skipped' => $voidTicket === null,
                    ],
                    [
                        'billing_group_id' => $header->billing_group_id,
                        'order_header_id' => $header->id,
                        'service_session_id' => $header->billingGroup?->service_session_id,
                        'actor_user_id' => $actor->id,
                        'production_ticket_id' => $voidTicket?->id,
                    ],
                );
            }

            $remaining = $header->items()->whereNull('voided_at')->count();
            $header->update([
                'submission_status' => $remaining === 0 ? 'VOIDED' : 'PARTIALLY_VOIDED',
            ]);

            return $header->refresh();
        });
    }

    private function assertCanVoidOrder(User $actor, OrderHeader $header): void
    {
        $this->ensureCan($actor, 'order.void_item');

        if ($actor->hasRole('ADMIN') || $actor->hasRole('CASHIER')) {
            return;
        }

        if ($actor->hasRole('SERVER') && $header->ordered_by_user_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('Unauthorized to void this order.');
    }

    private function voidSingleItem(OrderItem $item, User $actor, ?string $reason = null): void
    {
        if ($item->voided_at) {
            return;
        }

        if ($item->isDelivered()) {
            throw new RuntimeException('Cannot void a delivered item. Unmark as delivered first.');
        }

        $item->update([
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ]);

        $header = $item->header;
        $remaining = $header->items()->whereNull('voided_at')->count();
        $header->update([
            'submission_status' => $remaining === 0 ? 'VOIDED' : 'PARTIALLY_VOIDED',
        ]);

        $printer = $this->resolvePrinterForRoute(
            $header->billingGroup,
            $item->fulfillment_route,
            PrinterRoute::DOC_PRODUCTION_TICKET,
        );

        $voidTicket = null;

        if ($printer) {
            $voidTicket = ProductionTicket::create([
                'service_session_id' => $header->billingGroup->service_session_id,
                'billing_group_id' => $header->billing_group_id,
                'occupied_zone_id' => $header->occupied_zone_id,
                'printer_id' => $printer->id,
                'ticket_type' => 'VOID',
                'ticket_sequence_route' => $item->fulfillment_route,
                'route_ticket_number' => $this->originalRouteTicketNumber($item, $item->fulfillment_route),
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
            [
                'reason' => $reason,
                'void_slip_skipped' => $voidTicket === null,
            ],
            [
                'billing_group_id' => $header->billing_group_id,
                'order_header_id' => $header->id,
                'order_item_id' => $item->id,
                'service_session_id' => $header->billingGroup?->service_session_id,
                'actor_user_id' => $actor->id,
                'production_ticket_id' => $voidTicket?->id,
            ],
        );
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

    private function originalRouteTicketNumber(OrderItem $item, string $route): int
    {
        $number = $item->productionTickets()
            ->where('is_void_slip', false)
            ->where('ticket_sequence_route', $route)
            ->value('route_ticket_number');

        return $number ?: ProductionTicket::nextRouteTicketNumber($item->header->billingGroup->service_session_id, $route);
    }

    private function queueServerOrder(OrderHeader $header, User $actor): void
    {
        $printerId = CashierPrinterAssignment::query()
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->value('printer_id');

        if (! $printerId) {
            throw new RuntimeException('No cashier printer is assigned to this user.');
        }

        $this->printQueue->enqueueServerOrder($header, $printerId, $actor);

        Audit::record(
            'SERVER_ORDER_QUEUED',
            "Pedido de servente em fila para o pedido #{$header->id}",
            [],
            [
                'billing_group_id' => $header->billing_group_id,
                'service_session_id' => $header->billingGroup?->service_session_id,
                'order_header_id' => $header->id,
                'actor_user_id' => $actor->id,
            ],
        );
    }
}
