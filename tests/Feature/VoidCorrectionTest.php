<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\AuditEvent;
use App\Models\MenuItem;
use App\Models\ProductionTicket;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    $this->header = app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
        ['menu_item_id' => $barItem->id,     'quantity' => 1],
    ], $this->zone);
});

it('voids an item and records voided_at and voided_by_user_id', function () {
    $item = $this->header->items->first();
    app(OrderService::class)->voidItem($item, $this->server, 'Wrong item');

    $item->refresh();
    expect($item->voided_at)->not->toBeNull()
        ->and($item->voided_by_user_id)->toBe($this->server->id)
        ->and($item->void_reason)->toBe('Wrong item');
});

it('creates a void slip production ticket for kitchen and bar items', function () {
    $kitchenItem = $this->header->items->firstWhere('fulfillment_route', 'KITCHEN');
    $barItem = $this->header->items->firstWhere('fulfillment_route', 'BAR');

    app(OrderService::class)->voidItem($kitchenItem, $this->server, 'No longer needed');
    app(OrderService::class)->voidItem($barItem, $this->server, 'No longer needed');

    $voidTickets = ProductionTicket::where('billing_group_id', $this->group->id)
        ->where('is_void_slip', true)
        ->get();

    expect($voidTickets)->toHaveCount(2);
    expect($voidTickets->pluck('ticket_type')->all())->toContain('VOID');
});

it('routes void slip to same destination as original', function () {
    $kitchenItem = $this->header->items->firstWhere('fulfillment_route', 'KITCHEN');

    app(OrderService::class)->voidItem($kitchenItem, $this->server, 'No longer needed');

    $voidTicket = ProductionTicket::where('billing_group_id', $this->group->id)
        ->where('is_void_slip', true)
        ->first();

    $originalTicket = ProductionTicket::where('billing_group_id', $this->group->id)
        ->where('is_void_slip', false)
        ->where('ticket_type', 'KITCHEN')
        ->first();

    expect($voidTicket->printer_id)->toBe($originalTicket->printer_id);
});

it('updates order header status to PARTIALLY_VOIDED when some items remain', function () {
    $item = $this->header->items->first();
    app(OrderService::class)->voidItem($item, $this->server, 'Mistake');

    expect($this->header->refresh()->submission_status)->toBe('PARTIALLY_VOIDED');
});

it('updates order header status to VOIDED when all items are voided', function () {
    foreach ($this->header->items as $item) {
        app(OrderService::class)->voidItem($item, $this->server, 'Cancelled');
    }

    expect($this->header->refresh()->submission_status)->toBe('VOIDED');
});

it('creates an audit event for void', function () {
    $item = $this->header->items->first();
    app(OrderService::class)->voidItem($item, $this->server, 'Mistake');

    expect(AuditEvent::where('event_type', 'ORDER_ITEM_VOIDED')->count())->toBe(1);
});
