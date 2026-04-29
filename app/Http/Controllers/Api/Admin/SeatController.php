<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Row;
use App\Models\Seat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rowId'      => ['required', 'exists:rows,id'],
            'seatNumber' => ['required', 'integer', 'min:1'],
            'sortOrder'  => ['nullable', 'integer'],
        ]);

        $seat = Seat::create([
            'row_id'      => $validated['rowId'],
            'seat_number' => $validated['seatNumber'],
            'sort_order'  => $validated['sortOrder'] ?? $validated['seatNumber'],
            'is_active'   => true,
        ]);

        return $this->success([
            'seatId'     => $seat->id,
            'seatNumber' => $seat->seat_number,
        ], status: 201);
    }

    public function update(Request $request, Seat $seat): JsonResponse
    {
        $validated = $request->validate([
            'seatNumber' => ['nullable', 'integer', 'min:1'],
            'sortOrder'  => ['nullable', 'integer'],
            'isActive'   => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('seatNumber', $validated)) $update['seat_number'] = $validated['seatNumber'];
        if (array_key_exists('sortOrder', $validated))  $update['sort_order'] = $validated['sortOrder'];
        if (array_key_exists('isActive', $validated))   $update['is_active'] = $validated['isActive'];

        $seat->update($update);

        return $this->success([
            'seatId'     => $seat->id,
            'seatNumber' => $seat->seat_number,
            'isActive'   => $seat->is_active,
        ]);
    }
}
