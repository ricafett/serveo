<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\BillingStatus;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
});

it('throws VERSION_CONFLICT on stale version during setStatus', function () {
    $originalVersion = $this->group->version_number;

    // Simulate another update incrementing version
    $this->group->increment('version_number');

    expect(fn () => app(BillingGroupService::class)->setStatus(
        $this->group->refresh(),
        BillingStatus::CLOSED,
        $this->cashier,
        $originalVersion,
    ))->toThrow(RuntimeException::class, 'VERSION_CONFLICT');
});

it('increments version_number on successful setStatus', function () {
    $before = $this->group->version_number;

    app(BillingGroupService::class)->setStatus($this->group, BillingStatus::CLOSED, $this->cashier);

    expect($this->group->refresh()->version_number)->toBe($before + 1);
});

it('throws VERSION_CONFLICT on stale version during close', function () {
    $originalVersion = $this->group->version_number;
    $this->group->increment('version_number');

    expect(fn () => app(BillingGroupService::class)->close(
        $this->group->refresh(),
        $this->cashier,
        $originalVersion,
    ))->toThrow(RuntimeException::class, 'VERSION_CONFLICT');
});

it('increments version_number on successful close', function () {
    $before = $this->group->version_number;

    app(BillingGroupService::class)->close($this->group, $this->cashier);

    expect($this->group->refresh()->version_number)->toBe($before + 1);
});

it('throws VERSION_CONFLICT on stale version during reopen', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);
    $this->group->refresh();
    $originalVersion = $this->group->version_number;
    $this->group->increment('version_number');

    expect(fn () => app(BillingGroupService::class)->reopen(
        $this->group->refresh(),
        $this->cashier,
        $originalVersion,
    ))->toThrow(RuntimeException::class, 'VERSION_CONFLICT');
});

it('increments version_number on successful reopen', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);
    $this->group->refresh();
    $before = $this->group->version_number;

    app(BillingGroupService::class)->reopen($this->group, $this->cashier);

    expect($this->group->refresh()->version_number)->toBe($before + 1);
});

it('allows concurrent zone assignment on same billing group', function () {
    $zone1 = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 3, 4, $this->server
    );
    $zone2 = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 5, 6, $this->server
    );

    expect($zone1->is_open)->toBeTrue()
        ->and($zone2->is_open)->toBeTrue()
        ->and($this->group->occupiedZones()->count())->toBe(2);
});
