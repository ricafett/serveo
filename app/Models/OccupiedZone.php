<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccupiedZone extends Model
{
    protected $fillable = [
        'billing_group_id', 'row_id',
        'start_seat_pair_sequence', 'end_seat_pair_sequence',
        'default_delivery_mode', 'delivery_center_label',
        'delivery_seat_pair_id',
        'opened_at', 'released_at', 'is_open',
        'created_by_user_id', 'server_id',
    ];

    protected $casts = [
        'opened_at'   => 'datetime',
        'released_at' => 'datetime',
        'is_open'     => 'boolean',
    ];

    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    public function deliverySeatPair(): BelongsTo
    {
        return $this->belongsTo(SeatPair::class, 'delivery_seat_pair_id');
    }

    /** Server actively working this zone during service. */
    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function centerSequence(): int
    {
        return (int) floor(
            ($this->start_seat_pair_sequence + $this->end_seat_pair_sequence) / 2
        );
    }

    public function defaultDeliveryLabel(): string
    {
        if ($this->default_delivery_mode === 'SPECIFIC' && $this->delivery_seat_pair_id) {
            return "Pair {$this->deliverySeatPair?->pair_sequence}";
        }

        return $this->delivery_center_label
            ?: "Center pair {$this->centerSequence()}";
    }

    public function rangeLabel(): string
    {
        $row = $this->row?->row_code ?? "row {$this->row_id}";
        return "{$row} pairs {$this->start_seat_pair_sequence}-{$this->end_seat_pair_sequence}";
    }
}
