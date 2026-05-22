<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_category_id', 'sku', 'code', 'display_name', 'short_name',
        'unit_price', 'tax_code', 'is_active', 'modifier_set_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function modifierSet(): BelongsTo
    {
        return $this->belongsTo(ModifierSet::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->where('is_active', true);
    }

    public function hasVariants(): bool
    {
        return $this->activeVariants()->count() > 0;
    }

    public function hasModifiers(): bool
    {
        return $this->modifierSet()->exists();
    }
}
