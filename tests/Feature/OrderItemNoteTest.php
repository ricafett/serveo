<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Domain\Printing\TicketRenderer;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\ProductionTicket;
use App\Models\Row;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

// ─── Note persistence ───────────────────────────────────────────────────

it('persists note on order item', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'sem cebola'],
    ], $this->zone);

    $orderItem = $header->items()->first();

    expect($orderItem->note)->toBe('sem cebola');
});

it('persists null note when not provided', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1],
    ], $this->zone);

    $orderItem = $header->items()->first();

    expect($orderItem->note)->toBeNull();
});

// ─── Production ticket rendering ─────────────────────────────────────────

it('shows note on production ticket when ignore_item_notes is false', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'sem cebola, bem passado'],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    expect($ticket)->not->toBeNull();

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['ignore_item_notes' => false]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($ticket->load('items'));

    expect($output)->toContain('-- sem cebola, bem passado');
});

it('hides note on production ticket when ignore_item_notes is true', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'sem cebola'],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    expect($ticket)->not->toBeNull();

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['ignore_item_notes' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($ticket->load('items'));

    expect($output)->not->toContain('-- sem cebola');
});

it('wraps long notes with indentation on production ticket', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    // Note long enough to wrap at 48 chars (accounting for "   -- " prefix = 6 chars → 42 usable)
    $longNote = str_repeat('A', 50);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => $longNote],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    expect($ticket)->not->toBeNull();

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['ignore_item_notes' => false]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($ticket->load('items'));

    // Both wrapped lines should have the indent prefix
    $lines = explode("\n", $output);
    $noteLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '   -- ')));
    expect($noteLines)->toHaveCount(2);
    expect($noteLines[0])->toStartWith('   -- ');
    expect($noteLines[1])->toStartWith('   -- ');
});

// ─── Bill rendering ──────────────────────────────────────────────────────

it('hides note on bill by default (ignore_item_notes=true)', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'sem cebola'],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    // BILL default: ignore_item_notes = true (set by migration)
    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    expect($config->ignore_item_notes)->toBeTrue();

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    expect($output)->not->toContain('sem cebola');
});

it('shows note on bill when ignore_item_notes is false', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'sem cebola'],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    $config->update(['ignore_item_notes' => false, 'group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    expect($output)->toContain('sem cebola');
});

it('groups noted and non-noted items in bill when notes are ignored', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    // Two orders: one with note, one without — same menu item
    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 2, 'note' => 'sem cebola'],
    ], $this->zone);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    // Default: ignore_item_notes=true — notes ignored → items grouped
    $config->update(['group_items' => true]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    // Only one Bacalhau line (grouped) with quantity 3
    expect($output)->toContain(' 3x Bacalhau')
        ->and(substr_count($output, 'Bacalhau'))->toBe(1);
});

it('does NOT group noted and non-noted items in bill when notes are shown', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 2, 'note' => 'sem cebola'],
    ], $this->zone);

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1],
    ], $this->zone);

    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();
    // Notes shown → grouping key includes note → items stay separate
    $config->update(['group_items' => true, 'ignore_item_notes' => false]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderBill($bill);

    // Two separate Bacalhau lines (one with note, one without)
    expect(substr_count($output, 'Bacalhau'))->toBe(2)
        ->and($output)->toContain(' 2x Bacalhau')
        ->and($output)->toContain(' 1x Bacalhau');
});

// ─── DocumentPrintConfig defaults ────────────────────────────────────────

it('defaults ignore_item_notes to false for production tickets', function () {
    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();

    expect($config->ignore_item_notes)->toBeFalse();
});

it('defaults ignore_item_notes to true for bills', function () {
    $config = DocumentPrintConfig::where('document_type', 'BILL')
        ->whereNull('fulfillment_route')
        ->first();

    expect($config->ignore_item_notes)->toBeTrue();
});

// ─── Void slip shows note ────────────────────────────────────────────────

it('shows note on void slip when ignore_item_notes is false', function () {
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $item->id, 'quantity' => 1, 'note' => 'enviado por engano'],
    ], $this->zone);

    $ticket = ProductionTicket::where('ticket_type', 'KITCHEN')->first();
    $orderItem = $ticket->items()->first();

    app(OrderService::class)->voidItem($orderItem, $this->server, 'cliente cancelou');

    $voidTicket = ProductionTicket::where('is_void_slip', true)->first();
    expect($voidTicket)->not->toBeNull();

    $config = DocumentPrintConfig::where('document_type', 'PRODUCTION_TICKET')
        ->where('fulfillment_route', 'KITCHEN')
        ->first();
    $config->update(['ignore_item_notes' => false]);

    $renderer = new TicketRenderer(documentConfig: $config);
    $output = $renderer->renderProductionTicket($voidTicket->load('items'));

    expect($output)->toContain('-- enviado por engano');
});
