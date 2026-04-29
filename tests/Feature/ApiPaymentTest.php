<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\MenuItem;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(\App\Domain\Orders\OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]],
        $this->zone
    );
});

it('records a partial payment via api', function () {
    $response = $this->actingAs($this->cashier)->postJson('/api/v1/payments', [
        'billingGroupId' => $this->group->id,
        'amount'         => '10.00',
        'paymentLabel'   => 'Cash',
        'notes'          => 'Partial payment',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.amount', '10.00');

    $this->group->refresh();
    expect($this->group->status?->code)->toBe('PARTIALLY_PAID');
});

it('records a full payment and closes the group', function () {
    $response = $this->actingAs($this->cashier)->postJson('/api/v1/payments', [
        'billingGroupId' => $this->group->id,
        'amount'         => '36.00',
        'paymentLabel'   => 'Cash',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->group->refresh();
    expect($this->group->is_closed)->toBeTrue();
});

it('rejects payment on closed group', function () {
    $this->group->update(['is_closed' => true, 'closed_at' => now()]);

    $response = $this->actingAs($this->cashier)->postJson('/api/v1/payments', [
        'billingGroupId' => $this->group->id,
        'amount'         => '10.00',
        'paymentLabel'   => 'Cash',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'GROUP_CLOSED');
});

it('rejects zero or negative payment amount', function () {
    $response = $this->actingAs($this->cashier)->postJson('/api/v1/payments', [
        'billingGroupId' => $this->group->id,
        'amount'         => '0',
        'paymentLabel'   => 'Cash',
    ]);

    $response->assertStatus(422);
});

it('enforces role permissions on payment endpoints', function () {
    // Server cannot record payments
    $this->actingAs($this->server)->postJson('/api/v1/payments', [
        'billingGroupId' => $this->group->id,
        'amount'         => '10.00',
        'paymentLabel'   => 'Cash',
    ])->assertStatus(403);
});
