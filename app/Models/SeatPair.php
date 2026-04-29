<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatPair extends Model
{
    protected $fillable = ['row_id', 'pair_sequence', 'seat_a_id', 'seat_b_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    public function seatA(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_a_id');
    }

    public function seatB(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_b_id');
    }

    public function label(): string
    {
        return "Pair {$this->pair_sequence}";
    }
}
