<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BillingDocument extends Model
{
    protected $fillable = [
        'billing_group_id', 'printer_id', 'document_type', 'document_status',
        'document_number', 'subtotal_amount', 'total_amount',
        'printed_at', 'requested_at', 'reprint_of_billing_document_id',
        'is_reprint', 'created_by_user_id',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'printed_at' => 'datetime',
        'requested_at' => 'datetime',
        'is_reprint' => 'boolean',
    ];

    public const TYPE_INTERNAL_BILL = 'INTERNAL_BILL';

    public const TYPE_BILL_REPRINT = 'BILL_REPRINT';

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function printJobs(): MorphMany
    {
        return $this->morphMany(PrintJob::class, 'printable');
    }
}
