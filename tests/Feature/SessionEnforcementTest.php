<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\PaymentRecord;
use App\Models\Row;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\CoreSeeder::class);
});

// ------------------------------------------------------------------
// OrderService — submit requires open session
// ------------------------------------------------------------------

it('rejects order submission when session is closed', function () {
    $server = makeUser('SERVER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, $server);
    $menuItem = MenuItem::first();

    expect(fn () => app(OrderService::class)->submit(
        $group, $server,
        [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
    ))->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// OrderService — voidItem requires open session
// ------------------------------------------------------------------

it('rejects void item when session is closed', function () {
    $server = makeUser('SERVER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, $server);
    $menuItem = MenuItem::first();

    $header = OrderHeader::create([
        'billing_group_id' => $group->id,
        'ordered_by_user_id' => $server->id,
        'ordered_at' => now(),
        'submission_status' => 'SUBMITTED',
    ]);
    $item = OrderItem::create([
        'order_header_id' => $header->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 1,
        'unit_price' => 10.0,
        'line_subtotal' => 10.0,
        'fulfillment_route' => 'NONE',
        'sent_to_production_at' => now(),
    ]);

    expect(fn () => app(OrderService::class)->voidItem($item, $server))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingService — generateInternalBill requires open session
// ------------------------------------------------------------------

it('rejects bill generation when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    expect(fn () => app(BillingService::class)->generateInternalBill($group, $cashier))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingService — recordPayment requires open session
// ------------------------------------------------------------------

it('rejects payment recording when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    expect(fn () => app(BillingService::class)->recordPayment($group, $cashier, 10.0, 'Cash'))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingService — reprintBill requires open session
// ------------------------------------------------------------------

it('rejects bill reprint when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    $bill = BillingDocument::create([
        'billing_group_id' => $group->id,
        'document_type' => BillingDocument::TYPE_INTERNAL_BILL,
        'document_status' => 'GENERATED',
        'document_number' => 'B-TEST',
        'subtotal_amount' => 10.0,
        'total_amount' => 10.0,
        'requested_at' => now(),
        'is_reprint' => false,
        'created_by_user_id' => $cashier->id,
    ]);

    expect(fn () => app(BillingService::class)->reprintBill($bill, $cashier))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingService — voidPayment requires open session
// ------------------------------------------------------------------

it('rejects payment void when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    $payment = PaymentRecord::create([
        'billing_group_id' => $group->id,
        'recorded_by_user_id' => $cashier->id,
        'recorded_at' => now(),
        'amount' => 10.0,
        'payment_label' => 'Cash',
        'is_voided' => false,
    ]);

    expect(fn () => app(BillingService::class)->voidPayment($payment, $cashier))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingGroupService — setStatus requires open session
// ------------------------------------------------------------------

it('rejects status change when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    expect(fn () => app(BillingGroupService::class)->setStatus($group, 'CLOSED', $cashier))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingGroupService — close requires open session
// ------------------------------------------------------------------

it('rejects group close when session is closed', function () {
    $cashier = makeUser('CASHIER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, makeUser('SERVER'));

    expect(fn () => app(BillingGroupService::class)->close($group, $cashier))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// BillingGroupService — reopen requires open session
// ------------------------------------------------------------------

it('rejects group reopen when session is closed', function () {
    $server = makeUser('SERVER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, $server);
    $group->update(['is_closed' => true, 'closed_at' => now()]);

    expect(fn () => app(BillingGroupService::class)->reopen($group, $server))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// OccupancyService — assignZone requires open session
// ------------------------------------------------------------------

it('rejects zone assignment when session is closed', function () {
    $server = makeUser('SERVER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, $server);
    $row = Row::first();

    expect(fn () => app(OccupancyService::class)->assignZone($group, $row, 1, 3, $server))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// OccupancyService — releaseZone requires open session
// ------------------------------------------------------------------

it('rejects zone release when session is closed', function () {
    $server = makeUser('SERVER');
    $venue = \App\Models\Venue::first();
    $session = ServiceSession::create([
        'venue_id' => $venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($session, $server);
    $row = Row::first();
    $zone = OccupiedZone::create([
        'billing_group_id'         => $group->id,
        'row_id'                   => $row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence'   => 3,
        'default_delivery_mode'    => 'CENTER',
        'opened_at'                => now(),
        'is_open'                  => true,
        'created_by_user_id'       => $group->opened_by_user_id,
    ]);

    expect(fn () => app(OccupancyService::class)->releaseZone($zone, $server))
        ->toThrow(RuntimeException::class, 'No open service session');
});

// ------------------------------------------------------------------
// Positive: operations succeed when session IS open
// ------------------------------------------------------------------

it('allows order submission when session is open', function () {
    $server = makeUser('SERVER');
    $session = ServiceSession::where('status', 'OPEN')->first();
    $group = createBillingGroup($session, $server);
    $row = Row::first();
    $zone = OccupiedZone::create([
        'billing_group_id'         => $group->id,
        'row_id'                   => $row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence'   => 3,
        'default_delivery_mode'    => 'CENTER',
        'opened_at'                => now(),
        'is_open'                  => true,
        'created_by_user_id'       => $group->opened_by_user_id,
    ]);
    $menuItem = MenuItem::first();

    $header = app(OrderService::class)->submit(
        $group, $server,
        [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        $zone,
    );

    expect($header->id)->not->toBeNull();
});
