<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Row;
use App\Models\SeatPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatPairController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rowId'        => ['required', 'exists:rows,id'],
            'pairSequence' => ['required', 'integer', 'min:1'],
            'seatAId'      => ['required', 'exists:seats,id'],
            'seatBId'      => ['required', 'exists:seats,id'],
        ]);

        $pair = SeatPair::create([
            'row_id'        => $validated['rowId'],
            'pair_sequence' => $validated['pairSequence'],
            'seat_a_id'     => $validated['seatAId'],
            'seat_b_id'     => $validated['seatBId'],
            'is_active'     => true,
        ]);

        return $this->success([
            'seatPairId'   => $pair->id,
            'pairSequence' => $pair->pair_sequence,
        ], status: 201);
    }

    public function update(Request $request, SeatPair $seatPair): JsonResponse
    {
        $validated = $request->validate([
            'pairSequence' => ['nullable', 'integer', 'min:1'],
            'seatAId'      => ['nullable', 'exists:seats,id'],
            'seatBId'      => ['nullable', 'exists:seats,id'],
            'isActive'     => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('pairSequence', $validated)) $update['pair_sequence'] = $validated['pairSequence'];
        if (array_key_exists('seatAId', $validated))      $update['seat_a_id'] = $validated['seatAId'];
        if (array_key_exists('seatBId', $validated))      $update['seat_b_id'] = $validated['seatBId'];
        if (array_key_exists('isActive', $validated))     $update['is_active'] = $validated['isActive'];

        $seatPair->update($update);

        return $this->success([
            'seatPairId'   => $seatPair->id,
            'pairSequence' => $seatPair->pair_sequence,
            'isActive'     => $seatPair->is_active,
        ]);
    }
}
