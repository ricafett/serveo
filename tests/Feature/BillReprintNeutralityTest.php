<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\AuditEvent;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');

    // Assign bill printer to cashier (required — no fallback).
    $billPrinter = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 2]], $this->zone);

    $this->original = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);
});

it('creates a reprint with same totals', function () {
    $reprint = app(BillingService::class)->reprintBill($this->original, $this->cashier);

    expect($reprint->total_amount)->toBe($this->original->total_amount)
        ->and($reprint->subtotal_amount)->toBe($this->original->subtotal_amount);
});

it('marks reprint with is_reprint true', function () {
    $reprint = app(BillingService::class)->reprintBill($this->original, $this->cashier);

    expect($reprint->is_reprint)->toBeTrue()
        ->and($reprint->reprint_of_billing_document_id)->toBe($this->original->id);
});

it('does not alter original document totals', function () {
    $originalTotal = $this->original->total_amount;
    app(BillingService::class)->reprintBill($this->original, $this->cashier);

    expect($this->original->refresh()->total_amount)->toBe($originalTotal);
});

it('creates an audit event for reprint', function () {
    app(BillingService::class)->reprintBill($this->original, $this->cashier);

    expect(AuditEvent::where('event_type', 'BILL_REPRINTED')->count())->toBe(1);
});
