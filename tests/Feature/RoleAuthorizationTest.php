<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->session = bootScenario();
    $this->admin = makeUser('ADMIN');
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');

    // Assign bill printer to admin and cashier (required — no fallback).
    $billPrinter = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->admin->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);
});

it('prevents server from generating a bill', function () {
    expect(fn () => app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->server))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission billing_document.create');
});

it('prevents server from recording a payment', function () {
    expect(fn () => app(BillingService::class)->recordPayment($this->group, $this->server, 10.00, 'Cash'))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission payment.record');
});

it('allows cashier to create an order', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($this->group, $this->cashier,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    expect($header)->not->toBeNull()
        ->and($header->ordered_by_user_id)->toBe($this->cashier->id);
});

it('allows cashier to assign a zone', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->admin);

    $zone = app(OccupancyService::class)->assignZone(
        $group, Row::first(), 3, 4, $this->cashier
    );

    expect($zone)->not->toBeNull()
        ->and($zone->server_id)->toBe($this->cashier->id);
});

it('prevents server from reprinting a bill', function () {
    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->admin);

    expect(fn () => app(BillingService::class)->reprintBill($bill, $this->server))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission billing_document.reprint');
});

it('allows cashier from opening a billing group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->cashier);

    expect($group)->not->toBeNull()
        ->and($group->opened_by_user_id)->toBe($this->cashier->id);
});

it('prevents server from releasing a zone', function () {
    $serverWithoutRelease = makeUser('SERVER');
    Role::findByName('SERVER')->revokePermissionTo('floor.release_zone');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => app(OccupancyService::class)->releaseZone($this->zone, $serverWithoutRelease))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission floor.release_zone');
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

    // Admin can void item
    $item = $header->items->first();
    app(OrderService::class)->voidItem($item, $this->admin, 'Test');
    expect($item->refresh()->voided_at)->not->toBeNull();

    // Admin can reprint bill
    $reprint = app(BillingService::class)->reprintBill($bill, $this->admin);
    expect($reprint)->not->toBeNull();

    // Admin can void payment
    app(BillingService::class)->voidPayment($payment, $this->admin, 'Correction');
    expect($payment->refresh()->is_voided)->toBeTrue();
});
