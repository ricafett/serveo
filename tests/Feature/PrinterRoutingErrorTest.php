<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Orders\OrderService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
});

/* ──────────────────────────────────────────────────────────────────
 * Missing printer routes
 * ────────────────────────────────────────────────────────────────── */

it('throws when no production ticket route is configured for a route', function () {
    // Remove the KITCHEN route so Bacalhau cannot be routed.
    PrinterRoute::where('fulfillment_route', 'KITCHEN')
        ->where('document_type', 'PRODUCTION_TICKET')
        ->delete();

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    expect(fn () => app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]
    ))->toThrow(RuntimeException::class, 'No printer route configured for KITCHEN.');
});

it('does not throw when no void slip route is configured for a route', function () {
    // Remove the KITCHEN void-slip route.
    PrinterRoute::where('fulfillment_route', 'KITCHEN')
        ->where('document_type', 'VOID_SLIP')
        ->delete();

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $orderItem = $header->items->first();

    // Voiding should succeed but skip void-slip ticket creation.
    app(OrderService::class)->voidItem($orderItem, $this->server, 'Test void');

    expect($orderItem->fresh()->voided_at)->not->toBeNull();
});

it('throws when no bar production route is configured for a bar item', function () {
    PrinterRoute::where('fulfillment_route', 'BAR')
        ->where('document_type', 'PRODUCTION_TICKET')
        ->delete();

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Vinho copo')->first();

    expect(fn () => app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]
    ))->toThrow(RuntimeException::class, 'No printer route configured for BAR.');
});

it('skips ticket creation when route type is NONE', function () {
    // Create a category with route_type NONE so no printer is needed.
    $noneCat = MenuCategory::create([
        'code' => 'NONE_CAT',
        'display_name' => 'No route',
        'route_type' => 'NONE',
        'sort_order' => 99,
        'is_active' => true,
    ]);
    $noneItem = MenuItem::create([
        'menu_category_id' => $noneCat->id,
        'display_name' => 'No-route item',
        'unit_price' => 1.00,
        'is_active' => true,
    ]);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $header = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $noneItem->id, 'quantity' => 1]]);

    expect($header->items)->toHaveCount(1)
        ->and(ProductionTicket::where('billing_group_id', $group->id)->count())->toBe(0);
});
