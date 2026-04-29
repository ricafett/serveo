<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Row extends Model
{
    protected $table = 'rows';

    protected $fillable = ['section_id', 'row_code', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class)->orderBy('sort_order');
    }

    public function seatPairs(): HasMany
    {
        return $this->hasMany(SeatPair::class)->orderBy('pair_sequence');
    }

    public function occupiedZones(): HasMany
    {
        return $this->hasMany(OccupiedZone::class);
    }
}
