<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Livewire\Floor\FloorIndex;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\Seat;
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
        $seatA = Seat::create([
            'row_id' => $this->row->id,
            'seat_number' => $i * 2 - 1,
            'sort_order' => $i * 2 - 1,
            'is_active' => true,
        ]);
        $seatB = Seat::create([
            'row_id' => $this->row->id,
            'seat_number' => $i * 2,
            'sort_order' => $i * 2,
            'is_active' => true,
        ]);
        SeatPair::create([
            'row_id' => $this->row->id, 'pair_sequence' => $i,
            'seat_a_id' => $seatA->id, 'seat_b_id' => $seatB->id,
            'is_active' => true,
        ]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');

    $this->cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $this->cashier->assignRole('CASHIER');

    $this->server2 = User::factory()->create(['username' => 'assignedserver', 'is_active' => true]);
    $this->server2->assignRole('SERVER');

    SeatPair::where('row_id', $this->row->id)->where('pair_sequence', 1)->update(['default_server_id' => $this->server2->id]);
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

it('rejects floor modal submission when a once-free range is claimed before submit', function () {
    $this->actingAs($this->server);

    $component = Livewire::test(FloorIndex::class)
        ->assertSee('TESTT101');

    $existingGroup = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($existingGroup, $this->row, 1, 3, $this->server);

    $countBefore = BillingGroup::count();
    $zonesBefore = OccupiedZone::count();

    $component
        ->call('selectPair', $this->row->id, 1)
        ->set('name', 'Stale Snapshot Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneSeatCount', 3)
        ->set('zoneEndSeq', 3)
        ->call('createBillingGroup')
        ->assertSee('Zone overlap');

    expect(BillingGroup::count())->toBe($countBefore);
    expect(OccupiedZone::count())->toBe($zonesBefore);
});

it('allows floor modal creation when only an old session has an overlapping open zone', function () {
    $this->actingAs($this->server);

    $closedSession = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Closed session with lingering zone',
        'starts_at' => now()->subDay(),
        'status' => 'CLOSED',
    ]);

    $closedGroup = createBillingGroup($closedSession, $this->server);
    OccupiedZone::create([
        'billing_group_id' => $closedGroup->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now()->subDay(),
        'is_open' => true,
        'created_by_user_id' => $closedGroup->opened_by_user_id,
        'server_id' => $this->server->id,
    ]);

    $countBefore = BillingGroup::count();
    $zonesBefore = OccupiedZone::count();

    Livewire::test(FloorIndex::class)
        ->assertSee('TESTT101')
        ->call('selectPair', $this->row->id, 1)
        ->set('name', 'Current Session Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneSeatCount', 3)
        ->set('zoneEndSeq', 3)
        ->call('createBillingGroup');

    expect(BillingGroup::count())->toBe($countBefore + 1);
    expect(OccupiedZone::count())->toBe($zonesBefore + 1);
});

it('rejects range expansion when another group claims part of the expanded range before submit', function () {
    $this->actingAs($this->server);

    $component = Livewire::test(FloorIndex::class)
        ->call('selectPair', $this->row->id, 4)
        ->assertSet('zoneStartSeq', 4)
        ->assertSet('zoneEndSeq', 4);

    $existingGroup = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($existingGroup, $this->row, 5, 6, $this->server);

    $countBefore = BillingGroup::count();
    $zonesBefore = OccupiedZone::count();

    $component
        ->set('name', 'Expanded Stale Range Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneSeatCount', 3)
        ->set('zoneEndSeq', 6)
        ->call('createBillingGroup')
        ->assertSee('Zone overlap');

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

it('requires assigned server when cashier creates a billing group', function () {
    $this->actingAs($this->cashier);

    Livewire::test(FloorIndex::class)
        ->call('selectPair', $this->row->id, 1)
        ->set('name', 'Cashier Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneEndSeq', 3)
        ->set('zoneSeatCount', 3)
        ->set('assignedServerId', null)
        ->call('createBillingGroup')
        ->assertHasErrors(['assignedServerId']);
});

it('cashier can create a billing group with an explicitly assigned zone server', function () {
    $this->actingAs($this->cashier);

    Livewire::test(FloorIndex::class)
        ->call('selectPair', $this->row->id, 1)
        ->set('name', 'Cashier Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneEndSeq', 3)
        ->set('zoneSeatCount', 3)
        ->set('assignedServerId', $this->server2->id)
        ->call('createBillingGroup');

    $group = BillingGroup::latest('id')->first();
    $zone = OccupiedZone::where('billing_group_id', $group->id)->first();

    expect($group->opened_by_user_id)->toBe($this->cashier->id)
        ->and($zone)->not->toBeNull()
        ->and($zone->server_id)->toBe($this->server2->id)
        ->and($zone->created_by_user_id)->toBe($this->cashier->id);
});

it('prefills cashier assigned server from seat pair default server on floor selection', function () {
    $this->actingAs($this->cashier);

    Livewire::test(FloorIndex::class)
        ->call('selectPair', $this->row->id, 1)
        ->assertSet('assignedServerId', $this->server2->id);
});
