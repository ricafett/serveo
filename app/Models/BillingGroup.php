<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BillingGroup extends Model
{
    protected $fillable = [
        'service_session_id', 'display_code', 'name', 'billing_status_id',
        'cover_count', 'notes', 'opened_by_user_id',
        'opened_at', 'closed_at', 'is_closed', 'version_number',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_closed' => 'boolean',
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
        return $this->hasMany(OrderHeader::class)
            ->orderBy('ordered_at', 'desc')
            ->orderBy('id', 'desc');
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
    public function assignedServers(): Collection
    {
        return $this->occupiedZones
            ->pluck('server')
            ->filter()
            ->unique('id');
    }

    /** Servers who favorited this group. */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'billing_group_favorites')
            ->withPivot('is_manual')
            ->withTimestamps();
    }

    /** Check if a specific user has favorited this group. */
    public function isFavoritedBy(User $user): bool
    {
        return $this->favoritedBy()->where('user_id', $user->id)->exists();
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

    public function longLabel(): string
    {
        $label = $this->name ?: $this->display_code;

        $zones = $this->relationLoaded('occupiedZones')
            ? $this->occupiedZones
            : $this->occupiedZones()->with('row.section')->get();

        $locations = $zones
            ->map(fn ($z) => $z->location())
            ->filter()
            ->values();

        if ($locations->isNotEmpty()) {
            $label .= ' ('.$locations->join(', ').')';
        }

        return $label;
    }
}
