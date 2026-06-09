<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    public const TYPE_CASH_IN = 'CASH_IN';
    public const TYPE_CASH_OUT = 'CASH_OUT';

    protected $fillable = [
        'service_session_id', 'cashier_user_id', 'movement_type',
        'amount', 'label', 'notes', 'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function serviceSession(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }
}
