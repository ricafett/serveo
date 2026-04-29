<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('creates an order via api', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $response = $this->actingAs($this->server)->postJson('/api/v1/orders', [
        'billingGroupId' => $this->group->id,
        'occupiedZoneId' => $this->zone->id,
        'notes'          => 'API order',
        'items'          => [
            [
                'menuItemId' => $kitchenItem->id,
                'quantity'   => 3,
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.submissionStatus', 'SUBMITTED')
        ->assertJsonCount(1, 'data.items');
});

it('returns an order by id', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );

    $response = $this->actingAs($this->server)->getJson("/api/v1/orders/{$header->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.orderHeaderId', $header->id);
});

it('rejects order on closed billing group', function () {
    $this->group->update(['is_closed' => true, 'closed_at' => now()]);
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $response = $this->actingAs($this->server)->postJson('/api/v1/orders', [
        'billingGroupId' => $this->group->id,
        'items'          => [
            ['menuItemId' => $kitchenItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'GROUP_CLOSED');
});

it('voids order items via api', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );

    $item = $header->items->first();

    $response = $this->actingAs($this->server)->postJson("/api/v1/orders/{$header->id}/void-items", [
        'items' => [
            [
                'orderItemId' => $item->id,
                'reason'      => 'Wrong item ordered',
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.affectedItems');
});

it('enforces role permissions on order endpoints', function () {
    $cashier = makeUser('CASHIER');
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    // Cashier cannot create orders
    $this->actingAs($cashier)->postJson('/api/v1/orders', [
        'billingGroupId' => $this->group->id,
        'items'          => [
            ['menuItemId' => $kitchenItem->id, 'quantity' => 1],
        ],
    ])->assertStatus(403);
});
