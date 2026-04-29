<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSession extends Model
{
    protected $fillable = [
        'venue_id', 'session_type', 'session_label',
        'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function billingGroups(): HasMany
    {
        return $this->hasMany(BillingGroup::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }
}
