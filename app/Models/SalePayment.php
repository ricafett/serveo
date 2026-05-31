<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'recorded_by_user_id',
        'recorded_at',
        'amount',
        'payment_label',
        'notes',
        'is_voided',
        'voided_at',
        'voided_by_user_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'voided_at' => 'datetime',
        'amount' => 'decimal:2',
        'is_voided' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
