<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\OccupiedZone;
use App\Models\Section;
use App\Models\ServiceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $session = ServiceSession::where('status', 'OPEN')
            ->orderBy('starts_at', 'desc')
            ->first();

        if (! $session) {
            return $this->error('NOT_FOUND', 'No active service session found.', status: 404);
        }

        $query = Section::with(['rows.seatPairs'])
            ->where('is_active', true);

        if ($request->filled('sectionId')) {
            $query->where('id', $request->input('sectionId'));
        }

        $sections = $query->orderBy('sort_order')->get();
        $includeClosed = filter_var($request->input('includeClosed', false), FILTER_VALIDATE_BOOLEAN);

        // Preload open zones for the session
        $zones = OccupiedZone::with(['billingGroup.status'])
            ->whereHas('billingGroup', fn ($q) => $q->where('service_session_id', $session->id))
            ->when(! $includeClosed, fn ($q) => $q->where('is_open', true))
            ->get();

        $groupedZones = $zones->groupBy(fn ($z) => $z->row_id);

        $result = $sections->map(function ($section) use ($groupedZones) {
            return [
                'sectionId'   => $section->id,
                'sectionCode' => $section->section_code,
                'rows'        => $section->rows->map(function ($row) use ($groupedZones) {
                    $rowZones = $groupedZones->get($row->id, collect());

                    return [
                        'rowId'   => $row->id,
                        'rowCode' => $row->row_code,
                        'seatPairs' => $row->seatPairs->map(function ($pair) use ($rowZones) {
                            $zone = $rowZones->first(function ($z) use ($pair) {
                                return $pair->pair_sequence >= $z->start_seat_pair_sequence
                                    && $pair->pair_sequence <= $z->end_seat_pair_sequence;
                            });

                            $data = [
                                'pairSequence' => $pair->pair_sequence,
                                'state'        => $zone ? 'OCCUPIED' : 'FREE',
                            ];

                            if ($zone) {
                                $data['billingGroupId'] = $zone->billing_group_id;
                                $data['status'] = $zone->billingGroup?->status?->code;
                            }

                            return $data;
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return $this->success([
            'sessionId' => $session->id,
            'sections'  => $result,
        ]);
    }
}
