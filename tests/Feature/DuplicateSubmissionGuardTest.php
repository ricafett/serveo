<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Livewire\BillingGroup\BillingGroupDetail;
use App\Livewire\Floor\FloorIndex;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
    $this->seed(DemoTransactionSeeder::class);

    // Close CoreSeeder session so tests only see their own session
    ServiceSession::where('status', 'OPEN')->update(['status' => 'CLOSED']);

    $this->venue = Venue::first();
    $this->session = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test Dinner',
        'starts_at' => now()->subHour(),
        'status' => 'OPEN',
    ]);

    $this->section = Section::create(['venue_id' => $this->venue->id, 'section_code' => 'TEST', 'name' => 'Test Section', 'sort_order' => 99, 'is_active' => true]);
    $this->row = Row::create(['section_id' => $this->section->id, 'row_code' => 'T1', 'sort_order' => 1, 'is_active' => true]);

    for ($i = 1; $i <= 10; $i++) {
        SeatPair::create(['row_id' => $this->row->id, 'pair_sequence' => $i, 'seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2, 'is_active' => true]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');

    $this->cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $this->cashier->assignRole('CASHIER');
    $this->cashier->givePermissionTo('billing_document.create');

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 3, $this->server);
});

// ------------------------------------------------------------------
// FloorIndex — createBillingGroup duplicate prevention
// ------------------------------------------------------------------

it('prevents double-click on createBillingGroup in floor index', function () {
    $this->actingAs($this->server);

    $initialCount = BillingGroup::count();

    Livewire::test(FloorIndex::class)
        ->set('isSubmitting', true)
        ->call('createBillingGroup');

    // No new billing group should have been created.
    expect(BillingGroup::count())->toBe($initialCount);
});

// ------------------------------------------------------------------
// BillingGroupDetail — addZone duplicate prevention
// ------------------------------------------------------------------

it('prevents double-click on addZone in billing group detail', function () {
    $this->actingAs($this->server);

    $initialZoneCount = OccupiedZone::where('billing_group_id', $this->group->id)->count();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('addZone');

    // No new zone should have been created.
    expect(OccupiedZone::where('billing_group_id', $this->group->id)->count())->toBe($initialZoneCount);
});

// ------------------------------------------------------------------
// BillingGroupDetail — releaseZone duplicate prevention
// ------------------------------------------------------------------

it('prevents double-click on releaseZone in billing group detail', function () {
    $this->actingAs($this->server);

    $zone = $this->group->occupiedZones->first();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('releaseZone', $zone->id);

    // Zone should remain open (not released).
    $zone->refresh();
    expect($zone->is_open)->toBeTrue();
});

// ------------------------------------------------------------------
// BillingGroupDetail — printBill duplicate prevention
// ------------------------------------------------------------------

it('prevents double-click on printBill in billing group detail', function () {
    $this->actingAs($this->server);

    $initialBillCount = BillingDocument::where('billing_group_id', $this->group->id)->count();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('printBill');

    expect(BillingDocument::where('billing_group_id', $this->group->id)->count())->toBe($initialBillCount);
});

// ------------------------------------------------------------------
// BillingGroupDetail — reopenGroup duplicate prevention
// ------------------------------------------------------------------

it('prevents double-click on reopenGroup in billing group detail', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);
    $this->actingAs($this->server);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('reopenGroup');

    $group = BillingGroup::find($this->group->id);
    expect($group->is_closed)->toBeTrue();
});
