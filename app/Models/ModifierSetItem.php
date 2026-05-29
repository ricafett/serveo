<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierSetItem extends Model
{
    protected $fillable = ['modifier_set_id', 'display_name', 'sort_order', 'is_active', 'is_default'];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->is_default) {
                static::where('modifier_set_id', $item->modifier_set_id)
                    ->where('id', '!=', $item->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function modifierSet(): BelongsTo
    {
        return $this->belongsTo(ModifierSet::class);
    }
}
