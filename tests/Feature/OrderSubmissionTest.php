<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\AuditEvent;
use App\Models\MenuItem;
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
