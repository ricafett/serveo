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
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $session) {
            if (! $session->exists) {
                return;
            }

            $originalStatus = $session->getOriginal('status');
            $newStatus = $session->status;

            if ($originalStatus === 'OPEN' && $newStatus !== 'OPEN') {
                $hasOpenGroups = $session->billingGroups()->where('is_closed', false)->exists();

                if ($hasOpenGroups) {
                    throw new \RuntimeException(
                        __('app.session_has_open_groups')
                    );
                }
            }
        });
    }

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
