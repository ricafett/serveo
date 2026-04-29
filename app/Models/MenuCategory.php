<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $fillable = ['code', 'display_name', 'route_type', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const ROUTE_KITCHEN = 'KITCHEN';
    public const ROUTE_BAR     = 'BAR';
    public const ROUTE_NONE    = 'NONE';

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
