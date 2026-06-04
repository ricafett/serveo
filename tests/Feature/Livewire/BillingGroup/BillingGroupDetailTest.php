<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Livewire\BillingGroup\BillingGroupDetail;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\MenuItem;
use App\Models\PaymentRecord;
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

    // Assign a bill printer to the cashier
    $billPrinter = \App\Models\Printer::where('is_active', true)->first();
    if (! $billPrinter) {
        $billPrinter = \App\Models\Printer::create([
            'name' => 'Test Bill Printer',
            'connection_type' => 'LAN',
            'address' => '192.168.1.99',
            'port' => 9100,
            'is_active' => true,
        ]);
    }
    \App\Models\CashierPrinterAssignment::create(['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id, 'is_active' => true]);

    // Add an order so there are charges (needed for payment/reopen/void tests)
    $this->menuItem = MenuItem::where('is_active', true)->first();
    app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 2]],
    );
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

    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        $this->zone,
        null,
    );

    $component = Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id]);

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
        ->call('openVoidModal', $order->id, true)
        ->set('voidReason', 'Guest cancelled')
        ->call('confirmVoid')
        ->assertSet('successMessage', __('billing.items_voided'));

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
        ->call('openVoidModal', $order->id, true)
        ->assertSet('errorMessage', __('billing.void_unauthorized'));

    expect($order->refresh()->submission_status)->toBe('SUBMITTED');
});

// ------------------------------------------------------------------
// Bill printing
// ------------------------------------------------------------------

it('prints bill from billing group detail', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('printBill')
        ->assertSet('successMessage', 'Bill sent to printer.');

    $doc = BillingDocument::where('billing_group_id', $this->group->id)->first();
    expect($doc)->not->toBeNull();
    expect($doc->is_reprint)->toBeFalse();
});

it('reprints bill from billing group detail', function () {
    $this->actingAs($this->cashier);

    $bill = app(BillingService::class)->generateInternalBill($this->group, $this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('reprintBill', $bill->id)
        ->assertSet('successMessage', 'Bill reprint sent to printer.');

    $reprints = BillingDocument::where('billing_group_id', $this->group->id)->where('is_reprint', true)->get();
    expect($reprints)->toHaveCount(1);
});

// ------------------------------------------------------------------
// Payment
// ------------------------------------------------------------------

it('records partial payment from billing group detail', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('paymentAmount', 5.00)
        ->set('paymentLabel', 'Cash')
        ->call('recordPayment')
        ->assertSet('successMessage', 'Payment recorded.');

    $payment = PaymentRecord::where('billing_group_id', $this->group->id)->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(5.00);
});

// ------------------------------------------------------------------
// Reopen
// ------------------------------------------------------------------

it('reopens closed group from billing group detail', function () {
    $this->group->refresh();
    $balance = $this->group->balance();
    app(BillingService::class)->recordPayment($this->group, $this->cashier, $balance, 'Cash');
    expect($this->group->fresh()->is_closed)->toBeTrue();

    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('reopenGroup')
        ->assertSet('successMessage', 'Group reopened.');

    $group = BillingGroup::find($this->group->id);
    expect($group->is_closed)->toBeFalse();
});

// ------------------------------------------------------------------
// Cashier void
// ------------------------------------------------------------------

it('cashier can void another server order from billing group detail', function () {
    $this->actingAs($this->cashier);

    $order = $this->group->orderHeaders()->latest('id')->first();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidModal', $order->id, true)
        ->set('voidReason', 'Cashier approved cancellation')
        ->call('confirmVoid')
        ->assertSet('successMessage', __('billing.items_voided'));

    expect($order->refresh()->submission_status)->toBe('VOIDED');
});

it('cashier can void a single item from billing group detail', function () {
    $this->actingAs($this->cashier);

    // Create an order with multiple items so voiding one leaves PARTIALLY_VOIDED.
    $order = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 1],
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 1],
        ],
        $this->zone,
    );
    $item = $order->items()->first();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidModal', $item->order_header_id, false)
        ->set('selectedVoidItemIds', [$item->id])
        ->set('voidReason', 'Cashier item correction')
        ->call('confirmVoid')
        ->assertSet('successMessage', __('billing.items_voided'));

    expect($item->refresh()->voided_at)->not->toBeNull();
    expect($order->fresh()->submission_status)->toBe('PARTIALLY_VOIDED');
});

it('can void multiple items from the same order at once', function () {
    $this->actingAs($this->server);

    $order = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 1],
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 2],
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 1],
        ],
        $this->zone,
    );

    $items = $order->items()->get();
    expect($items)->toHaveCount(3);

    // Void only 2 of 3 items.
    $voidIds = $items->take(2)->pluck('id')->toArray();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidModal', $order->id, false)
        ->set('selectedVoidItemIds', $voidIds)
        ->set('voidReason', 'Partial void')
        ->call('confirmVoid')
        ->assertSet('successMessage', __('billing.items_voided'));

    expect($order->refresh()->submission_status)->toBe('PARTIALLY_VOIDED');
    expect($order->items()->whereNotNull('voided_at')->count())->toBe(2);
    expect($order->items()->whereNull('voided_at')->count())->toBe(1);
});

// ------------------------------------------------------------------
// Duplicate submission prevention
// ------------------------------------------------------------------

it('prevents double-click on printBill', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('printBill')
        ->assertSet('successMessage', null)
        ->assertSet('errorMessage', null);

    expect(BillingDocument::where('billing_group_id', $this->group->id)->count())->toBe(0);
});

it('prevents double-click on recordPayment', function () {
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('paymentAmount', 5.00)
        ->set('paymentLabel', 'Cash')
        ->set('isSubmitting', true)
        ->call('recordPayment')
        ->assertSet('successMessage', null);

    expect(PaymentRecord::where('billing_group_id', $this->group->id)->count())->toBe(0);
});

it('prevents double-click on reopenGroup', function () {
    $this->group->refresh();
    $balance = $this->group->balance();
    app(BillingService::class)->recordPayment($this->group, $this->cashier, $balance, 'Cash');
    expect($this->group->fresh()->is_closed)->toBeTrue();
    $this->actingAs($this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('reopenGroup')
        ->assertSet('successMessage', null);

    $group = BillingGroup::find($this->group->id);
    expect($group->is_closed)->toBeTrue();
});

it('prevents double-click on reprintBill', function () {
    $this->actingAs($this->cashier);

    $bill = app(BillingService::class)->generateInternalBill($this->group, $this->cashier);

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->set('isSubmitting', true)
        ->call('reprintBill', $bill->id)
        ->assertSet('successMessage', null);

    $reprints = BillingDocument::where('billing_group_id', $this->group->id)->where('is_reprint', true)->get();
    expect($reprints)->toHaveCount(0);
});

it('prevents double-click on confirmVoid', function () {
    $this->actingAs($this->cashier);

    $order = $this->group->orderHeaders()->latest('id')->first();
    $item = $order->items()->first();

    Livewire::test(BillingGroupDetail::class, ['id' => $this->group->id])
        ->call('openVoidModal', $order->id, true)
        ->set('voidReason', 'Duplicate click')
        ->set('isSubmitting', true)
        ->call('confirmVoid')
        ->assertSet('successMessage', null);

    expect($item->refresh()->voided_at)->toBeNull();
});
