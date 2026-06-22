<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\AuditEvent;
use App\Models\MenuItem;
use App\Models\OrderHeader;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('creates a kitchen and a bar production ticket and queues print jobs', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
        ['menu_item_id' => $barItem->id,     'quantity' => 4],
    ], $this->zone);

    expect($header->items)->toHaveCount(2);

    $tickets = ProductionTicket::where('billing_group_id', $this->group->id)->get();
    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('ticket_type')->sort()->values()->all())->toBe(['BAR', 'KITCHEN']);

    expect(PrintJob::count())->toBeGreaterThanOrEqual(2);
});

it('assigns route-scoped ticket numbers per production route', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
        ['menu_item_id' => $barItem->id, 'quantity' => 1],
    ], $this->zone);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
    ], $this->zone);

    $kitchenTickets = ProductionTicket::where('billing_group_id', $this->group->id)
        ->where('ticket_type', 'KITCHEN')
        ->orderBy('id')
        ->get();
    $barTickets = ProductionTicket::where('billing_group_id', $this->group->id)
        ->where('ticket_type', 'BAR')
        ->orderBy('id')
        ->get();

    expect($kitchenTickets->pluck('route_ticket_number')->all())->toBe([1, 2])
        ->and($kitchenTickets->pluck('ticket_sequence_route')->unique()->all())->toBe(['KITCHEN'])
        ->and($barTickets->pluck('route_ticket_number')->all())->toBe([1])
        ->and($barTickets->pluck('ticket_sequence_route')->unique()->all())->toBe(['BAR']);
});

it('rejects ordering on a closed billing group', function () {
    $this->group->update(['is_closed' => true, 'closed_at' => now()]);

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    expect(fn () => app(OrderService::class)->submit(
        $this->group->refresh(), $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone,
    ))->toThrow(RuntimeException::class);
});

it('records an audit event for every order submission', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    expect(AuditEvent::where('event_type', 'ORDER_SUBMITTED')->count())->toBe(1);
});

it('saves a draft order without queuing production tickets or print jobs', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->saveDraft($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
    ], $this->zone);

    expect($header->submission_status)->toBe('DRAFT')
        ->and($header->items)->toHaveCount(1)
        ->and($header->items->first()->sent_to_production_at)->toBeNull();

    expect(ProductionTicket::where('billing_group_id', $this->group->id)->count())->toBe(0)
        ->and(PrintJob::count())->toBe(0)
        ->and(AuditEvent::where('event_type', 'ORDER_DRAFT_SAVED')->where('order_header_id', $header->id)->exists())->toBeTrue();
});

it('submits a saved draft order to production later', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    $header = app(OrderService::class)->saveDraft($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
        ['menu_item_id' => $barItem->id, 'quantity' => 1],
    ], $this->zone);

    $submitted = app(OrderService::class)->submitDraft($header->fresh(), $this->server);

    expect($submitted->submission_status)->toBe('SUBMITTED');
    expect($submitted->items->every(fn ($item) => $item->sent_to_production_at !== null))->toBeTrue();

    $tickets = ProductionTicket::where('billing_group_id', $this->group->id)->get();

    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('ticket_type')->sort()->values()->all())->toBe(['BAR', 'KITCHEN'])
        ->and(PrintJob::count())->toBeGreaterThanOrEqual(2)
        ->and(AuditEvent::where('event_type', 'ORDER_SUBMITTED')->where('order_header_id', $header->id)->count())->toBe(1);
});

// ------------------------------------------------------------------
// Idempotency / duplicate submission prevention
// ------------------------------------------------------------------

it('returns the same order when submitted twice with the same idempotency key', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $key = 'test-idempotency-key-' . uniqid();

    $header1 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone, null, $key
    );

    $header2 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone, null, $key
    );

    // Same order header should be returned (not a new one).
    expect($header1->id)->toBe($header2->id);
    expect($header1->submission_status)->toBe('SUBMITTED');

    // Only one order should exist.
    expect(OrderHeader::where('billing_group_id', $this->group->id)->count())->toBe(1);

    // Only one audit event for ORDER_SUBMITTED.
    expect(AuditEvent::where('event_type', 'ORDER_SUBMITTED')->count())->toBe(1);
});

it('creates separate orders when different idempotency keys are used', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    $header1 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone, null, 'key-one-' . uniqid()
    );

    $header2 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $barItem->id, 'quantity' => 2]],
        $this->zone, null, 'key-two-' . uniqid()
    );

    // Different orders should be created.
    expect($header1->id)->not->toBe($header2->id);
    expect(OrderHeader::where('billing_group_id', $this->group->id)->count())->toBe(2);
});

it('allows duplicate submissions when no idempotency key is provided (backward compatibility)', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $header1 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );

    $header2 = app(OrderService::class)->submit(
        $this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]],
        $this->zone
    );

    // Without an idempotency key, duplicate orders are still allowed (existing behavior).
    expect($header1->id)->not->toBe($header2->id);
    expect(OrderHeader::where('billing_group_id', $this->group->id)->count())->toBe(2);
});
