<?php

namespace App\Domain\Audit;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit log facade. All meaningful operational mutations should
 * record one event with a stable EventType + structured payload.
 */
class Audit
{
    public const TYPES = [
        'BILLING_GROUP_OPENED',
        'BILLING_GROUP_STATUS_CHANGED',
        'BILLING_GROUP_CLOSED',
        'BILLING_GROUP_REOPENED',
        'OCCUPIED_ZONE_OPENED',
        'OCCUPIED_ZONE_RELEASED',
        'ORDER_SUBMITTED',
        'ORDER_ITEM_VOIDED',
        'ORDER_ITEMS_VOIDED',
        'ORDER_VOIDED',
        'ORDER_ITEM_DELIVERED',
        'ORDER_ITEM_UNDELIVERED',
        'PRODUCTION_TICKET_QUEUED',
        'PRODUCTION_TICKET_PRINTED',
        'PRODUCTION_TICKET_FAILED',
        'PRODUCTION_TICKET_REPRINTED',
        'BILL_GENERATED',
        'BILL_PRINTED',
        'BILL_REPRINTED',
        'PAYMENT_RECORDED',
        'PAYMENT_VOIDED',
        'SALE_COMPLETED',
        'SALE_PAYMENT_RECORDED',
        'SALE_VOUCHER_QUEUED',
        'SALE_VOUCHER_PRINTED',
        'SALE_RECEIPT_QUEUED',
        'SALE_RECEIPT_PRINTED',
        'PRINT_JOB_RETRIED',
        'PRINT_JOB_MAX_ATTEMPTS',
        'EXPORT_REQUESTED',
        'EXPORT_COMPLETED',
        'EXPORT_FAILED',
        'BACKUP_REQUESTED',
        'BACKUP_COMPLETED',
        'BACKUP_FAILED',
        'BACKUP_RESTORE_STARTED',
        'BACKUP_RESTORE_COMPLETED',
        'BACKUP_RESTORE_FAILED',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $relations  Optional foreign keys (billing_group_id, etc.)
     */
    public static function record(string $eventType, string $summary, array $payload = [], array $relations = []): AuditEvent
    {
        return AuditEvent::create(array_merge([
            'service_session_id' => $relations['service_session_id'] ?? null,
            'event_type' => $eventType,
            'event_time' => now(),
            'actor_user_id' => $relations['actor_user_id'] ?? Auth::id(),
            'billing_group_id' => $relations['billing_group_id'] ?? null,
            'occupied_zone_id' => $relations['occupied_zone_id'] ?? null,
            'order_header_id' => $relations['order_header_id'] ?? null,
            'order_item_id' => $relations['order_item_id'] ?? null,
            'production_ticket_id' => $relations['production_ticket_id'] ?? null,
            'billing_document_id' => $relations['billing_document_id'] ?? null,
            'payment_record_id' => $relations['payment_record_id'] ?? null,
            'sale_id' => $relations['sale_id'] ?? null,
            'sale_payment_id' => $relations['sale_payment_id'] ?? null,
            'sale_document_id' => $relations['sale_document_id'] ?? null,
            'accounting_export_id' => $relations['accounting_export_id'] ?? null,
            'entity_type' => $relations['entity_type'] ?? null,
            'entity_id' => $relations['entity_id'] ?? null,
            'summary' => $summary,
            'payload_json' => $payload,
            'created_at' => now(),
        ]));
    }

    public static function forModel(Model $model, string $eventType, string $summary, array $payload = []): AuditEvent
    {
        return self::record($eventType, $summary, $payload, [
            'entity_type' => $model::class,
            'entity_id' => $model->getKey(),
        ]);
    }
}
