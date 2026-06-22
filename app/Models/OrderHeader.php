<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrderHeader extends Model
{
    protected $fillable = [
        'billing_group_id', 'occupied_zone_id', 'ordered_by_user_id',
        'ordered_at', 'submission_status', 'notes', 'idempotency_key',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
    ];

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function occupiedZone(): BelongsTo
    {
        return $this->belongsTo(OccupiedZone::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function printJobs(): MorphMany
    {
        return $this->morphMany(PrintJob::class, 'printable');
    }
}
