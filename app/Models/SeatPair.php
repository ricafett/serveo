<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatPair extends Model
{
    protected $fillable = ['row_id', 'pair_sequence', 'seat_a_id', 'seat_b_id', 'is_active', 'default_server_id'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleted(function (SeatPair $seatPair): void {
            // After the seat_pair row is gone (FK references removed),
            // clean up the orphaned seat records.
            $seatPair->seatA?->delete();
            $seatPair->seatB?->delete();
        });
    }

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

    /** Default server assigned by admin (venue configuration). */
    public function defaultServer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_server_id');
    }

    public function label(): string
    {
        return "Pair {$this->pair_sequence}";
    }
}
