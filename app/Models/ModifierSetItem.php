<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierSetItem extends Model
{
    protected $fillable = ['modifier_set_id', 'display_name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function modifierSet(): BelongsTo
    {
        return $this->belongsTo(ModifierSet::class);
    }
}
