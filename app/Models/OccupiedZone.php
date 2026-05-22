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
        'opened_at' => 'datetime',
        'released_at' => 'datetime',
        'is_open' => 'boolean',
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
            return $this->locationForSequence($this->deliverySeatPair?->pair_sequence) ?? "Pair {$this->deliverySeatPair?->pair_sequence}";
        }

        return $this->delivery_center_label
            ?: $this->locationForSequence($this->centerSequence()) ?? "Center pair {$this->centerSequence()}";
    }

    public function rangeLabel(): string
    {
        $start = $this->location();
        $end = $this->endLocation();

        if ($start === $end) {
            return $start;
        }

        return "{$start}-{$end}";
    }

    public function rangeLabelWithCount(): string
    {
        $label = $this->rangeLabel();
        $count = $this->end_seat_pair_sequence - $this->start_seat_pair_sequence + 1;

        if ($count > 1) {
            $label .= " ({$count})";
        }

        return $label;
    }

    public function endLocation(): string
    {
        $this->ensureRowSectionLoaded();

        $sectionCode = $this->row?->section?->section_code ?? '';
        $rowCode = $this->row?->row_code ?? '';
        $pair = str_pad((string) $this->end_seat_pair_sequence, 2, '0', STR_PAD_LEFT);

        return $sectionCode.$rowCode.$pair;
    }

    public function location(): string
    {
        $this->ensureRowSectionLoaded();

        $sectionCode = $this->row?->section?->section_code ?? '';
        $rowCode = $this->row?->row_code ?? '';
        $pair = str_pad((string) $this->start_seat_pair_sequence, 2, '0', STR_PAD_LEFT);

        return $sectionCode.$rowCode.$pair;
    }

    private function locationForSequence(?int $sequence): ?string
    {
        if ($sequence === null) {
            return null;
        }

        $this->ensureRowSectionLoaded();

        $sectionCode = $this->row?->section?->section_code ?? '';
        $rowCode = $this->row?->row_code ?? '';
        $pair = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return $sectionCode.$rowCode.$pair;
    }

    private function ensureRowSectionLoaded(): void
    {
        if (! $this->relationLoaded('row')) {
            $this->load('row.section');
        } elseif (! $this->row?->relationLoaded('section')) {
            $this->row->load('section');
        }
    }
}
