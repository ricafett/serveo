<?php

namespace App\Http\Controllers\Api;

use App\Domain\Orders\OrderService;
use App\Http\Controllers\ApiController;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billingGroupId' => ['required', 'exists:billing_groups,id'],
            'occupiedZoneId' => ['nullable', 'exists:occupied_zones,id'],
            'idempotencyKey' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menuItemId' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.deliverySeatPairId' => ['nullable', 'exists:seat_pairs,id'],
        ]);

        $group = BillingGroup::findOrFail($validated['billingGroupId']);

        if ($group->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot create orders for a closed billing group.', status: 409);
        }

        $zone = null;
        if (! empty($validated['occupiedZoneId'])) {
            $zone = OccupiedZone::find($validated['occupiedZoneId']);
            if ($zone && $zone->billing_group_id !== $group->id) {
                return $this->error('INVALID_DELIVERY_TARGET', 'Occupied zone does not belong to this billing group.', status: 400);
            }
        }

        $lines = collect($validated['items'])->map(fn ($item) => [
            'menu_item_id' => $item['menuItemId'],
            'quantity' => $item['quantity'],
            'delivery_seat_pair_id' => $item['deliverySeatPairId'] ?? null,
        ])->all();

        try {
            $header = $this->orderService->submit(
                $group,
                $request->user(),
                $lines,
                $zone,
                $validated['notes'] ?? null,
                $validated['idempotencyKey'] ?? null,
            );
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success($this->toOrderDto($header->refresh()->load('items.menuItem')), status: 201);
    }

    public function show(OrderHeader $orderHeader): JsonResponse
    {
        $orderHeader->load(['items.menuItem', 'billingGroup', 'occupiedZone', 'orderedBy']);

        return $this->success($this->toOrderDto($orderHeader));
    }

    public function voidItems(Request $request, OrderHeader $orderHeader): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.orderItemId' => ['required', 'exists:order_items,id'],
            'items.*.reason' => ['required', 'string', 'max:500'],
        ]);

        if ($orderHeader->billingGroup->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot void items on a closed billing group.', status: 409);
        }

        $affected = [];
        $tickets = [];

        try {
            $this->authorize('voidItem', $orderHeader);

            DB::transaction(function () use ($request, $orderHeader, $validated, &$affected, &$tickets) {
                foreach ($validated['items'] as $itemData) {
                    $item = OrderItem::where('order_header_id', $orderHeader->id)
                        ->where('id', $itemData['orderItemId'])
                        ->first();

                    if (! $item) {
                        continue;
                    }

                    $this->orderService->voidItem($item, $request->user(), $itemData['reason']);
                    $affected[] = $item->refresh();
                }

                // Load any void tickets created
                $tickets = $orderHeader->billingGroup->productionTickets()
                    ->where('is_void_slip', true)
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->get();
            });
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success([
            'affectedItems' => collect($affected)->map(fn ($item) => [
                'orderItemId' => $item->id,
                'voidedAt' => $item->voided_at?->toIso8601String(),
                'voidReason' => $item->void_reason,
            ])->all(),
            'voidTickets' => $tickets->map(fn ($t) => [
                'productionTicketId' => $t->id,
                'ticketType' => $t->ticket_type,
                'isVoidSlip' => $t->is_void_slip,
            ])->all(),
        ]);
    }

    public function voidOrder(Request $request, OrderHeader $orderHeader): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($orderHeader->billingGroup->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot void orders on a closed billing group.', status: 409);
        }

        try {
            $this->authorize('voidOrder', $orderHeader);

            $header = $this->orderService->voidOrder($orderHeader, $request->user(), $validated['reason']);
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        $header->load('items.menuItem');
        $tickets = $header->billingGroup->productionTickets()
            ->where('is_void_slip', true)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->get();

        return $this->success([
            'orderHeaderId' => $header->id,
            'submissionStatus' => $header->submission_status,
            'affectedItems' => $header->items
                ->filter(fn ($item) => $item->voided_at !== null)
                ->map(fn ($item) => [
                    'orderItemId' => $item->id,
                    'voidedAt' => $item->voided_at?->toIso8601String(),
                    'voidReason' => $item->void_reason,
                ])
                ->values()
                ->all(),
            'voidTickets' => $tickets->map(fn ($t) => [
                'productionTicketId' => $t->id,
                'ticketType' => $t->ticket_type,
                'isVoidSlip' => $t->is_void_slip,
            ])->all(),
        ]);
    }

    private function toOrderDto(OrderHeader $order): array
    {
        return [
            'orderHeaderId' => $order->id,
            'billingGroupId' => $order->billing_group_id,
            'occupiedZoneId' => $order->occupied_zone_id,
            'orderedBy' => [
                'userId' => $order->ordered_by_user_id,
                'displayName' => $order->orderedBy?->name,
            ],
            'orderedAt' => $order->ordered_at?->toIso8601String(),
            'submissionStatus' => $order->submission_status,
            'notes' => $order->notes,
            'items' => $order->items->map(fn ($item) => [
                'orderItemId' => $item->id,
                'menuItemId' => $item->menu_item_id,
                'menuItemName' => $item->menuItem?->display_name,
                'quantity' => $item->quantity,
                'unitPrice' => (string) $item->unit_price,
                'lineSubtotal' => (string) $item->line_subtotal,
                'fulfillmentRoute' => $item->fulfillment_route,
                'deliverySeatPairId' => $item->delivery_seat_pair_id,
                'deliveryReferenceLabel' => $item->delivery_reference_label,
                'voidedAt' => $item->voided_at?->toIso8601String(),
                'voidReason' => $item->void_reason,
            ])->values()->all(),
        ];
    }
}
