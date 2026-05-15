<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\Row;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
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

it('is no-op when reopening non-closed group', function () {
    $before = $this->group->version_number;
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->cashier);

    expect($this->group->refresh()->status?->code)->toBe(BillingStatus::ACTIVE)
        ->and($this->group->version_number)->toBe($before);
});
