<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Domain\Printing\TicketRenderer;
use App\Models\BillingDocument;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\FulfillmentRoute;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\ProductionTicket;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');

    $billPrinter = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

// ─── DocumentPrintConfig model ──────────────────────────────────────────

it('DocumentPrintConfig has correct default values', function () {
    $config = DocumentPrintConfig::create([
        'document_type' => 'TEST_TYPE',
        'fulfillment_route' => 'TEST_ROUTE',
        'group_items' => true,
        'ignore_variants' => false,
        'ignore_modifiers' => false,
        'is_active' => true,
    ]);

    expect($config->group_items)->toBeTrue()
        ->and($config->ignore_variants)->toBeFalse()
        ->and($config->ignore_modifiers)->toBeFalse()
        ->and($config->is_active)->toBeTrue();
});

it('DocumentPrintConfig enforces unique document_type + fulfillment_route', function () {
    DocumentPrintConfig::create([
        'document_type' => 'PRODUCTION_TICKET',
        'fulfillment_route' => 'UNIQUE_CHECK',
    ]);

    DocumentPrintConfig::create([
        'document_type' => 'PRODUCTION_TICKET',
        'fulfillment_route' => 'UNIQUE_CHECK',
    ]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('DocumentPrintConfig active scope filters inactive rows', function () {
    DocumentPrintConfig::create([
        'document_type' => 'PRODUCTION_TICKET',
        'fulfillment_route' => 'ACTIVE_TEST',
        'is_active' => true,
    ]);
    DocumentPrintConfig::create([
        'document_type' => 'PRODUCTION_TICKET',
        'fulfillment_route' => 'INACTIVE_TEST',
        'is_active' => false,
    ]);

    $active = DocumentPrintConfig::active()->get();
    expect($active)->toHaveCount(4) // KITCHEN, BAR, BILL (from migration), + ACTIVE_TEST
        ->and($active->pluck('fulfillment_route'))->toContain('ACTIVE_TEST')
        ->and($active->pluck('fulfillment_route'))->not->toContain('INACTIVE_TEST');
});

// ─── FulfillmentRouteObserver ────────────────────────────────────────────

it('auto-creates DocumentPrintConfig when fulfillment route is created', function () {
    $route = FulfillmentRoute::create([
        'code' => 'DESSERT',
        'display_name' => 'Sobremesas',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'DESSERT')
        ->first();

    expect($config)->not->toBeNull()
        ->and($config->group_items)->toBeTrue()
        ->and($config->ignore_variants)->toBeFalse()
        ->and($config->ignore_modifiers)->toBeFalse();
});

it('deletes DocumentPrintConfig when fulfillment route is deleted', function () {
    $route = FulfillmentRoute::create([
        'code' => 'TEMP_ROUTE',
        'display_name' => 'Temp',
        'sort_order' => 99,
    ]);

    expect(DocumentPrintConfig::where('fulfillment_route', 'TEMP_ROUTE')->exists())->toBeTrue();

    $route->delete();

    expect(DocumentPrintConfig::where('fulfillment_route', 'TEMP_ROUTE')->exists())->toBeFalse();
});

// ─── TicketRenderer: group_items for production tickets ─────────────────

it('groups items when production ticket config has group_items=true', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 3],
        ['menu_item_id' => $barItem->id, 'quantity' => 2],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    expect($ticket)->not->toBeNull();

    // Default config: group_items=true
    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($ticket->load('items'));

    // Should show " 3x Bacalhau" (grouped), not " 1x Bacalhau" × 3
    expect($output)->toContain(' 3x Bacalhau')
        ->and(substr_count($output, 'Bacalhau'))->toBe(1);
});

it('repeats items when production ticket config has group_items=false', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 3],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    expect($ticket)->not->toBeNull();

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['group_items' => false]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($ticket->load('items'));

    // Should repeat " 1x Bacalhau" 3 times
    expect($output)->toContain(' 1x Bacalhau')
        ->and(substr_count($output, 'Bacalhau'))->toBe(3);
});

// ─── TicketRenderer: group_items for bills ──────────────────────────────

it('groups identical bill items across orders when group_items=true', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $barItem = MenuItem::where('display_name', 'Vinho copo')->first();

    // Two orders, both containing Bacalhau
    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
    ], $this->zone);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
        ['menu_item_id' => $barItem->id, 'quantity' => 1],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    $config->update(['group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    // Both orders' Bacalhau should be grouped: " 3x Bacalhau" total
    expect($output)->toContain(' 3x Bacalhau')
        ->and(substr_count($output, 'Bacalhau'))->toBe(1);
});

it('does NOT group bill items when group_items=false (default)', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
    ], $this->zone);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    // Default: group_items=false
    $renderer = new TicketRenderer(documentConfig: null);
    $output = $renderer->renderBill($bill);

    // Should appear twice (once per order item)
    expect(substr_count($output, 'Bacalhau'))->toBe(2);
});

// ─── TicketRenderer: ignore_variants / ignore_modifiers ─────────────────

it('shows variant and modifier when config does not ignore them', function () {
    $vinhoItem = MenuItem::where('display_name', 'Vinho copo')->first();

    // Create variants for the test fixture item
    \App\Models\MenuItemVariant::firstOrCreate(
        ['menu_item_id' => $vinhoItem->id, 'display_name' => 'Casa'],
        ['sort_order' => 1, 'is_active' => true],
    );

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $vinhoItem->id, 'quantity' => 1, 'variant_name' => 'Casa', 'modifier_name' => 'Fresca'],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    $config->update(['ignore_variants' => false, 'ignore_modifiers' => false, 'group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    expect($output)->toContain('Casa')
        ->and($output)->toContain('Fresca');
});

it('hides variant when ignore_variants=true', function () {
    $vinhoItem = MenuItem::where('display_name', 'Vinho copo')->first();

    \App\Models\MenuItemVariant::firstOrCreate(
        ['menu_item_id' => $vinhoItem->id, 'display_name' => 'Casa'],
        ['sort_order' => 1, 'is_active' => true],
    );

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $vinhoItem->id, 'quantity' => 1, 'variant_name' => 'Casa'],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    $config->update(['ignore_variants' => true, 'group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    expect($output)->not->toContain('Casa')
        ->and($output)->toContain('Vinho copo');
});

it('hides modifier when ignore_modifiers=true', function () {
    $vinhoItem = MenuItem::where('display_name', 'Vinho copo')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $vinhoItem->id, 'quantity' => 1, 'modifier_name' => 'Fresca'],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    $config->update(['ignore_modifiers' => true, 'group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    expect($output)->not->toContain('Fresca')
        ->and($output)->toContain('Vinho copo');
});

// ─── ProductionTicket::effectiveFulfillmentRoute ─────────────────────────

it('returns ticket_type for normal production tickets', function () {
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => Printer::first()->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'is_void_slip' => false,
        'created_by_user_id' => $this->server->id,
    ]);

    expect($ticket->effectiveFulfillmentRoute())->toBe('KITCHEN');
});

it('returns item fulfillment_route for void slips', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 2],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();

    // Create a void slip for the first item
    $item = $ticket->items()->first();
    $voidTicket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $ticket->printer_id,
        'ticket_type' => 'VOID',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'is_void_slip' => true,
        'created_by_user_id' => $this->server->id,
    ]);
    $voidTicket->items()->sync([$item->id]);

    expect($voidTicket->effectiveFulfillmentRoute())->toBe('KITCHEN');
});

it('renders branding headers on production tickets, bills, reprints, and void slips by default', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->firstOrFail();
    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $voidItem = $ticket->items()->first();
    app(OrderService::class)->voidItem($voidItem, $this->server, 'Test');
    $voidTicket = ProductionTicket::where('is_void_slip', true)->latest('id')->firstOrFail();

    $reprint = ProductionTicket::create([
        'service_session_id' => $ticket->service_session_id,
        'billing_group_id' => $ticket->billing_group_id,
        'occupied_zone_id' => $ticket->occupied_zone_id,
        'printer_id' => $ticket->printer_id,
        'ticket_type' => $ticket->ticket_type,
        'ticket_sequence_route' => $ticket->ticket_sequence_route,
        'route_ticket_number' => $ticket->route_ticket_number,
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'reprint_of_ticket_id' => $ticket->id,
        'is_void_slip' => false,
        'is_reprint' => true,
        'created_by_user_id' => $this->server->id,
    ]);
    $reprint->items()->sync($ticket->items->pluck('id'));

    $ticketConfig = DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_PRODUCTION_TICKET)
        ->where('fulfillment_route', 'KITCHEN')
        ->firstOrFail();
    $billConfig = DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_BILL)
        ->whereNull('fulfillment_route')
        ->firstOrFail();

    $renderer = new TicketRenderer(documentConfig: $ticketConfig);
    $billRenderer = new TicketRenderer(documentConfig: $billConfig);

    expect($ticketConfig->branding_header)->toBe(DocumentPrintConfig::defaultBrandingHeader())
        ->and($billConfig->branding_header)->toBe(DocumentPrintConfig::defaultBrandingHeader())
        ->and($renderer->renderProductionTicket($ticket))->toContain(DocumentPrintConfig::defaultBrandingHeader())
        ->and($renderer->renderProductionTicket($voidTicket))->toContain(DocumentPrintConfig::defaultBrandingHeader())
        ->and($renderer->renderProductionTicket($reprint))->toContain(DocumentPrintConfig::defaultBrandingHeader())
        ->and($billRenderer->renderBill($bill))->toContain(DocumentPrintConfig::defaultBrandingHeader());
});
