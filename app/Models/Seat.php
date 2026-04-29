<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    protected $fillable = ['row_id', 'seat_number', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }
}
