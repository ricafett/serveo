<?php

namespace App\Http\Controllers\Api;

use App\Domain\Floor\OccupancyService;
use App\Http\Controllers\ApiController;
use App\Models\OccupiedZone;
use App\Models\SeatPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupiedZoneController extends ApiController
{
    public function __construct(private readonly OccupancyService $occupancyService) {}

    public function show(OccupiedZone $occupiedZone): JsonResponse
    {
        $occupiedZone->load(['billingGroup', 'row', 'deliverySeatPair']);

        return $this->success([
            'occupiedZoneId'         => $occupiedZone->id,
            'billingGroupId'         => $occupiedZone->billing_group_id,
            'rowId'                  => $occupiedZone->row_id,
            'rowCode'                => $occupiedZone->row?->row_code,
            'startSeatPairSequence'  => $occupiedZone->start_seat_pair_sequence,
            'endSeatPairSequence'    => $occupiedZone->end_seat_pair_sequence,
            'defaultDeliveryReference' => $occupiedZone->defaultDeliveryLabel(),
            'deliverySeatPairId'     => $occupiedZone->delivery_seat_pair_id,
            'isOpen'                 => $occupiedZone->is_open,
            'openedAt'               => $occupiedZone->opened_at?->toIso8601String(),
            'releasedAt'             => $occupiedZone->released_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request, OccupiedZone $occupiedZone): JsonResponse
    {
        $validated = $request->validate([
            'deliveryMode'       => ['nullable', 'string', 'in:CENTER,SPECIFIC_SEAT_PAIR'],
            'deliverySeatPairId' => ['nullable', 'exists:seat_pairs,id'],
            'releasedAt'         => ['nullable', 'date'],
            'isOpen'             => ['nullable', 'boolean'],
        ]);

        $update = [];

        if (array_key_exists('deliveryMode', $validated)) {
            $update['default_delivery_mode'] = $validated['deliveryMode'];
        }

        if (array_key_exists('deliverySeatPairId', $validated)) {
            if ($validated['deliverySeatPairId']) {
                $pair = SeatPair::find($validated['deliverySeatPairId']);
                if ($pair->row_id !== $occupiedZone->row_id) {
                    return $this->error('INVALID_DELIVERY_TARGET', 'Delivery seat pair must be in the same row.', status: 400);
                }
                if ($pair->pair_sequence < $occupiedZone->start_seat_pair_sequence
                    || $pair->pair_sequence > $occupiedZone->end_seat_pair_sequence) {
                    return $this->error('INVALID_DELIVERY_TARGET', 'Delivery seat pair must fall inside the zone range.', status: 400);
                }
            }
            $update['delivery_seat_pair_id'] = $validated['deliverySeatPairId'];
        }

        if (array_key_exists('releasedAt', $validated) || array_key_exists('isOpen', $validated)) {
            if (($validated['isOpen'] ?? true) === false || $validated['releasedAt']) {
                $this->occupancyService->releaseZone($occupiedZone, $request->user());
                return $this->success([
                    'occupiedZoneId' => $occupiedZone->refresh()->id,
                    'isOpen'         => $occupiedZone->is_open,
                    'releasedAt'     => $occupiedZone->released_at?->toIso8601String(),
                ]);
            }
        }

        if (! empty($update)) {
            $occupiedZone->update($update);
        }

        return $this->success([
            'occupiedZoneId'         => $occupiedZone->refresh()->id,
            'defaultDeliveryReference' => $occupiedZone->defaultDeliveryLabel(),
            'deliverySeatPairId'     => $occupiedZone->delivery_seat_pair_id,
            'isOpen'                 => $occupiedZone->is_open,
        ]);
    }
}
