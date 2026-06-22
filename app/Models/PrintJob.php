<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrintJob extends Model
{
    protected $fillable = [
        'job_kind', 'printable_type', 'printable_id', 'printer_id',
        'status', 'attempts', 'max_attempts', 'payload', 'last_error',
        'next_attempt_at', 'completed_at', 'requested_by_user_id',
        'locale',
    ];

    protected $casts = [
        'next_attempt_at' => 'datetime',
        'completed_at' => 'datetime',
        'payload' => 'array',
    ];

    public const KIND_PRODUCTION_TICKET = 'PRODUCTION_TICKET';

    public const KIND_BILL = 'BILL';

    public const KIND_SALE_VOUCHER = 'SALE_VOUCHER';

    public const KIND_SALE_RECEIPT = 'SALE_RECEIPT';

    public const KIND_SERVER_ORDER = 'SERVER_ORDER';

    public const KIND_SALE_VOUCHER_BATCH = 'SALE_VOUCHER_BATCH';

    public const KIND_CASHIER_TOTALS = 'CASHIER_TOTALS';

    public const KIND_SESSION_TOTALS = 'SESSION_TOTALS';

    public const KIND_INVENTORY_MOVEMENTS = 'INVENTORY_MOVEMENTS';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_PRINTED = 'PRINTED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELED = 'CANCELED';

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
