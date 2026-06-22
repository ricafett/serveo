<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPrintConfig extends Model
{
    public const DOC_PRODUCTION_TICKET = 'PRODUCTION_TICKET';

    public const DOC_BILL = 'BILL';

    public const DOC_SALE_VOUCHER = 'SALE_VOUCHER';

    public const DOC_SALE_RECEIPT = 'SALE_RECEIPT';

    public const DOC_SERVER_ORDER = 'SERVER_ORDER';

    public const DOC_CASHIER_TOTALS = 'CASHIER_TOTALS';

    public const DOC_SESSION_TOTALS = 'SESSION_TOTALS';

    public const DOC_INVENTORY_MOVEMENTS = 'INVENTORY_MOVEMENTS';

    protected $fillable = [
        'document_type', 'fulfillment_route',
        'group_items', 'ignore_variants', 'ignore_modifiers',
        'ignore_item_notes',
        'is_active', 'branding_header',
        'print_begin_space', 'print_end_space',
        'copies', 'trigger_cash_drawer',
    ];

    protected $casts = [
        'group_items' => 'boolean',
        'ignore_variants' => 'boolean',
        'ignore_modifiers' => 'boolean',
        'ignore_item_notes' => 'boolean',
        'is_active' => 'boolean',
        'copies' => 'integer',
        'trigger_cash_drawer' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function defaultBrandingHeader(): string
    {
        return mb_strtoupper((string) config('app.name', 'Serveo'));
    }
}
