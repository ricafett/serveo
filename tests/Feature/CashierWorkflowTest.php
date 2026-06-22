<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Livewire\BillingGroup\BillingGroupDetail;
use App\Livewire\Cashier\ReprintPanel;
use App\Jobs\OpenCashDrawerJob;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\ProductionTicket;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use App\Livewire\Floor\FloorIndex;
use App\Livewire\Order\OrderEntry;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    $this->assignedServer = User::factory()->create(['username' => 'assignedserver', 'is_active' => true]);
    $this->assignedServer->assignRole('SERVER');

    $this->cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $this->cashier->assignRole('CASHIER');
    $this->cashier->givePermissionTo('production_ticket.reprint');

    // Assign a bill printer to the cashier
    $billPrinter = Printer::where('is_active', true)->first();
    if (! $billPrinter) {
        $billPrinter = Printer::create([
            'name' => 'Test Bill Printer',
            'connection_type' => 'LAN',
            'address' => '192.168.1.99',
            'port' => 9100,
            'is_active' => true,
        ]);
    }
    CashierPrinterAssignment::create(['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id, 'is_active' => true]);

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 5, $this->server);

    // Add an order so there are charges
    $menuItem = MenuItem::first();
    app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $menuItem->id, 'quantity' => 2]],
    );
});

// ------------------------------------------------------------------
// Billing Group Lookup
// ------------------------------------------------------------------

it('renders billing group lookup for cashier', function () {
    $response = $this->actingAs($this->cashier)->get('/lookup');
    $response->assertOk();
    $response->assertSee('Billing Groups');
    $response->assertSee($this->group->display_code);
});

it('filters closed groups by default', function () {
    $this->group->refresh();
    $balance = $this->group->balance();
    app(BillingService::class)->recordPayment($this->group, $this->cashier, $balance, 'Cash');
    expect($this->group->fresh()->is_closed)->toBeTrue();

    $response = $this->actingAs($this->cashier)->get('/lookup');
    $response->assertOk();
    $response->assertDontSee($this->group->display_code);
});

it('shows closed groups when show closed is enabled', function () {
    $this->group->refresh();
    $balance = $this->group->balance();
    app(BillingService::class)->recordPayment($this->group, $this->cashier, $balance, 'Cash');
    expect($this->group->fresh()->is_closed)->toBeTrue();

    $response = $this->actingAs($this->cashier)->get('/lookup?showClosed=1');
    $response->assertOk();
    $response->assertSee($this->group->display_code);
});

it('searches billing groups by display code', function () {
    $response = $this->actingAs($this->cashier)->get('/lookup?search='.urlencode($this->group->display_code));
    $response->assertOk();
    $response->assertSee($this->group->display_code);
});

// ------------------------------------------------------------------
// Reprint Panel
// ------------------------------------------------------------------

it('renders reprint panel with bills and tickets', function () {
    $this->actingAs($this->cashier);

    app(BillingService::class)->generateInternalBill($this->group, $this->cashier);

    $response = $this->actingAs($this->cashier)->get("/reprint/{$this->group->id}");
    $response->assertOk();
    $response->assertSee('Reprint & Documents');
    $response->assertSee('Bills');
});

it('reprints ticket from reprint panel', function () {
    $this->actingAs($this->cashier);

    $ticket = ProductionTicket::where('billing_group_id', $this->group->id)->first();
    expect($ticket)->not->toBeNull();

    Livewire::test(ReprintPanel::class, ['billingGroupId' => $this->group->id])
        ->call('reprintTicket', $ticket->id)
        ->assertSet('successMessage', 'Ticket reprint sent to printer.');

    $reprints = ProductionTicket::where('billing_group_id', $this->group->id)->where('is_reprint', true)->get();
    expect($reprints)->toHaveCount(1)
        ->and($reprints->first()->route_ticket_number)->toBe($ticket->route_ticket_number)
        ->and($reprints->first()->ticket_sequence_route)->toBe($ticket->ticket_sequence_route);
});

// ------------------------------------------------------------------
// Role Access
// ------------------------------------------------------------------

it('prevents server from accessing cashier lookup', function () {
    $response = $this->actingAs($this->server)->get('/lookup');
    $response->assertForbidden();
});

it('allows cashier to access floor', function () {
    $response = $this->actingAs($this->cashier)->get('/floor');
    $response->assertOk();
    $response->assertSee('Floor');
});

it('allows cashier to access order entry', function () {
    $response = $this->actingAs($this->cashier)->get("/orders/new/{$this->group->id}");
    $response->assertOk();
    $response->assertSee('Order Entry');
});

it('cashier can create billing group from floor with assigned server', function () {
    $this->actingAs($this->cashier);

    Livewire::test(FloorIndex::class)
        ->set('name', 'Cashier Floor Group')
        ->set('statusCode', 'ACTIVE')
        ->set('zoneRowId', $this->row->id)
        ->set('zoneStartSeq', 6)
        ->set('zoneEndSeq', 7)
        ->set('zoneSeatCount', 2)
        ->set('assignedServerId', $this->assignedServer->id)
        ->call('createBillingGroup');

    $group = BillingGroup::latest('id')->first();
    $zone = $group->occupiedZones()->first();

    expect($group->opened_by_user_id)->toBe($this->cashier->id)
        ->and($zone->server_id)->toBe($this->assignedServer->id);
});

it('cashier can submit an order from order entry', function () {
    $this->actingAs($this->cashier);

    Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [['menu_item_id' => MenuItem::first()->id, 'quantity' => 1]])
        ->assertSet('successMessage', 'Order submitted successfully.');

    expect(BillingGroup::find($this->group->id)->orderHeaders()->latest('id')->first()->ordered_by_user_id)
        ->toBe($this->cashier->id);
});

it('cashier recording payment from billing group detail queues cash drawer opening', function () {
    Queue::fake();
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('paymentAmount', 5.00)
        ->set('paymentLabel', 'Cash')
        ->call('recordPayment')
        ->assertSet('successMessage', 'Payment recorded.');

    Queue::assertPushed(OpenCashDrawerJob::class, function (OpenCashDrawerJob $job) {
        return $job->actorId === $this->cashier->id
            && $job->printerId === CashierPrinterAssignment::where('user_id', $this->cashier->id)
                ->where('is_active', true)
                ->value('printer_id');
    });
});

it('allows admin to access cashier screens', function () {
    $admin = User::factory()->create(['username' => 'testadmin', 'is_active' => true]);
    $admin->assignRole('ADMIN');

    $response = $this->actingAs($admin)->get('/lookup');
    $response->assertOk();
});

// ------------------------------------------------------------------
// Duplicate Submission Prevention (reprint panel isSubmitting guards)
// ------------------------------------------------------------------

it('prevents double-click on reprintTicket in reprint panel', function () {
    $this->actingAs($this->cashier);

    $ticket = ProductionTicket::where('billing_group_id', $this->group->id)->first();

    Livewire::test(ReprintPanel::class, ['billingGroupId' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('reprintTicket', $ticket->id)
        ->assertSet('successMessage', null);

    $reprints = ProductionTicket::where('billing_group_id', $this->group->id)->where('is_reprint', true)->get();
    expect($reprints)->toHaveCount(0);
});

it('prevents double-click on reprintBill in reprint panel', function () {
    $this->actingAs($this->cashier);

    $bill = app(BillingService::class)->generateInternalBill($this->group, $this->cashier);

    Livewire::test(ReprintPanel::class, ['billingGroupId' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('reprintBill', $bill->id)
        ->assertSet('successMessage', null);

    $reprints = BillingDocument::where('billing_group_id', $this->group->id)->where('is_reprint', true)->get();
    expect($reprints)->toHaveCount(0);
});
