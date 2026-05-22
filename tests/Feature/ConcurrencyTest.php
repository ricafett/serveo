<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Domain\Orders\OrderService;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->row = Row::first();
});

it('prevents overlapping zone assignments via database locking', function () {
    $g1 = app(BillingGroupService::class)->open($this->session, $this->server);
    $g2 = app(BillingGroupService::class)->open($this->session, $this->server);

    app(OccupancyService::class)->assignZone($g1, $this->row, 1, 3, $this->server);

    expect(fn () => app(OccupancyService::class)->assignZone($g2, $this->row, 2, 4, $this->server))
        ->toThrow(ZoneOverlapException::class);
});

it('allows simultaneous orders on same billing group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $zone = app(OccupancyService::class)->assignZone($group, $this->row, 1, 2, $this->server);

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $header1 = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $zone);
    $header2 = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $zone);

    expect($header1->id)->not->toBe($header2->id)
        ->and($group->orderHeaders()->count())->toBe(2);
});

it('rejects concurrent status changes with stale version', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $cashier = makeUser('CASHIER');
    $originalVersion = $group->version_number;

    // First update succeeds
    app(BillingGroupService::class)->setStatus($group, BillingStatus::CLOSED, $cashier, $originalVersion);
    $group->refresh();

    // Second update with same original version fails
    expect(fn () => app(BillingGroupService::class)->setStatus(
        $group,
        BillingStatus::CLOSED,
        $cashier,
        $originalVersion,
    ))->toThrow(RuntimeException::class, 'VERSION_CONFLICT');
});
