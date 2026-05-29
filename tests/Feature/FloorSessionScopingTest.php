<?php

use App\Domain\Floor\OccupancyService;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);

    // Create a fresh row that CoreSeeder doesn't touch,
    // so our zone counts are predictable.
    $this->venue = Venue::first();
    $this->section = Section::create([
        'venue_id' => $this->venue->id,
        'section_code' => 'TEST',
        'name' => 'Test Section',
        'sort_order' => 99,
        'is_active' => true,
    ]);
    $this->row = Row::create([
        'section_id' => $this->section->id,
        'row_code' => 'T1',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        SeatPair::create([
            'row_id' => $this->row->id,
            'pair_sequence' => $i,
            'seat_a_id' => $i * 2 - 1,
            'seat_b_id' => $i * 2,
            'is_active' => true,
        ]);
    }

    $this->currentSession = ServiceSession::where('status', 'OPEN')->first();
});

// ------------------------------------------------------------------
// OccupancyService::releaseZone — closed billing group, open session
// ------------------------------------------------------------------

it('allows releasing a zone from a closed billing group when session is still open', function () {
    $server = makeUser('SERVER');

    // Create group and zone directly
    $group = createBillingGroup($this->currentSession, $server);
    $zone = OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $group->opened_by_user_id,
    ]);

    // Close the group directly (bypasses BillingGroupService::close()
    // which would auto-release zones via openOccupiedZones()->update(...))
    $group->update(['is_closed' => true]);

    expect($group->fresh()->is_closed)->toBeTrue();
    expect($zone->fresh()->is_open)->toBeTrue();

    // Release should succeed — group is closed but session is still open
    app(OccupancyService::class)->releaseZone($zone, $server);

    expect($zone->fresh()->is_open)->toBeFalse()
        ->and($zone->fresh()->released_at)->not->toBeNull();
});

it('allows releasing a zone from a closed billing group via cashier', function () {
    $cashier = makeUser('CASHIER');
    $server = makeUser('SERVER');

    $group = createBillingGroup($this->currentSession, $server);
    $zone = OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $group->opened_by_user_id,
    ]);

    // Close group directly (without service auto-release)
    $group->update(['is_closed' => true]);

    // Cashier should be able to release zone from closed group
    app(OccupancyService::class)->releaseZone($zone, $cashier);

    expect($zone->fresh()->is_open)->toBeFalse();
});

it('still rejects zone release when session is closed', function () {
    $server = makeUser('SERVER');
    $closedSession = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Closed Session',
        'starts_at' => now(),
        'status' => 'CLOSED',
    ]);
    $group = createBillingGroup($closedSession, $server);
    $zone = OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $group->opened_by_user_id,
    ]);

    expect(fn () => app(OccupancyService::class)->releaseZone($zone, $server))
        ->toThrow(RuntimeException::class, 'No open service session');
});

it('records audit event when releasing zone from closed group', function () {
    $server = makeUser('SERVER');

    $group = createBillingGroup($this->currentSession, $server);
    $zone = OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $group->opened_by_user_id,
    ]);

    $group->update(['is_closed' => true]);
    app(OccupancyService::class)->releaseZone($zone, $server);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'OCCUPIED_ZONE_RELEASED',
    ]);
});

// ------------------------------------------------------------------
// Session scoping: floor occupied zones
// ------------------------------------------------------------------

it('occupied zones from closed session are excluded by session-scoped query', function () {
    $server = makeUser('SERVER');

    // Zone in current (open) session
    $currentGroup = createBillingGroup($this->currentSession, $server);
    $currentZone = OccupiedZone::create([
        'billing_group_id' => $currentGroup->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 2,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $currentGroup->opened_by_user_id,
    ]);

    // Zone in a different (closed) session — same row, overlapping seats
    $closedSession = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Closed session',
        'starts_at' => now()->subDays(1),
        'status' => 'CLOSED',
    ]);
    $closedGroup = createBillingGroup($closedSession, $server);
    OccupiedZone::create([
        'billing_group_id' => $closedGroup->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 3,
        'end_seat_pair_sequence' => 4,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now()->subDay(),
        'is_open' => true,
        'created_by_user_id' => $closedGroup->opened_by_user_id,
    ]);

    // Query mimicking the fixed getSectionsProperty (with session scoping)
    $scopedZones = OccupiedZone::where('is_open', true)
        ->where('row_id', $this->row->id)
        ->whereHas('billingGroup', fn ($q) => $q->where('service_session_id', $this->currentSession->id))
        ->get();

    expect($scopedZones)->toHaveCount(1);
    expect($scopedZones->first()->id)->toBe($currentZone->id);

    // Without session scoping (the old buggy query), we'd get both zones
    $unscopedZones = OccupiedZone::where('is_open', true)
        ->where('row_id', $this->row->id)
        ->get();
    expect($unscopedZones)->toHaveCount(2);
});

it('favorite group ids are scoped to current session', function () {
    $server = makeUser('SERVER');

    $currentGroup = createBillingGroup($this->currentSession, $server);

    $oldSession = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Old session',
        'starts_at' => now()->subDays(1),
        'status' => 'CLOSED',
    ]);
    $oldGroup = createBillingGroup($oldSession, $server);

    // Favorite both groups as the server
    $server->favoriteBillingGroups()->sync([$currentGroup->id, $oldGroup->id]);

    // Query with session scoping (matching the fixed getFavoriteGroupIdsProperty)
    $favoriteIds = BillingGroup::whereHas('favoritedBy', fn ($q) => $q->where('user_id', $server->id))
        ->where('service_session_id', $this->currentSession->id)
        ->where('is_closed', false)
        ->pluck('id')
        ->toArray();

    expect($favoriteIds)->toContain($currentGroup->id);
    expect($favoriteIds)->not->toContain($oldGroup->id);
});

it('closed group zones in current session still appear in scoped query', function () {
    $server = makeUser('SERVER');

    $group = createBillingGroup($this->currentSession, $server);
    $zone = OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $this->row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 2,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $group->opened_by_user_id,
    ]);

    // Close the group directly without auto-releasing zones
    $group->update(['is_closed' => true]);

    // Zone is still open, group is in current session — should appear in floor map query
    $scopedZones = OccupiedZone::where('is_open', true)
        ->where('row_id', $this->row->id)
        ->whereHas('billingGroup', fn ($q) => $q->where('service_session_id', $this->currentSession->id))
        ->get();

    expect($scopedZones)->toHaveCount(1);
    expect($scopedZones->first()->id)->toBe($zone->id);
    expect($scopedZones->first()->billingGroup->is_closed)->toBeTrue();
});
