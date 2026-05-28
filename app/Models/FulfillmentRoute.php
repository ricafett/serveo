<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentRoute extends Model
{
    protected $fillable = ['code', 'display_name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const ROUTE_KITCHEN = 'KITCHEN';

    public const ROUTE_BAR = 'BAR';

    public const ROUTE_NONE = 'NONE';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
