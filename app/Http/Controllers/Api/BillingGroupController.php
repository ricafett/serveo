<?php

namespace App\Http\Controllers\Api;

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Http\Controllers\ApiController;
use App\Models\BillingGroup;
use App\Models\OrderHeader;
use App\Models\Row;
use App\Models\ServiceSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingGroupController extends ApiController
{
    public function __construct(
        private readonly BillingGroupService $billingGroupService,
        private readonly OccupancyService $occupancyService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'statusCode' => ['required', 'string', 'exists:billing_statuses,code'],
            'coverCount' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'zones' => ['nullable', 'array'],
            'zones.*.rowId' => ['required', 'exists:rows,id'],
            'zones.*.startSeatPairSequence' => ['required', 'integer', 'min:1'],
            'zones.*.endSeatPairSequence' => ['required', 'integer', 'min:1'],
            'zones.*.deliveryMode' => ['nullable', 'string', 'in:CENTER,SPECIFIC_SEAT_PAIR'],
        ]);

        $session = ServiceSession::where('status', 'OPEN')
            ->orderBy('starts_at', 'desc')
            ->first();

        if (! $session) {
            return $this->error('NOT_FOUND', 'No active service session found.', status: 404);
        }

        try {
            $group = DB::transaction(function () use ($request, $validated, $session) {
                $group = $this->billingGroupService->open(
                    $session,
                    $request->user(),
                    $validated['coverCount'] ?? null,
                    $validated['notes'] ?? null,
                    $validated['statusCode'] ?? null,
                    $validated['name'] ?? null,
                );

                // Create zones if provided
                if (! empty($validated['zones'])) {
                    foreach ($validated['zones'] as $zoneData) {
                        $row = Row::findOrFail($zoneData['rowId']);
                        $this->occupancyService->assignZone(
                            $group,
                            $row,
                            (int) $zoneData['startSeatPairSequence'],
                            (int) $zoneData['endSeatPairSequence'],
                            $request->user(),
                            $zoneData['deliveryCenterLabel'] ?? null,
                        );
                    }
                    $group->load('occupiedZones');
                }

                return $group;
            });
        } catch (ZoneOverlapException $e) {
            return $this->error('ZONE_OVERLAP', $e->getMessage(), status: 409);
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success($this->toBillingGroupDto($group), status: 201);
    }

    public function show(BillingGroup $billingGroup): JsonResponse
    {
        $billingGroup->load(['occupiedZones.row', 'status', 'orderHeaders.items', 'billingDocuments', 'paymentRecords']);

        return $this->success($this->toBillingGroupDto($billingGroup, detailed: true));
    }

    public function update(Request $request, BillingGroup $billingGroup): JsonResponse
    {
        $validated = $request->validate([
            'versionNumber' => ['nullable', 'integer'],
            'statusCode' => ['nullable', 'string', 'exists:billing_statuses,code'],
            'coverCount' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['versionNumber']) && $billingGroup->version_number !== $validated['versionNumber']) {
            return $this->error('VERSION_CONFLICT', 'Billing group was modified by another user.', status: 409);
        }

        if ($billingGroup->is_closed && ! empty($validated)) {
            return $this->error('GROUP_CLOSED', 'Cannot modify a closed billing group.', status: 409);
        }

        try {
            $statusChanged = false;
            $fieldsChanged = false;

            DB::transaction(function () use ($request, $billingGroup, $validated, &$statusChanged, &$fieldsChanged) {
                if (! empty($validated['statusCode'])) {
                    $this->billingGroupService->setStatus(
                        $billingGroup,
                        $validated['statusCode'],
                        $request->user(),
                        null, // controller already checked version at the gate
                    );
                    $statusChanged = true;
                }

                $update = [];
                if (array_key_exists('coverCount', $validated)) {
                    $update['cover_count'] = $validated['coverCount'];
                }
                if (array_key_exists('notes', $validated)) {
                    $update['notes'] = $validated['notes'];
                }
                if (! empty($update)) {
                    $billingGroup->update($update);
                    $fieldsChanged = true;
                }

                // If only fields changed (no status change), we must still bump version.
                if (! $statusChanged && $fieldsChanged) {
                    $billingGroup->increment('version_number');
                }
            });
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'VERSION_CONFLICT')
                ? 'VERSION_CONFLICT'
                : 'INVALID_STATUS_TRANSITION';

            return $this->error($code, $e->getMessage(), status: 409);
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        }

        return $this->success($this->toBillingGroupDto($billingGroup->refresh()));
    }

    public function storeZones(Request $request, BillingGroup $billingGroup): JsonResponse
    {
        $validated = $request->validate([
            'zones' => ['required', 'array', 'min:1'],
            'zones.*.rowId' => ['required', 'exists:rows,id'],
            'zones.*.startSeatPairSequence' => ['required', 'integer', 'min:1'],
            'zones.*.endSeatPairSequence' => ['required', 'integer', 'min:1'],
            'zones.*.deliveryMode' => ['nullable', 'string', 'in:CENTER,SPECIFIC_SEAT_PAIR'],
        ]);

        if ($billingGroup->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot assign zones to a closed billing group.', status: 409);
        }

        try {
            DB::transaction(function () use ($request, $billingGroup, $validated) {
                foreach ($validated['zones'] as $zoneData) {
                    $row = Row::findOrFail($zoneData['rowId']);
                    $this->occupancyService->assignZone(
                        $billingGroup,
                        $row,
                        (int) $zoneData['startSeatPairSequence'],
                        (int) $zoneData['endSeatPairSequence'],
                        $request->user(),
                        $zoneData['deliveryCenterLabel'] ?? null,
                    );
                }
            });
        } catch (ZoneOverlapException $e) {
            return $this->error('ZONE_OVERLAP', $e->getMessage(), status: 409);
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success($this->toBillingGroupDto($billingGroup->refresh()->load('occupiedZones')));
    }

    public function orders(BillingGroup $billingGroup): JsonResponse
    {
        $orders = $billingGroup->orderHeaders()
            ->with(['items.menuItem', 'occupiedZone', 'orderedBy'])
            ->orderBy('ordered_at')
            ->get();

        return $this->success($orders->map(fn ($o) => $this->toOrderDto($o))->all());
    }

    public function productionTickets(BillingGroup $billingGroup): JsonResponse
    {
        $tickets = $billingGroup->productionTickets()
            ->with(['printer', 'items'])
            ->orderBy('requested_at', 'desc')
            ->get();

        return $this->success($tickets->map(fn ($t) => [
            'productionTicketId' => $t->id,
            'ticketType' => $t->ticket_type,
            'ticketStatus' => $t->ticket_status,
            'billingGroupId' => $t->billing_group_id,
            'occupiedZoneId' => $t->occupied_zone_id,
            'printerId' => $t->printer_id,
            'printedAt' => $t->printed_at?->toIso8601String(),
            'isVoidSlip' => $t->is_void_slip,
            'isReprint' => $t->is_reprint,
        ])->all());
    }

    public function billSummary(BillingGroup $billingGroup): JsonResponse
    {
        $billingGroup->load(['occupiedZones.row', 'orderHeaders.items.menuItem', 'paymentRecords']);

        $lines = $billingGroup->orderHeaders
            ->flatMap->items
            ->whereNull('voided_at')
            ->map(fn ($item) => [
                'orderItemId' => $item->id,
                'menuItemName' => $item->menuItem?->display_name,
                'quantity' => $item->quantity,
                'unitPrice' => (string) $item->unit_price,
                'lineSubtotal' => (string) $item->line_subtotal,
            ])->values()->all();

        $charges = $billingGroup->chargesTotal();
        $payments = $billingGroup->paymentsTotal();

        return $this->success([
            'billingGroupId' => $billingGroup->id,
            'displayCode' => $billingGroup->display_code,
            'displayLabel' => $billingGroup->longLabel(),
            'statusCode' => $billingGroup->status?->code,
            'zones' => $billingGroup->occupiedZones->map(fn ($z) => [
                'occupiedZoneId' => $z->id,
                'rowCode' => $z->row?->row_code,
                'startSeatPairSequence' => $z->start_seat_pair_sequence,
                'endSeatPairSequence' => $z->end_seat_pair_sequence,
            ])->all(),
            'lineItems' => $lines,
            'subtotal' => (string) $charges,
            'total' => (string) $charges,
            'paymentsToDate' => $billingGroup->paymentRecords->where('is_voided', false)->map(fn ($p) => [
                'paymentRecordId' => $p->id,
                'amount' => (string) $p->amount,
                'paymentLabel' => $p->payment_label,
                'recordedAt' => $p->recorded_at?->toIso8601String(),
            ])->values()->all(),
            'remainingBalance' => (string) max(0, round($charges - $payments, 2)),
        ]);
    }

    public function reopen(Request $request, BillingGroup $billingGroup): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'versionNumber' => ['nullable', 'integer'],
        ]);

        if (! $billingGroup->is_closed) {
            return $this->error('CONFLICT', 'Billing group is already open.', status: 409);
        }

        try {
            $this->billingGroupService->reopen(
                $billingGroup,
                $request->user(),
                $validated['versionNumber'] ?? null,
            );
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'VERSION_CONFLICT')
                ? 'VERSION_CONFLICT'
                : 'INVALID_STATUS_TRANSITION';

            return $this->error($code, $e->getMessage(), status: 409);
        }

        return $this->success($this->toBillingGroupDto($billingGroup->refresh()));
    }

    private function toBillingGroupDto(BillingGroup $group, bool $detailed = false): array
    {
        $dto = [
            'billingGroupId' => $group->id,
            'displayCode' => $group->display_code,
            'name' => $group->name,
            'displayLabel' => $group->longLabel(),
            'statusCode' => $group->status?->code,
            'statusLabel' => $group->status?->display_name,
            'coverCount' => $group->cover_count,
            'notes' => $group->notes,
            'isClosed' => $group->is_closed,
            'versionNumber' => $group->version_number,
            'openedAt' => $group->opened_at?->toIso8601String(),
            'closedAt' => $group->closed_at?->toIso8601String(),
            'zones' => $group->occupiedZones->map(fn ($z) => [
                'occupiedZoneId' => $z->id,
                'rowId' => $z->row_id,
                'rowCode' => $z->row?->row_code,
                'startSeatPairSequence' => $z->start_seat_pair_sequence,
                'endSeatPairSequence' => $z->end_seat_pair_sequence,
                'defaultDeliveryReference' => $z->defaultDeliveryLabel(),
                'deliverySeatPairId' => $z->delivery_seat_pair_id,
                'isOpen' => $z->is_open,
            ])->values()->all(),
        ];

        if ($detailed) {
            $dto['runningTotals'] = [
                'charges' => (string) $group->chargesTotal(),
                'payments' => (string) $group->paymentsTotal(),
                'balance' => (string) $group->balance(),
            ];
            $dto['recentDocuments'] = $group->billingDocuments->sortByDesc('requested_at')->take(5)->map(fn ($d) => [
                'billingDocumentId' => $d->id,
                'documentType' => $d->document_type,
                'documentStatus' => $d->document_status,
                'totalAmount' => (string) $d->total_amount,
                'printedAt' => $d->printed_at?->toIso8601String(),
                'isReprint' => $d->is_reprint,
            ])->values()->all();
        }

        return $dto;
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
