<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Jobs\OpenCashDrawerJob;
use App\Models\BillingDocument;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Row;
use Illuminate\Support\Facades\Queue;

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
});

it('generates an internal bill and queues it on the cashier printer', function () {
    $bill = app(BillingService::class)->generateInternalBill($this->group->refresh(), $this->cashier);

    expect($bill->document_type)->toBe(BillingDocument::TYPE_INTERNAL_BILL)
        ->and((float) $bill->total_amount)->toBe(36.00);

    expect(PrintJob::where('printer_id', $bill->printer_id)->count())->toBeGreaterThanOrEqual(1);
});

it('partial payment keeps group ACTIVE when PARTIALLY_PAID status does not exist', function () {
    app(BillingService::class)->recordPayment($this->group, $this->cashier, 10.00, 'Numerário');
    $this->group->refresh();
    expect($this->group->status?->code)->toBe(BillingStatus::ACTIVE)
        ->and($this->group->is_closed)->toBeFalse();
});

it('full payment closes the group but does not release zones', function () {
    app(BillingService::class)->recordPayment($this->group, $this->cashier, 36.00, 'Numerário');
    $this->group->refresh();

    expect($this->group->is_closed)->toBeTrue()
        ->and($this->group->status?->code)->toBe(BillingStatus::CLOSED)
        ->and($this->zone->refresh()->is_open)->toBeTrue();
});

it('reopens a closed group via service', function () {
    app(BillingService::class)->recordPayment($this->group, $this->cashier, 36.00, 'Numerário');
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->cashier);

    $this->group->refresh();
    expect($this->group->is_closed)->toBeFalse()
        ->and($this->group->status?->code)->toBe(BillingStatus::ACTIVE);
});

it('zone release via OccupancyService closes the zone', function () {
    expect($this->zone->is_open)->toBeTrue();

    app(OccupancyService::class)->releaseZone($this->zone, $this->cashier);

    expect($this->zone->refresh()->is_open)->toBeFalse();
});

it('zone release is idempotent', function () {
    app(OccupancyService::class)->releaseZone($this->zone, $this->cashier);
    app(OccupancyService::class)->releaseZone($this->zone->refresh(), $this->cashier);

    expect($this->zone->refresh()->is_open)->toBeFalse();
});

it('recording a payment queues the cash drawer opening for the cashier printer', function () {
    Queue::fake();

    app(BillingService::class)->recordPayment($this->group, $this->cashier, 10.00, 'Numerário');

    Queue::assertPushed(OpenCashDrawerJob::class, function (OpenCashDrawerJob $job) {
        return $job->actorId === $this->cashier->id
            && $job->printerId === CashierPrinterAssignment::where('user_id', $this->cashier->id)
                ->where('is_active', true)
                ->value('printer_id');
    });
});

it('recording a payment still succeeds when the cashier has no printer assignment', function () {
    Queue::fake();

    CashierPrinterAssignment::where('user_id', $this->cashier->id)->delete();

    $payment = app(BillingService::class)->recordPayment($this->group, $this->cashier, 10.00, 'Numerário');

    expect($payment->exists)->toBeTrue();
    Queue::assertNotPushed(OpenCashDrawerJob::class);
});
