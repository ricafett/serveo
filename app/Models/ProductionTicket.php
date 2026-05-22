<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionTicket extends Model
{
    protected $fillable = [
        'service_session_id', 'billing_group_id', 'occupied_zone_id',
        'printer_id', 'ticket_type', 'ticket_status',
        'delivery_reference_label', 'printed_at', 'requested_at',
        'reprint_of_ticket_id', 'is_void_slip', 'is_reprint',
        'created_by_user_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'printed_at' => 'datetime',
        'is_void_slip' => 'boolean',
        'is_reprint' => 'boolean',
    ];

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function occupiedZone(): BelongsTo
    {
        return $this->belongsTo(OccupiedZone::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'production_ticket_items');
    }

    public function printJobs(): MorphMany
    {
        return $this->morphMany(PrintJob::class, 'printable');
    }
}
