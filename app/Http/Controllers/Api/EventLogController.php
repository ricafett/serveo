<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serviceSessionId' => ['nullable', 'integer', 'exists:service_sessions,id'],
            'billingGroupId'   => ['nullable', 'integer', 'exists:billing_groups,id'],
            'eventType'        => ['nullable', 'string'],
            'from'             => ['nullable', 'date'],
            'to'               => ['nullable', 'date'],
            'page'             => ['nullable', 'integer', 'min:1'],
            'pageSize'         => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditEvent::with('actor')
            ->orderBy('event_time', 'desc');

        if (! empty($validated['serviceSessionId'])) {
            $query->where('service_session_id', $validated['serviceSessionId']);
        }
        if (! empty($validated['billingGroupId'])) {
            $query->where('billing_group_id', $validated['billingGroupId']);
        }
        if (! empty($validated['eventType'])) {
            $query->where('event_type', $validated['eventType']);
        }
        if (! empty($validated['from'])) {
            $query->whereDate('event_time', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('event_time', '<=', $validated['to']);
        }

        $pageSize = $validated['pageSize'] ?? 25;
        $page = $validated['page'] ?? 1;

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        $data = $paginator->map(fn ($event) => [
            'eventId'          => $event->id,
            'eventType'        => $event->event_type,
            'eventTime'        => $event->event_time?->toIso8601String(),
            'actor'            => $event->actor ? [
                'userId'      => $event->actor->id,
                'displayName' => $event->actor->name,
            ] : null,
            'billingGroupId'   => $event->billing_group_id,
            'occupiedZoneId'   => $event->occupied_zone_id,
            'summary'          => $event->summary,
        ])->all();

        return $this->success($data, [
            'currentPage' => $paginator->currentPage(),
            'lastPage'    => $paginator->lastPage(),
            'perPage'     => $paginator->perPage(),
            'total'       => $paginator->total(),
        ]);
    }

    public function show(AuditEvent $auditEvent): JsonResponse
    {
        $auditEvent->load('actor');

        return $this->success([
            'eventId'          => $auditEvent->id,
            'eventType'        => $auditEvent->event_type,
            'eventTime'        => $auditEvent->event_time?->toIso8601String(),
            'actor'            => $auditEvent->actor ? [
                'userId'      => $auditEvent->actor->id,
                'displayName' => $auditEvent->actor->name,
            ] : null,
            'billingGroupId'   => $auditEvent->billing_group_id,
            'occupiedZoneId'   => $auditEvent->occupied_zone_id,
            'orderHeaderId'    => $auditEvent->order_header_id,
            'orderItemId'      => $auditEvent->order_item_id,
            'productionTicketId'  => $auditEvent->production_ticket_id,
            'billingDocumentId'   => $auditEvent->billing_document_id,
            'paymentRecordId'     => $auditEvent->payment_record_id,
            'summary'          => $auditEvent->summary,
            'payload'          => $auditEvent->payload_json,
        ]);
    }
}
