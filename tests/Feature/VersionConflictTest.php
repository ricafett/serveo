<?php

use App\Domain\Floor\BillingGroupService;
use App\Models\BillingStatus;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
});

it('throws VERSION_CONFLICT on stale version during setStatus', function () {
    $originalVersion = $this->group->version_number;

    // Simulate another update incrementing version
    $this->group->increment('version_number');

    expect(fn () => app(BillingGroupService::class)->setStatus(
        $this->group->refresh(),
        BillingStatus::CHECK_REQUESTED,
        $this->server,
        $originalVersion,
    ))->toThrow(RuntimeException::class, 'VERSION_CONFLICT');
});

it('increments version_number on successful setStatus', function () {
    $before = $this->group->version_number;

    app(BillingGroupService::class)->setStatus($this->group, BillingStatus::CHECK_REQUESTED, $this->server);

    expect($this->group->refresh()->version_number)->toBe($before + 1);
});

it('allows concurrent zone assignment on same billing group', function () {
    $zone1 = app(\App\Domain\Floor\OccupancyService::class)->assignZone(
        $this->group, \App\Models\Row::first(), 3, 4, $this->server
    );
    $zone2 = app(\App\Domain\Floor\OccupancyService::class)->assignZone(
        $this->group, \App\Models\Row::first(), 5, 6, $this->server
    );

    expect($zone1->is_open)->toBeTrue()
        ->and($zone2->is_open)->toBeTrue()
        ->and($this->group->occupiedZones()->count())->toBe(2);
});
