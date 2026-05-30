<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierSet extends Model
{
    protected $fillable = ['display_name', 'selection_mode', 'sort_order', 'is_active', 'assume_default', 'default_modifier_set_item_id'];

    protected $casts = ['is_active' => 'boolean', 'assume_default' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(ModifierSetItem::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function defaultItem(): BelongsTo
    {
        return $this->belongsTo(ModifierSetItem::class, 'default_modifier_set_item_id');
    }

    public function isSingle(): bool
    {
        return $this->selection_mode === 'single';
    }
}
