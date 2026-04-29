<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['venue_id', 'section_code', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(Row::class)->orderBy('sort_order');
    }
}
