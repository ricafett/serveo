<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\User;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('creates a billing group via api', function () {
    $response = $this->actingAs($this->server)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
        'coverCount' => 4,
        'notes'      => 'API test group',
        'zones'      => [
            [
                'rowId'                   => Row::first()->id,
                'startSeatPairSequence'   => 3,
                'endSeatPairSequence'     => 4,
                'deliveryMode'            => 'CENTER',
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
        'notes'         => 'Updated via API',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.notes', 'Updated via API');
});

it('allows cashier to close a billing group via status update', function () {
    $response = $this->actingAs($this->cashier)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'statusCode'    => 'CLOSED',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.statusCode', 'CLOSED')
        ->assertJsonPath('data.isClosed', true);
});

it('rejects server closing a billing group via status update', function () {
    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'statusCode'    => 'CLOSED',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('rejects update with stale version number', function () {
    $this->group->update(['version_number' => 5]);

    $response = $this->actingAs($this->server)->patchJson("/api/v1/billing-groups/{$this->group->id}", [
        'versionNumber' => 1,
        'notes'         => 'Should fail',
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
                'rowId'                 => Row::first()->id,
                'startSeatPairSequence' => 5,
                'endSeatPairSequence'   => 6,
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
                'rowId'                 => Row::first()->id,
                'startSeatPairSequence' => 1,
                'endSeatPairSequence'   => 2,
            ],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'ZONE_OVERLAP');
});

it('returns orders for a billing group', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
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
    app(\App\Domain\Billing\BillingService::class)->recordPayment(
        $this->group, $this->cashier, 1000.00, 'Numerário'
    );

    $this->group->refresh();
    expect($this->group->is_closed)->toBeTrue();

    $response = $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/reopen", [
        'reason'        => 'Guest returned',
        'versionNumber' => $this->group->version_number,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.isClosed', false);
});

it('rejects reopen with stale version', function () {
    app(\App\Domain\Billing\BillingService::class)->recordPayment(
        $this->group, $this->cashier, 1000.00, 'Numerário'
    );

    $this->group->refresh();

    $response = $this->actingAs($this->cashier)->postJson("/api/v1/billing-groups/{$this->group->id}/reopen", [
        'reason'        => 'Guest returned',
        'versionNumber' => $this->group->version_number - 1,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'VERSION_CONFLICT');
});

it('enforces role permissions on billing group endpoints', function () {
    $admin = makeUser('ADMIN');

    // Admin can create
    $this->actingAs($admin)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
    ])->assertStatus(201);

    // Cashier cannot create
    $this->actingAs($this->cashier)->postJson('/api/v1/billing-groups', [
        'statusCode' => 'ACTIVE',
    ])->assertStatus(403);
});
