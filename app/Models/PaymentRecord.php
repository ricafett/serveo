<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRecord extends Model
{
    protected $fillable = [
        'billing_group_id', 'recorded_by_user_id', 'recorded_at',
        'amount', 'payment_label', 'notes', 'is_voided',
        'voided_at', 'voided_by_user_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'voided_at' => 'datetime',
        'amount' => 'decimal:2',
        'is_voided' => 'boolean',
    ];

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
