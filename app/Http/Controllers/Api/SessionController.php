<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\BillingStatus;
use App\Models\ServiceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends ApiController
{
    public function current(Request $request): JsonResponse
    {
        $session = ServiceSession::with('venue')
            ->where('status', 'OPEN')
            ->orderBy('starts_at', 'desc')
            ->first();

        if (! $session) {
            return $this->error('NOT_FOUND', 'No active service session found.', status: 404);
        }

        $statuses = BillingStatus::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'display_name']);

        return $this->success([
            'sessionId' => $session->id,
            'sessionType' => $session->session_type,
            'sessionLabel' => $session->session_label,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'venue' => [
                'venueId' => $session->venue?->id,
                'venueCode' => $session->venue?->venue_code,
                'name' => $session->venue?->name,
            ],
            'activeBillingStatuses' => $statuses->map(fn ($s) => [
                'statusId' => $s->id,
                'code' => $s->code,
                'displayName' => $s->display_name,
            ]),
            'effectiveLanguageOptions' => ['pt-PT', 'en-US'],
        ]);
    }
}
