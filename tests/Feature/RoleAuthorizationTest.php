<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->admin   = makeUser('ADMIN');
    $this->server  = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);
});

it('prevents server from generating a bill', function () {
    expect(fn () => app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->server))
        ->toThrow(RuntimeException::class, 'Unauthorized: missing permission billing_document.create');
});

it('prevents server from recording a payment', function () {
    expect(fn () => app(BillingService::class)->recordPayment($this->group, $this->server, 10.00, 'Cash'))
        ->toThrow(RuntimeException::class, 'Unauthorized: missing permission payment.record');
});

it('prevents cashier from creating an order', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    expect(fn () => app(OrderService::class)->submit($this->group, $this->cashier,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone))
        ->toThrow(RuntimeException::class, 'Unauthorized: missing permission order.create');
});

it('prevents cashier from assigning a zone', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->cashier);

    expect(fn () => app(OccupancyService::class)->assignZone(
        $group, Row::first(), 3, 4, $this->cashier
    ))->toThrow(RuntimeException::class, 'Unauthorized: missing permission floor.assign_zone');
});

it('allows admin to perform all restricted actions', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    // Admin can create order
    $header = app(OrderService::class)->submit($this->group, $this->admin,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);
    expect($header)->not->toBeNull();

    // Admin can generate bill
    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->admin);
    expect($bill)->not->toBeNull();

    // Admin can record payment
    $payment = app(BillingService::class)->recordPayment($this->group->refresh(), $this->admin, 10.00, 'Cash');
    expect($payment)->not->toBeNull();

    // Admin can assign zone
    $group2 = app(BillingGroupService::class)->open($this->session, $this->admin);
    $zone = app(OccupancyService::class)->assignZone($group2, Row::first(), 5, 6, $this->admin);
    expect($zone)->not->toBeNull();
});
