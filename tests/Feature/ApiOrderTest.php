<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\OrderHeader;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('creates an order via api', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $response = $this->actingAs($this->server)->postJson('/api/v1/orders', [
        'billingGroupId' => $this->group->id,
        'occupiedZoneId' => $this->zone->id,
        'notes' => 'API order',
        'items' => [
            [
                'menuItemId' => $kitchenItem->id,
                'quantity' => 3,
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
    $header = app(OrderService::class)->submit(
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
        'items' => [
            ['menuItemId' => $kitchenItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'GROUP_CLOSED');
});

it('voids order items via api', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );

    $item = $header->items->first();

    $response = $this->actingAs($this->server)->postJson("/api/v1/orders/{$header->id}/void-items", [
        'items' => [
            [
                'orderItemId' => $item->id,
                'reason' => 'Wrong item ordered',
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.affectedItems');
});

it('voids an order via api', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();
    $header = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [
            ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
            ['menu_item_id' => $barItem->id, 'quantity' => 1],
        ],
        $this->zone
    );

    $response = $this->actingAs($this->server)->postJson("/api/v1/orders/{$header->id}/void", [
        'reason' => 'Guest cancelled everything',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.orderHeaderId', $header->id)
        ->assertJsonPath('data.submissionStatus', 'VOIDED')
        ->assertJsonCount(2, 'data.affectedItems');
});

it('prevents a different server from voiding another server order via api', function () {
    $otherServer = makeUser('SERVER');
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );

    $this->actingAs($otherServer)
        ->postJson("/api/v1/orders/{$header->id}/void", [
            'reason' => 'Unauthorized attempt',
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('allows cashier to void another server order via api', function () {
    $cashier = makeUser('CASHIER');
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );

    $this->actingAs($cashier)
        ->postJson("/api/v1/orders/{$header->id}/void", [
            'reason' => 'Cashier correction',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.submissionStatus', 'VOIDED');
});

it('allows cashier to create orders through the api', function () {
    $cashier = makeUser('CASHIER');
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->actingAs($cashier)->postJson('/api/v1/orders', [
        'billingGroupId' => $this->group->id,
        'occupiedZoneId' => $this->zone->id,
        'items' => [
            ['menuItemId' => $kitchenItem->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);
});

it('prevents duplicate orders via api when idempotency key is provided', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $key = 'api-idempotency-key-' . uniqid();

    $payload = [
        'billingGroupId' => $this->group->id,
        'occupiedZoneId' => $this->zone->id,
        'idempotencyKey' => $key,
        'notes' => 'API idempotent order',
        'items' => [
            ['menuItemId' => $kitchenItem->id, 'quantity' => 2],
        ],
    ];

    // First submission should succeed with 201.
    $response1 = $this->actingAs($this->server)->postJson('/api/v1/orders', $payload);
    $response1->assertStatus(201);
    $orderId1 = $response1->json('data.orderHeaderId');

    // Second submission with the same idempotency key should return same order (200, not 201).
    $response2 = $this->actingAs($this->server)->postJson('/api/v1/orders', $payload);
    $response2->assertStatus(201);
    $orderId2 = $response2->json('data.orderHeaderId');

    // Same order ID should be returned.
    expect($orderId1)->toBe($orderId2);

    // Only one order should exist in the database.
    expect(OrderHeader::where('billing_group_id', $this->group->id)->count())->toBe(1);
});
