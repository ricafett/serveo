<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('allows WAITING to ACTIVE transition', function () {
    // Create group with WAITING status
    $group = app(BillingGroupService::class)->open($this->session, $this->server, null, null, BillingStatus::WAITING);
    app(BillingGroupService::class)->setStatus($group, BillingStatus::ACTIVE, $this->server);

    expect($group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE);
});

it('allows ACTIVE to CHECK_REQUESTED transition', function () {
    app(BillingGroupService::class)->setStatus($this->group, BillingStatus::CHECK_REQUESTED, $this->server);
    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::CHECK_REQUESTED);
});

it('allows CHECK_REQUESTED to PARTIALLY_PAID transition', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]], $this->zone
    );

    app(BillingGroupService::class)->setStatus($this->group, BillingStatus::CHECK_REQUESTED, $this->server);
    app(BillingService::class)->recordPayment($this->group->refresh(), $this->cashier, 10.00, 'Cash');

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::PARTIALLY_PAID);
});

it('allows PARTIALLY_PAID to ACTIVE via reopen', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]], $this->zone
    );

    app(BillingService::class)->recordPayment($this->group, $this->cashier, 10.00, 'Cash');
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->cashier);

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE);
});

it('allows ACTIVE to CLOSED transition', function () {
    app(BillingGroupService::class)->close($this->group, $this->server);
    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::CLOSED);
});

it('rejects ACTIVE to WAITING transition', function () {
    expect(fn () => app(BillingGroupService::class)->setStatus($this->group, BillingStatus::WAITING, $this->server))
        ->toThrow(RuntimeException::class, 'Invalid status transition');
});

it('rejects CLOSED to CHECK_REQUESTED without reopen', function () {
    app(BillingGroupService::class)->close($this->group, $this->server);

    expect(fn () => app(BillingGroupService::class)->setStatus($this->group->refresh(), BillingStatus::CHECK_REQUESTED, $this->server))
        ->toThrow(RuntimeException::class, 'Invalid status transition');
});

it('is no-op when reopening non-closed group', function () {
    $before = $this->group->version_number;
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->server);

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE)
        ->and($this->group->version_number)->toBe($before);
});
