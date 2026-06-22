<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\Row;
use App\Models\ServiceSession;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->row = Row::firstOrFail();
});

it('opens an occupied zone within an empty row', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $zone = app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    expect($zone->is_open)->toBeTrue()
        ->and($zone->billing_group_id)->toBe($group->id)
        ->and($zone->start_seat_pair_sequence)->toBe(1)
        ->and($zone->end_seat_pair_sequence)->toBe(3);
});

it('rejects overlapping zone in the same row', function () {
    $g1 = app(BillingGroupService::class)->open($this->session, $this->server);
    $g2 = app(BillingGroupService::class)->open($this->session, $this->server);

    app(OccupancyService::class)->assignZone($g1, $this->row, 1, 3, $this->server);

    expect(fn () => app(OccupancyService::class)->assignZone($g2, $this->row, 3, 5, $this->server))
        ->toThrow(ZoneOverlapException::class);
});

it('allows reusing range after release', function () {
    $g1 = app(BillingGroupService::class)->open($this->session, $this->server);
    $z = app(OccupancyService::class)->assignZone($g1, $this->row, 4, 6, $this->server);
    app(OccupancyService::class)->releaseZone($z, $this->server);

    $g2 = app(BillingGroupService::class)->open($this->session, $this->server);
    $new = app(OccupancyService::class)->assignZone($g2, $this->row, 4, 6, $this->server);

    expect($new->is_open)->toBeTrue();
});

it('ignores open zones from a different service session when checking overlap', function () {
    $currentGroup = app(BillingGroupService::class)->open($this->session, $this->server);

    $closedSession = ServiceSession::create([
        'venue_id' => $this->session->venue_id,
        'session_type' => 'DINNER',
        'session_label' => 'Closed session',
        'starts_at' => now()->subDay(),
        'status' => 'CLOSED',
    ]);

    $oldGroup = createBillingGroup($closedSession, $this->server);

    \App\Models\OccupiedZone::create([
        'billing_group_id' => $oldGroup->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now()->subDay(),
        'is_open' => true,
        'created_by_user_id' => $oldGroup->opened_by_user_id,
        'server_id' => $this->server->id,
    ]);

    $zone = app(OccupancyService::class)->assignZone($currentGroup, $this->row, 1, 3, $this->server);

    expect($zone->is_open)->toBeTrue()
        ->and($zone->billing_group_id)->toBe($currentGroup->id);
});

it('rejects inverted ranges', function () {
    $g = app(BillingGroupService::class)->open($this->session, $this->server);
    expect(fn () => app(OccupancyService::class)->assignZone($g, $this->row, 5, 2, $this->server))
        ->toThrow(RuntimeException::class);
});
