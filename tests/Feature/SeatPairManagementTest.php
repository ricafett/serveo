<?php

use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->session = bootScenario();
    $this->admin = makeUser('ADMIN');
    $this->server = makeUser('SERVER');
    $this->row = Row::first();
});

// ─── Cascade Delete: Single ──────────────────────────────────────────

it('deletes seats when a seat pair is deleted', function () {
    $pair = $this->row->seatPairs()->first();
    $seatAId = $pair->seat_a_id;
    $seatBId = $pair->seat_b_id;

    expect(Seat::find($seatAId))->not->toBeNull();
    expect(Seat::find($seatBId))->not->toBeNull();

    $pair->delete();

    expect(SeatPair::find($pair->id))->toBeNull();
    expect(Seat::find($seatAId))->toBeNull();
    expect(Seat::find($seatBId))->toBeNull();
});

it('only deletes owned seats when a single pair is deleted', function () {
    $pair1 = $this->row->seatPairs()->where('pair_sequence', 1)->first();
    $pair2 = $this->row->seatPairs()->where('pair_sequence', 2)->first();

    $pair1SeatAId = $pair1->seat_a_id;
    $pair1SeatBId = $pair1->seat_b_id;
    $pair2SeatAId = $pair2->seat_a_id;
    $pair2SeatBId = $pair2->seat_b_id;

    $pair1->delete();

    // Pair 1 seats gone
    expect(Seat::find($pair1SeatAId))->toBeNull();
    expect(Seat::find($pair1SeatBId))->toBeNull();

    // Pair 2 seats still exist
    expect(Seat::find($pair2SeatAId))->not->toBeNull();
    expect(Seat::find($pair2SeatBId))->not->toBeNull();
    expect($pair2->exists())->toBeTrue();
});

// ─── Cascade Delete: Bulk ────────────────────────────────────────────

it('deletes seats when multiple seat pairs are individually deleted', function () {
    $pairs = $this->row->seatPairs()->take(3)->get();
    $pairIds = $pairs->pluck('id');
    $seatIds = $pairs->flatMap(fn (SeatPair $p) => [$p->seat_a_id, $p->seat_b_id]);

    expect(Seat::whereIn('id', $seatIds)->count())->toBe(6);

    // Per-model deletion (matching Filament's DeleteBulkAction behavior)
    $pairs->each->delete();

    expect(SeatPair::whereIn('id', $pairIds)->count())->toBe(0);
    expect(Seat::whereIn('id', $seatIds)->count())->toBe(0);
});

// ─── Batch Create ─────────────────────────────────────────────────────

it('batch creates seat pairs with correct auto-numbering', function () {
    $existingCount = $this->row->seatPairs()->count();
    $existingMaxSeq = $this->row->seatPairs()->max('pair_sequence');
    $startSeq = $existingMaxSeq + 1;
    $count = 3;

    DB::transaction(function () use ($startSeq, $count) {
        for ($i = 0; $i < $count; $i++) {
            $seq = $startSeq + $i;

            $seatA = Seat::create([
                'row_id' => $this->row->id,
                'seat_number' => $seq * 2 - 1,
                'sort_order' => $seq * 2 - 1,
                'is_active' => true,
            ]);

            $seatB = Seat::create([
                'row_id' => $this->row->id,
                'seat_number' => $seq * 2,
                'sort_order' => $seq * 2,
                'is_active' => true,
            ]);

            SeatPair::create([
                'row_id' => $this->row->id,
                'pair_sequence' => $seq,
                'seat_a_id' => $seatA->id,
                'seat_b_id' => $seatB->id,
                'is_active' => true,
            ]);
        }
    });

    // Verify all pairs created with correct numbering
    for ($i = 0; $i < $count; $i++) {
        $seq = $startSeq + $i;
        $pair = SeatPair::where('row_id', $this->row->id)
            ->where('pair_sequence', $seq)
            ->first();

        expect($pair)->not->toBeNull();
        expect($pair->seatA->seat_number)->toBe($seq * 2 - 1);
        expect($pair->seatB->seat_number)->toBe($seq * 2);
        expect($pair->is_active)->toBeTrue();
    }

    expect($this->row->seatPairs()->count())->toBe($existingCount + $count);
});

it('batch creates pairs with default server assigned', function () {
    $startSeq = $this->row->seatPairs()->max('pair_sequence') + 1;
    $count = 2;

    DB::transaction(function () use ($startSeq, $count) {
        for ($i = 0; $i < $count; $i++) {
            $seq = $startSeq + $i;

            $seatA = Seat::create([
                'row_id' => $this->row->id,
                'seat_number' => $seq * 2 - 1,
                'sort_order' => $seq * 2 - 1,
                'is_active' => true,
            ]);

            $seatB = Seat::create([
                'row_id' => $this->row->id,
                'seat_number' => $seq * 2,
                'sort_order' => $seq * 2,
                'is_active' => true,
            ]);

            SeatPair::create([
                'row_id' => $this->row->id,
                'pair_sequence' => $seq,
                'seat_a_id' => $seatA->id,
                'seat_b_id' => $seatB->id,
                'default_server_id' => $this->server->id,
                'is_active' => true,
            ]);
        }
    });

    $pair = SeatPair::where('row_id', $this->row->id)
        ->where('pair_sequence', $startSeq)
        ->first();

    expect($pair->default_server_id)->toBe($this->server->id);
    expect($pair->defaultServer->id)->toBe($this->server->id);
});

it('batch created seats have correct row assignment', function () {
    $startSeq = $this->row->seatPairs()->max('pair_sequence') + 1;

    DB::transaction(function () use ($startSeq) {
        $seq = $startSeq;
        $seatA = Seat::create([
            'row_id' => $this->row->id,
            'seat_number' => $seq * 2 - 1,
            'sort_order' => $seq * 2 - 1,
            'is_active' => true,
        ]);
        $seatB = Seat::create([
            'row_id' => $this->row->id,
            'seat_number' => $seq * 2,
            'sort_order' => $seq * 2,
            'is_active' => true,
        ]);

        SeatPair::create([
            'row_id' => $this->row->id,
            'pair_sequence' => $seq,
            'seat_a_id' => $seatA->id,
            'seat_b_id' => $seatB->id,
            'is_active' => true,
        ]);
    });

    $pair = SeatPair::where('row_id', $this->row->id)
        ->where('pair_sequence', $startSeq)
        ->first();

    expect($pair->seatA->row_id)->toBe($this->row->id);
    expect($pair->seatB->row_id)->toBe($this->row->id);
});

// ─── Uniqueness Constraints ───────────────────────────────────────────

it('rejects duplicate pair sequence within same row', function () {
    // Pair 1 already exists from bootScenario; try to create another
    expect(fn () => SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => 9999,      // These don't exist but the unique check on
        'seat_b_id' => 9998,      // (row_id, pair_sequence) catches it first
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('rejects seat reused in another pair within same row', function () {
    $pair = $this->row->seatPairs()->first();
    $usedSeatId = $pair->seat_a_id;

    // Find an unused seat in the row
    $unusedSeat = Seat::where('row_id', $this->row->id)
        ->whereNotIn('id', [$pair->seat_a_id, $pair->seat_b_id])
        ->first();

    // Try to reuse $usedSeatId as seat_b in a new pair
    expect(fn () => SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 999,
        'seat_a_id' => $unusedSeat->id,
        'seat_b_id' => $usedSeatId,  // Already used
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('allows same pair_sequence in different rows', function () {
    // bootScenario already has pair_sequence=1 in the default row
    // Create a new row to test cross-row uniqueness
    $section = $this->row->section;
    $row2 = Row::create([
        'section_id' => $section->id,
        'row_code' => 'ROW-TEST-2',
        'sort_order' => 99,
        'is_active' => true,
    ]);

    // Create seats for row2
    $seat1 = Seat::create(['row_id' => $row2->id, 'seat_number' => 1, 'sort_order' => 1, 'is_active' => true]);
    $seat2 = Seat::create(['row_id' => $row2->id, 'seat_number' => 2, 'sort_order' => 2, 'is_active' => true]);

    // Same pair_sequence 1, different row — should succeed
    $pair2 = SeatPair::create([
        'row_id' => $row2->id,
        'pair_sequence' => 1,
        'seat_a_id' => $seat1->id,
        'seat_b_id' => $seat2->id,
        'is_active' => true,
    ]);

    expect($pair2->row_id)->toBe($row2->id);
    expect($pair2->pair_sequence)->toBe(1);
    expect(SeatPair::where('row_id', $this->row->id)->where('pair_sequence', 1)->exists())->toBeTrue();
});

// ─── Toggle is_active ─────────────────────────────────────────────────

it('can deactivate and reactivate a seat pair', function () {
    $pair = $this->row->seatPairs()->first();

    $pair->update(['is_active' => false]);
    expect($pair->fresh()->is_active)->toBeFalse();

    $pair->update(['is_active' => true]);
    expect($pair->fresh()->is_active)->toBeTrue();
});

// ─── Default Server Assignment ────────────────────────────────────────

it('default server can be assigned and cleared on a seat pair', function () {
    $pair = $this->row->seatPairs()->first();

    $pair->update(['default_server_id' => $this->server->id]);
    expect($pair->fresh()->default_server_id)->toBe($this->server->id);

    $pair->update(['default_server_id' => null]);
    expect($pair->fresh()->default_server_id)->toBeNull();
});

// ─── Model Relationships ──────────────────────────────────────────────

it('seat pair belongs to correct row', function () {
    $pair = $this->row->seatPairs()->first();

    expect($pair->row->id)->toBe($this->row->id);
    expect($pair->row->section->id)->toBe($this->row->section->id);
});

it('seat pair references correct seat A and seat B', function () {
    $pair = $this->row->seatPairs()->first();

    expect($pair->seatA)->not->toBeNull();
    expect($pair->seatB)->not->toBeNull();
    expect($pair->seatA->row_id)->toBe($this->row->id);
    expect($pair->seatB->row_id)->toBe($this->row->id);
});

it('label returns formatted pair sequence', function () {
    $pair = $this->row->seatPairs()->where('pair_sequence', 3)->first();

    expect($pair->label())->toBe('Pair 3');
});
