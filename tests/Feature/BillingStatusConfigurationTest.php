<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Orders\OrderService;
use App\Models\BillingStatus;
use App\Models\MenuItem;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
});

/* ──────────────────────────────────────────────────────────────────
 * Inactive status handling
 * ────────────────────────────────────────────────────────────────── */

it('allows group creation with inactive status when passed explicitly', function () {
    $inactive = BillingStatus::create([
        'code' => 'HOLD',
        'display_name' => 'Hold',
        'sort_order' => 5,
        'is_active' => false,
    ]);

    $group = app(BillingGroupService::class)->open($this->session, $this->server, initialStatusCode: 'HOLD');

    expect($group->billing_status_id)->toBe($inactive->id);
});

it('rejects transition to a status not in valid transitions even if active', function () {
    BillingStatus::create([
        'code' => 'ARCHIVED',
        'display_name' => 'Archived',
        'sort_order' => 99,
        'is_active' => true,
    ]);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    expect(fn () => app(BillingGroupService::class)->setStatus($group, 'ARCHIVED', $this->cashier))
        ->toThrow(RuntimeException::class, 'Invalid status transition');
});

it('auto-closes group even when closed status is inactive', function () {
    $closed = BillingStatus::where('code', BillingStatus::CLOSED)->first();
    $closed->update(['is_active' => false]);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $payment = app(BillingService::class)->recordPayment($group->refresh(), $this->cashier, $group->fresh()->balance(), 'Cash');

    expect($group->fresh()->is_closed)->toBeTrue();
});

it('auto-sets partially paid even when partially paid status is inactive', function () {
    $partial = BillingStatus::firstOrCreate(
        ['code' => BillingStatus::PARTIALLY_PAID],
        ['display_name' => 'Partially Paid', 'sort_order' => 15, 'is_active' => true]
    );
    $partial->update(['is_active' => false]);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    // Add an order so the group has a positive balance.
    $item = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $payment = app(BillingService::class)->recordPayment($group, $this->cashier, 1.00, 'Cash');

    expect($group->fresh()->billing_status_id)->toBe($partial->id);
});

/* ──────────────────────────────────────────────────────────────────
 * Missing status handling
 * ────────────────────────────────────────────────────────────────── */

it('throws when target status code does not exist', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    expect(fn () => app(BillingGroupService::class)->setStatus($group, 'FAKE', $this->cashier))
        ->toThrow(RuntimeException::class);
});

it('defaults to ACTIVE when no initial status is provided', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    expect($group->status->code)->toBe(BillingStatus::ACTIVE);
});
