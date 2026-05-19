<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingGroup extends Model
{
    protected $fillable = [
        'service_session_id', 'display_code', 'billing_status_id',
        'cover_count', 'notes', 'opened_by_user_id',
        'opened_at', 'closed_at', 'is_closed', 'version_number',
    ];

    protected $casts = [
        'opened_at'      => 'datetime',
        'closed_at'      => 'datetime',
        'is_closed'      => 'boolean',
    ];

    public function serviceSession(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(BillingStatus::class, 'billing_status_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function occupiedZones(): HasMany
    {
        return $this->hasMany(OccupiedZone::class);
    }

    public function openOccupiedZones(): HasMany
    {
        return $this->hasMany(OccupiedZone::class)->where('is_open', true);
    }

    public function orderHeaders(): HasMany
    {
        return $this->hasMany(OrderHeader::class);
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(BillingDocument::class);
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function productionTickets(): HasMany
    {
        return $this->hasMany(ProductionTicket::class);
    }

    /** All unique servers assigned to any open zone of this group. */
    public function assignedServers(): \Illuminate\Support\Collection
    {
        return $this->occupiedZones
            ->pluck('server')
            ->filter()
            ->unique('id');
    }

    /** Sum of non-voided order item subtotals. */
    public function chargesTotal(): float
    {
        return (float) $this->orderHeaders()
            ->with('items')
            ->get()
            ->flatMap->items
            ->whereNull('voided_at')
            ->sum('line_subtotal');
    }

    public function paymentsTotal(): float
    {
        return (float) $this->paymentRecords()
            ->where('is_voided', false)
            ->sum('amount');
    }

    public function balance(): float
    {
        return round($this->chargesTotal() - $this->paymentsTotal(), 2);
    }
}
