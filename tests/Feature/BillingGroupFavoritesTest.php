<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->row = Row::first();
});

it('auto-favorites a billing group when a server assigns a zone', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    expect($group->isFavoritedBy($this->server))->toBeTrue();

    $pivot = $group->favoritedBy()->where('user_id', $this->server->id)->first();
    expect((bool) $pivot->pivot->is_manual)->toBeFalse();
});

it('allows a server to manually favorite a billing group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    // Manual favorite (not through zone assignment)
    $group->favoritedBy()->attach($this->server->id, ['is_manual' => true]);

    expect($group->isFavoritedBy($this->server))->toBeTrue();

    $pivot = $group->favoritedBy()->where('user_id', $this->server->id)->first();
    expect((bool) $pivot->pivot->is_manual)->toBeTrue();
});

it('allows unfavorite of manual favorites', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $group->favoritedBy()->attach($this->server->id, ['is_manual' => true]);
    expect($group->isFavoritedBy($this->server))->toBeTrue();

    $group->favoritedBy()->detach($this->server->id);
    expect($group->isFavoritedBy($this->server))->toBeFalse();
});

it('auto-favorite creates a favorite entry that cannot be unfavorited by UI logic', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    $pivot = $group->favoritedBy()->where('user_id', $this->server->id)->first();
    expect((bool) $pivot->pivot->is_manual)->toBeFalse();

    // Verify the favorite exists and is auto (UI should block unfavorite)
    expect($group->favoritedBy()->count())->toBe(1);
});

it('upserts favorite when server assigns multiple zones to same group', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    // Zone 1
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    // Zone 2 — need a different row to avoid overlap
    $row2 = Row::where('id', '!=', $this->row->id)->first();
    if ($row2) {
        app(OccupancyService::class)->assignZone($group, $row2, 1, 2, $this->server);
    }

    // Should still have exactly one favorite entry
    $favCount = $group->favoritedBy()->where('user_id', $this->server->id)->count();
    expect($favCount)->toBe(1);
});

it('manual favorite is overwritten by auto-favorite when zone is assigned', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    // Server manually favorites first
    $group->favoritedBy()->attach($this->server->id, ['is_manual' => true]);

    // Then assigns a zone (auto-favorite)
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    // The auto-favorite upsert should set is_manual = false
    $pivot = $group->favoritedBy()->where('user_id', $this->server->id)->first();
    expect((bool) $pivot->pivot->is_manual)->toBeFalse();
});

it('auto-favorite can be overwritten by manual favorite', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    // Server assigns a zone (auto-favorite)
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    // Then manually favorites (should flip to is_manual = true)
    if ($group->favoritedBy()->where('user_id', $this->server->id)->exists()) {
        $group->favoritedBy()->updateExistingPivot($this->server->id, ['is_manual' => true]);
    } else {
        $group->favoritedBy()->attach($this->server->id, ['is_manual' => true]);
    }

    $pivot = $group->favoritedBy()->where('user_id', $this->server->id)->first();
    expect((bool) $pivot->pivot->is_manual)->toBeTrue();
});
