<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\MenuItem;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
    $this->seed(DemoTransactionSeeder::class);

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
        $seatA = Seat::create(['row_id' => $this->row->id, 'seat_number' => $i * 2 - 1, 'sort_order' => $i * 2 - 1, 'is_active' => true]);
        $seatB = Seat::create(['row_id' => $this->row->id, 'seat_number' => $i * 2, 'sort_order' => $i * 2, 'is_active' => true]);
        SeatPair::create(['row_id' => $this->row->id, 'pair_sequence' => $i, 'seat_a_id' => $seatA->id, 'seat_b_id' => $seatB->id, 'is_active' => true]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');

    $this->cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $this->cashier->assignRole('CASHIER');

    $this->assignedServer = User::factory()->create(['username' => 'assignedserver', 'is_active' => true]);
    $this->assignedServer->assignRole('SERVER');
});

// ------------------------------------------------------------------
// Floor Screen Rendering
// ------------------------------------------------------------------

it('renders floor screen with sections and rows', function () {
    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertOk();
    $response->assertSee('Test Section');
    $response->assertSee('Row T1');
    $response->assertSee('Floor');
});

it('shows free seat pair buttons individually on floor', function () {
    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertOk();
    $response->assertSee('TESTT101');
    $response->assertSee('TESTT102');
    $response->assertSee('TESTT110');
    $response->assertDontSee('1–10');
});

it('shows occupied ranges with positioning labels on floor', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 3, 6, $this->server);

    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertOk();
    $response->assertSee($group->display_code);
    // Occupied zones render the correct start/end labels.
    $response->assertSee('TESTT103');
    $response->assertSee('TESTT106');
    // Free pairs are individual buttons
    $response->assertSee('TESTT101');
    $response->assertSee('TESTT107');
});

it('shows open billing groups quick list on floor', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertOk();
    $response->assertSee('Open Billing Groups');
    $response->assertSee($group->display_code);
});

it('renders floor screen for cashier', function () {
    $response = $this->actingAs($this->cashier)->get('/floor');
    $response->assertOk();
    $response->assertSee('Floor');
    $response->assertSee('TESTT101');
});

it('shows assigned server selector to cashier in floor create modal', function () {
    $response = $this->actingAs($this->cashier)->get('/floor');
    $response->assertOk();
    $response->assertSee('Assign Server');
});

it('does not show assigned server selector to server in floor create modal', function () {
    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertOk();
    $response->assertDontSee('Assign Server');
});

it('shows no session warning when no open session exists', function () {
    ServiceSession::where('status', 'OPEN')->update(['status' => 'CLOSED']);

    $response = $this->actingAs($this->server)->get('/floor');
    $response->assertRedirect(route('home'));
});

// ------------------------------------------------------------------
// Billing Group Detail Rendering
// ------------------------------------------------------------------

it('shows billing group detail with zones', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee($group->display_code);
    $response->assertSee('TESTT101-TESTT105');
    $response->assertSee('Occupied Zones');
});

it('shows billing group detail with orders and items', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $menuItem = MenuItem::first();

    $orderHeader = OrderHeader::create([
        'billing_group_id' => $group->id,
        'ordered_by_user_id' => $this->server->id,
        'ordered_at' => now(),
        'submission_status' => 'SUBMITTED',
    ]);

    OrderItem::create([
        'order_header_id' => $orderHeader->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 2,
        'unit_price' => $menuItem->unit_price,
        'line_subtotal' => $menuItem->unit_price * 2,
        'fulfillment_route' => 'KITCHEN',
    ]);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee($menuItem->display_name);
    $response->assertSee('SUBMITTED');
});

it('shows billing group charges payments and balance', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $menuItem = MenuItem::first();

    $orderHeader = OrderHeader::create([
        'billing_group_id' => $group->id,
        'ordered_by_user_id' => $this->server->id,
        'ordered_at' => now(),
        'submission_status' => 'SUBMITTED',
    ]);

    OrderItem::create([
        'order_header_id' => $orderHeader->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 2,
        'unit_price' => 5.00,
        'line_subtotal' => 10.00,
        'fulfillment_route' => 'KITCHEN',
    ]);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee('Charges');
    $response->assertSee('10.00');
    $response->assertSee('Balance');
});

it('shows closed status for closed billing group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $cashier = User::factory()->create(['username' => 'testcashier-closed', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($group, $cashier);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee('Closed');
});

it('shows add order button for open group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee('Add Order');
});

it('shows add order button for cashier on open group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $response = $this->actingAs($this->cashier)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee('Add Order');
    $response->assertSee('Add Zone');
});

it('hides add order button for closed group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $cashier = User::factory()->create(['username' => 'testcashier2', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($group, $cashier);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertDontSee('Add Order');
});

it('shows reopen button for closed group to authorized users', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $cashier = User::factory()->create(['username' => 'testcashier3', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($group, $cashier);

    $response = $this->actingAs($cashier)->get("/billing-groups/{$group->id}");
    $response->assertOk();
    $response->assertSee('Reopen');
});

// ------------------------------------------------------------------
// Zone overlap protection
// ------------------------------------------------------------------

it('prevents overlapping zones via occupancy service', function () {
    $group1 = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group1, $this->row, 3, 6, $this->server);

    $group2 = app(BillingGroupService::class)->open($this->session, $this->server);

    expect(fn () => app(OccupancyService::class)->assignZone($group2, $this->row, 4, 7, $this->server))
        ->toThrow(ZoneOverlapException::class);
});
