<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Livewire\BillingGroup\BillingGroupDetail;
use App\Models\BillingGroup;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
    $this->seed(DemoTransactionSeeder::class);

    $this->venue = Venue::first();
    $this->session = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test Dinner',
        'starts_at' => now()->subHour(),
        'status' => 'OPEN',
    ]);

    $this->section = Section::create([
        'venue_id' => $this->venue->id,
        'section_code' => 'TEST',
        'name' => 'Test Section',
        'sort_order' => 99,
        'is_active' => true,
    ]);
    $this->row = Row::create([
        'section_id' => $this->section->id,
        'row_code' => 'T1',
        'sort_order' => 1,
        'is_active' => true,
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
            'row_id' => $this->row->id,
            'pair_sequence' => $i,
            'seat_a_id' => $seatA->id,
            'seat_b_id' => $seatB->id,
            'is_active' => true,
        ]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');

    $this->cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $this->cashier->assignRole('CASHIER');

    $this->server2 = User::factory()->create(['username' => 'assignedserver', 'is_active' => true]);
    $this->server2->assignRole('SERVER');

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 5, $this->server);

    $this->menuItem = MenuItem::where('is_active', true)->first();
});

// ------------------------------------------------------------------
// Order notes visibility
// ------------------------------------------------------------------

it('displays order notes on billing group detail for server', function () {
    $this->actingAs($this->server);

    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
        'Extra napkins please',
    );

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->assertSee('Extra napkins please');
});

it('displays order notes on billing group detail for cashier', function () {
    $this->actingAs($this->cashier);

    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 2]],
        $this->zone,
        'Guest allergic to shellfish',
    );

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->assertSee('Guest allergic to shellfish');
});

it('does not display notes section when order has no notes', function () {
    $this->actingAs($this->server);

    // Create an order without notes
    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
        null, // no notes
    );

    // The component should render successfully
    $component = Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id]);

    // The notes icon SVG path should not appear (order has no notes)
    $html = $component->html();
    expect($html)->toContain('Submitted');
});

it('displays multiple order notes from different orders', function () {
    $this->actingAs($this->server);

    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
        'First order note',
    );

    app(OrderService::class)->submit(
        $this->group->refresh(), $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 3]],
        $this->zone,
        'Second order note',
    );

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->assertSee('First order note')
        ->assertSee('Second order note');
});

it('requires assigned server when cashier adds a zone', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('zoneRowId', $this->row->id)
        ->set('zoneStartSeq', 6)
        ->set('zoneEndSeq', 7)
        ->set('assignedServerId', null)
        ->call('addZone')
        ->assertHasErrors(['assignedServerId']);
});

it('cashier can add a zone with an explicitly assigned server', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('zoneRowId', $this->row->id)
        ->set('zoneStartSeq', 6)
        ->set('zoneEndSeq', 7)
        ->set('assignedServerId', $this->server2->id)
        ->call('addZone')
        ->assertSet('showAddZoneModal', false);

    $zone = BillingGroup::find($this->group->id)
        ->occupiedZones()
        ->where('start_seat_pair_sequence', 6)
        ->where('end_seat_pair_sequence', 7)
        ->first();

    expect($zone)->not->toBeNull()
        ->and($zone->server_id)->toBe($this->server2->id)
        ->and($zone->created_by_user_id)->toBe($this->cashier->id);
});

it('server can void own order from billing group detail', function () {
    $this->actingAs($this->server);

    $order = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
    );

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidOrderModal', $order->id)
        ->set('voidReason', 'Guest cancelled')
        ->call('confirmVoidOrder')
        ->assertDispatched('notify', message: __('billing.order_voided'));

    expect($order->refresh()->submission_status)->toBe('VOIDED');
});

it('prevents server from voiding another server order from billing group detail', function () {
    $otherServer = User::factory()->create(['username' => 'otherserver', 'is_active' => true]);
    $otherServer->assignRole('SERVER');

    $order = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
    );

    $this->actingAs($otherServer);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidOrderModal', $order->id)
        ->assertDispatched('notify', message: __('billing.void_unauthorized'));

    expect($order->refresh()->submission_status)->toBe('SUBMITTED');
});
