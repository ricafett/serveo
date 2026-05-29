<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPrintConfig extends Model
{
    protected $fillable = [
        'document_type', 'fulfillment_route',
        'group_items', 'ignore_variants', 'ignore_modifiers',
        'is_active',
    ];

    protected $casts = [
        'group_items' => 'boolean',
        'ignore_variants' => 'boolean',
        'ignore_modifiers' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
