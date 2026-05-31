<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaleDocument extends Model
{
    public const TYPE_VOUCHER = 'SALE_VOUCHER';

    public const TYPE_RECEIPT = 'SALE_RECEIPT';

    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'printer_id',
        'document_type',
        'document_status',
        'document_number',
        'quantity',
        'printed_at',
        'requested_at',
        'reprint_of_sale_document_id',
        'is_reprint',
        'created_by_user_id',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'requested_at' => 'datetime',
        'is_reprint' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function printJobs(): MorphMany
    {
        return $this->morphMany(PrintJob::class, 'printable');
    }
}
