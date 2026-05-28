<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Orders\OrderService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderHeader;
use App\Models\OrderItem;
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

it('gracefully skips void slip when no production ticket route exists', function () {
    // Remove the KITCHEN production route so void slips cannot be created.
    PrinterRoute::where('fulfillment_route', 'KITCHEN')
        ->where('document_type', 'PRODUCTION_TICKET')
        ->delete();

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    // Submitting will fail because the production route is missing, so create manually.
    $header = \App\Models\OrderHeader::create([
        'billing_group_id' => $group->id,
        'ordered_by_user_id' => $this->server->id,
        'ordered_at' => now(),
        'submission_status' => 'SUBMITTED',
    ]);
    $orderItem = \App\Models\OrderItem::create([
        'order_header_id' => $header->id,
        'menu_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => $item->unit_price,
        'line_subtotal' => $item->unit_price,
        'fulfillment_route' => 'KITCHEN',
        'sent_to_production_at' => now(),
    ]);

    // Voiding should succeed but skip void-slip ticket creation
    // because resolvePrinterForRoute returns null (no active PRODUCTION_TICKET route).
    app(OrderService::class)->voidItem($orderItem, $this->server, 'Test void');

    expect($orderItem->fresh()->voided_at)->not->toBeNull()
        ->and(ProductionTicket::where('is_void_slip', true)->count())->toBe(0);
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
