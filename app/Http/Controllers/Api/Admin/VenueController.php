<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class VenueController extends ApiController
{
    public function show(Venue $venue): JsonResponse
    {
        return $this->success([
            'venueId'   => $venue->id,
            'venueCode' => $venue->venue_code,
            'name'      => $venue->name,
            'isActive'  => $venue->is_active,
        ]);
    }

    public function layout(Venue $venue): JsonResponse
    {
        $venue->load(['sections.rows.seatPairs']);

        return $this->success([
            'venueId'   => $venue->id,
            'sections'  => $venue->sections->map(fn ($section) => [
                'sectionId'   => $section->id,
                'sectionCode' => $section->section_code,
                'name'        => $section->name,
                'rows'        => $section->rows->map(fn ($row) => [
                    'rowId'   => $row->id,
                    'rowCode' => $row->row_code,
                    'seatPairs' => $row->seatPairs->map(fn ($pair) => [
                        'seatPairId'   => $pair->id,
                        'pairSequence' => $pair->pair_sequence,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
