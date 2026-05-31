<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'service_session_id',
        'display_code',
        'sold_by_user_id',
        'subtotal_amount',
        'total_amount',
        'payment_label',
        'sold_at',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function serviceSession(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SaleDocument::class);
    }

    public function paymentsTotal(): float
    {
        return (float) $this->payments()
            ->where('is_voided', false)
            ->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->total_amount - $this->paymentsTotal(), 2);
    }
}
