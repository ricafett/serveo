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
    public const DOC_BILL              = 'BILL';
    public const DOC_VOID_SLIP         = 'VOID_SLIP';

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
