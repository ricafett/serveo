<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Livewire\Floor\FloorIndex;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up roles/permissions and baseline data
    bootScenario();
    // Close the baseline session so we control exactly which session is open
    ServiceSession::where('status', 'OPEN')->update(['status' => 'CLOSED']);

    // Re-open just our own venue and session
    $this->venue = Venue::firstOrFail();
    $this->session = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test Floor',
        'starts_at' => now()->subHour(),
        'status' => 'OPEN',
    ]);

    $this->section = Section::create([
        'venue_id' => $this->venue->id, 'section_code' => 'TEST',
        'name' => 'Test Section', 'sort_order' => 99, 'is_active' => true,
    ]);
    $this->row = Row::create([
        'section_id' => $this->section->id, 'row_code' => 'T1',
        'sort_order' => 1, 'is_active' => true,
    ]);

    for ($i = 1; $i <= 10; $i++) {
        SeatPair::create([
            'row_id' => $this->row->id, 'pair_sequence' => $i,
            'seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2,
            'is_active' => true,
        ]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');
});

it('does not persist an orphan billing group when zone overlaps via Livewire floor modal', function () {
    $this->actingAs($this->server);

    // First, create a billing group with a zone (1-3) directly
    $g1 = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($g1, $this->row, 1, 3, $this->server);

    $countBefore = BillingGroup::count();
    $zonesBefore = OccupiedZone::count();

    // Try to create another group with an overlapping range (2-4) via Livewire
    Livewire::test(FloorIndex::class)
        ->set('name', 'Overlapping Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneRowId', $this->row->id)
        ->set('zoneStartSeq', 2)
        ->set('zoneEndSeq', 4)
        ->set('zoneSeatCount', 3)
        ->call('createBillingGroup')
        ->assertSee('Zone overlap');

    // No new billing group or zone should be persisted
    expect(BillingGroup::count())->toBe($countBefore);
    expect(OccupiedZone::count())->toBe($zonesBefore);
});

it('successfully creates a billing group with an occupied zone via Livewire when no overlap', function () {
    $this->actingAs($this->server);

    $countBefore = BillingGroup::count();
    $zonesBefore = OccupiedZone::count();

    Livewire::test(FloorIndex::class)
        ->set('name', 'Test Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneRowId', $this->row->id)
        ->set('zoneStartSeq', 1)
        ->set('zoneEndSeq', 3)
        ->set('zoneSeatCount', 3)
        ->call('createBillingGroup');

    expect(BillingGroup::count())->toBe($countBefore + 1);
    expect(OccupiedZone::count())->toBe($zonesBefore + 1);
});
