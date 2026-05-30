<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->server2 = makeUser('SERVER', 'server-2');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('creates a billing group via api', function () {
    $response = $this->actingAs($this->server)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
        'coverCount' => 4,
        'notes' => 'API test group',
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 3,
                'endSeatPairSequence' => 4,
                'deliveryMode' => 'CENTER',
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.statusCode', 'ACTIVE')
        ->assertJsonPath('data.coverCount', 4);
});

it('returns a billing group with zones and totals', function () {
    $response = $this->actingAs($this->server)->getJson("/api/v1/billing-groups/{$this->group->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.billingGroupId', $this->group->id)
        ->assertJsonPath('data.displayCode', $this->group->display_code);
});

it('updates billing group notes only', function () {
    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'notes' => 'Updated via API',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.notes', 'Updated via API');
});

it('allows cashier to close a billing group via status update', function () {
    $response = $this->actingAs($this->cashier)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'statusCode' => 'CLOSED',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.statusCode', 'CLOSED')
        ->assertJsonPath('data.isClosed', true);
});

it('rejects server closing a billing group via status update', function () {
    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'statusCode' => 'CLOSED',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('rejects update with stale version number', function () {
    $this->group->update(['version_number' => 5]);

    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'notes' => 'Should fail',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'VERSION_CONFLICT');
});

it('rejects update on closed group', function () {
    $this->group->update(['is_closed' => true, 'closed_at' => now()]);

    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'notes' => 'Should fail',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'GROUP_CLOSED');
});

it('adds zones to an existing billing group', function () {
    $response = $this->actingAs($this->server)->postJson("/api/v1/billing-groups/{$this->group->id}/zones", [
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 5,
                'endSeatPairSequence' => 6,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('rejects overlapping zones', function () {
    $response = $this->actingAs($this->server)->postJson("/api/v1/billing-groups/{$this->group->id}/zones", [
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 1,
                'endSeatPairSequence' => 2,
            ],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'ZONE_OVERLAP');
});

it('returns orders for a billing group', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );

    $response = $this->actingAs($this->server)->getJson("/api/v1/billing-groups/{$this->group->id}/orders");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data');
});

it('returns bill summary for a billing group', function () {
    $response = $this->actingAs($this->cashier)->getJson("/api/v1/billing-groups/{$this->group->id}/bill-summary");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.billingGroupId', $this->group->id);
});

it('reopens a closed billing group', function () {
    // Add charges so the group has a balance, then pay it exactly.
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );
    $this->group->refresh();
    $balance = $this->group->balance();

    app(BillingService::class)->recordPayment(
        $this->group, $this->cashier, $balance, 'Numerário'
    );

    $this->group->refresh();
    expect($this->group->is_closed)->toBeTrue();

    $response = $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/reopen", [
        'reason' => 'Guest returned',
        'versionNumber' => $this->group->version_number,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.isClosed', false);
});

it('rejects reopen with stale version', function () {
    // Add charges so the group has a balance, then pay it exactly.
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );
    $this->group->refresh();
    $balance = $this->group->balance();

    app(BillingService::class)->recordPayment(
        $this->group, $this->cashier, $balance, 'Numerário'
    );

    $this->group->refresh();

    $response = $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/reopen", [
        'reason' => 'Guest returned',
        'versionNumber' => $this->group->version_number - 1,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'VERSION_CONFLICT');
});

it('allows cashier to create billing groups through the api', function () {
    $admin = makeUser('ADMIN');

    // Admin can create
    $this->actingAs($admin)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
    ])->assertStatus(201);

    // Cashier can create
    $this->actingAs($this->cashier)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
    ])->assertStatus(201);
});

it('requires assigned server when cashier creates zoned billing groups through the api', function () {
    $this->actingAs($this->cashier)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 3,
                'endSeatPairSequence' => 4,
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zones.0.assignedServerId']);
});

it('requires assigned server when cashier adds zones through the api', function () {
    $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/zones", [
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 5,
                'endSeatPairSequence' => 6,
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zones.0.assignedServerId']);
});

it('uses the assigned server when cashier adds zones through the api', function () {
    $response = $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/zones", [
        'zones' => [
            [
                'rowId' => Row::first()->id,
                'startSeatPairSequence' => 5,
                'endSeatPairSequence' => 6,
                'assignedServerId' => $this->server2->id,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($this->group->fresh()->occupiedZones()->where('start_seat_pair_sequence', 5)->first()?->server_id)
        ->toBe($this->server2->id);
});
