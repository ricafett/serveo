<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterRoute extends Model
{
    protected $fillable = [
        'venue_id', 'document_type', 'fulfillment_route', 'printer_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public const DOC_PRODUCTION_TICKET = 'PRODUCTION_TICKET';

    public const DOC_BILL = 'BILL';

    public const DOC_SALE_VOUCHER = 'SALE_VOUCHER';

    public const DOC_SALE_RECEIPT = 'SALE_RECEIPT';

    public const DOC_SERVER_ORDER = 'SERVER_ORDER';

    public const DOC_CASHIER_TOTALS = 'CASHIER_TOTALS';

    public const DOC_SESSION_TOTALS = 'SESSION_TOTALS';

    public const DOC_INVENTORY_MOVEMENTS = 'INVENTORY_MOVEMENTS';

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
