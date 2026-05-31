<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_session_id', 'event_type', 'event_time', 'actor_user_id',
        'billing_group_id', 'occupied_zone_id', 'order_header_id', 'order_item_id',
        'production_ticket_id', 'billing_document_id', 'payment_record_id',
        'sale_id', 'sale_payment_id', 'sale_document_id',
        'accounting_export_id', 'entity_type', 'entity_id',
        'summary', 'payload_json', 'created_at',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'created_at' => 'datetime',
        'payload_json' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
