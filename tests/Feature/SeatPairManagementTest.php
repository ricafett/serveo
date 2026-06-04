<?php

use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\User;
use App\Models\Venue;

beforeEach(function () {
    $this->session = bootScenario();

    $this->admin = makeUser('ADMIN', 'admin-seatpair-test');
    $this->server = makeUser('SERVER', 'server-seatpair-test');

    $this->venue = Venue::first();

    $this->section = Section::create([
        'venue_id' => $this->venue->id,
        'section_code' => 'STP',
        'name' => 'Seat Pair Test',
        'sort_order' => 99,
        'is_active' => true,
    ]);

    $this->row = Row::create([
        'section_id' => $this->section->id,
        'row_code' => 'ST1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Create 6 seats for this row
    $this->seatIds = [];
    for ($n = 1; $n <= 6; $n++) {
        $seat = Seat::create([
            'row_id' => $this->row->id,
            'seat_number' => $n,
            'sort_order' => $n,
            'is_active' => true,
        ]);
        $this->seatIds[$n] = $seat->id;
    }

    // Create a second row for cross-row tests
    $this->row2 = Row::create([
        'section_id' => $this->section->id,
        'row_code' => 'ST2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->row2SeatIds = [];
    for ($n = 1; $n <= 4; $n++) {
        $seat = Seat::create([
            'row_id' => $this->row2->id,
            'seat_number' => $n,
            'sort_order' => $n,
            'is_active' => true,
        ]);
        $this->row2SeatIds[$n] = $seat->id;
    }
});

// ─── Creation ────────────────────────────────────────────────────────────────

it('admin can create a seat pair via API', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/seat-pairs', [
            'rowId' => $this->row->id,
            'pairSequence' => 1,
            'seatAId' => $this->seatIds[1],
            'seatBId' => $this->seatIds[2],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.seatPairId', fn ($id) => $id > 0)
        ->assertJsonPath('data.pairSequence', 1);

    $pair = SeatPair::find($response->json('data.seatPairId'));
    expect($pair->row_id)->toBe($this->row->id)
        ->and($pair->pair_sequence)->toBe(1)
        ->and($pair->seat_a_id)->toBe($this->seatIds[1])
        ->and($pair->seat_b_id)->toBe($this->seatIds[2])
        ->and($pair->is_active)->toBeTrue();
});

it('admin can update a seat pair via API', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->patchJson("/api/v1/admin/seat-pairs/{$pair->id}", [
            'pairSequence' => 5,
            'isActive' => false,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pairSequence', 5)
        ->assertJsonPath('data.isActive', false);

    $pair->refresh();
    expect($pair->pair_sequence)->toBe(5)
        ->and($pair->is_active)->toBeFalse();
});

// ─── Permission ──────────────────────────────────────────────────────────────

it('non-admin cannot create a seat pair', function () {
    $response = $this->actingAs($this->server)
        ->postJson('/api/v1/admin/seat-pairs', [
            'rowId' => $this->row->id,
            'pairSequence' => 1,
            'seatAId' => $this->seatIds[1],
            'seatBId' => $this->seatIds[2],
        ]);

    $response->assertStatus(403);
});

it('non-admin cannot update a seat pair', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->server)
        ->patchJson("/api/v1/admin/seat-pairs/{$pair->id}", [
            'isActive' => false,
        ]);

    $response->assertStatus(403);
});

// ─── Integrity ───────────────────────────────────────────────────────────────

it('rejects duplicate pair sequence within same row', function () {
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Try to create another pair with same sequence in same row
    // The DB unique constraint on (row_id, pair_sequence) should reject this
    expect(fn () => SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[3],
        'seat_b_id' => $this->seatIds[4],
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects seat_a reused in another pair within same row', function () {
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Try to use seat 1 as seat_a in another pair — should fail
    expect(fn () => SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 2,
        'seat_a_id' => $this->seatIds[1],  // Already used in pair 1
        'seat_b_id' => $this->seatIds[3],
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects seat_b reused in another pair within same row', function () {
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Try to use seat 2 as seat_b in another pair — should fail
    expect(fn () => SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 2,
        'seat_a_id' => $this->seatIds[3],
        'seat_b_id' => $this->seatIds[2],  // Already used in pair 1
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows same pair_sequence in different rows', function () {
    // Pair 1 in row 1
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Pair 1 in row 2 — should succeed (different row)
    $pair2 = SeatPair::create([
        'row_id' => $this->row2->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->row2SeatIds[1],
        'seat_b_id' => $this->row2SeatIds[2],
        'is_active' => true,
    ]);

    expect($pair2->row_id)->toBe($this->row2->id)
        ->and($pair2->pair_sequence)->toBe(1);
});

it('validates required fields on seat pair creation', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/seat-pairs', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rowId', 'pairSequence', 'seatAId', 'seatBId']);
});

// ─── Toggle is_active ────────────────────────────────────────────────────────

it('can deactivate and reactivate a seat pair', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Deactivate
    $pair->update(['is_active' => false]);
    expect($pair->fresh()->is_active)->toBeFalse();

    // Reactivate
    $pair->update(['is_active' => true]);
    expect($pair->fresh()->is_active)->toBeTrue();
});

it('default server can be assigned and cleared', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    // Assign default server
    $pair->update(['default_server_id' => $this->server->id]);
    expect($pair->fresh()->default_server_id)->toBe($this->server->id);

    // Clear default server
    $pair->update(['default_server_id' => null]);
    expect($pair->fresh()->default_server_id)->toBeNull();
});

// ─── Model Relationships ─────────────────────────────────────────────────────

it('seat pair belongs to correct row', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    expect($pair->row->id)->toBe($this->row->id)
        ->and($pair->row->section->id)->toBe($this->section->id);
});

it('seat pair references correct seat A and seat B', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    expect($pair->seatA->seat_number)->toBe(1)
        ->and($pair->seatB->seat_number)->toBe(2)
        ->and($pair->seatA->row_id)->toBe($this->row->id)
        ->and($pair->seatB->row_id)->toBe($this->row->id);
});

it('label returns pair sequence', function () {
    $pair = SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 3,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);

    expect($pair->label())->toBe('Pair 3');
});

it('row has correct seat pair count', function () {
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 1,
        'seat_a_id' => $this->seatIds[1],
        'seat_b_id' => $this->seatIds[2],
        'is_active' => true,
    ]);
    SeatPair::create([
        'row_id' => $this->row->id,
        'pair_sequence' => 2,
        'seat_a_id' => $this->seatIds[3],
        'seat_b_id' => $this->seatIds[4],
        'is_active' => true,
    ]);

    expect($this->row->fresh()->seatPairs()->count())->toBe(2);
    expect($this->row2->fresh()->seatPairs()->count())->toBe(0);
});
