<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\BillingStatus;
use App\Models\Row;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('allows ACTIVE to CLOSED transition by cashier', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);
    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::CLOSED);
});

it('allows CLOSED to ACTIVE via reopen by cashier', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->cashier);

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE);
});

it('rejects ACTIVE to CLOSED by server', function () {
    expect(fn () => app(BillingGroupService::class)->close($this->group, $this->server))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission billing_group.set_status');
});

it('allows reopen by server', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);

    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->server);

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE);
});

it('rejects setStatus to CLOSED by server', function () {
    expect(fn () => app(BillingGroupService::class)->setStatus($this->group, BillingStatus::CLOSED, $this->server))
        ->toThrow(AuthorizationException::class, 'Unauthorized: missing permission billing_group.set_status');
});

it('rejects invalid transition from CLOSED to ACTIVE via setStatus', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);

    expect(fn () => app(BillingGroupService::class)->setStatus($this->group->refresh(), BillingStatus::ACTIVE, $this->cashier))
        ->toThrow(RuntimeException::class, 'Invalid status transition');
});

it('rejects close with non-zero balance', function () {
    // Add an order to create a balance
    $kitchenItem = \App\Models\MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );
    $this->group->refresh();
    expect($this->group->balance())->toBeGreaterThan(0);

    expect(fn () => app(BillingGroupService::class)->close($this->group, $this->cashier))
        ->toThrow(RuntimeException::class, 'Cannot close billing group with outstanding balance.');
});

it('allows close with zero balance after full payment', function () {
    // Add an order and pay it exactly
    $kitchenItem = \App\Models\MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );
    $this->group->refresh();
    $balance = $this->group->balance();

    app(\App\Domain\Billing\BillingService::class)->recordPayment(
        $this->group, $this->cashier, $balance, 'Cash'
    );
    $this->group->refresh();

    expect($this->group->balance())->toBe(0.0);
    expect($this->group->status?->code)->toBe(BillingStatus::CLOSED);
    expect($this->group->is_closed)->toBeTrue();
});
