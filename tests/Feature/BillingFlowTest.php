<?php

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\BillingDocument;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\Row;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
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

it('full payment closes the group and releases zones', function () {
    app(BillingService::class)->recordPayment($this->group, $this->cashier, 36.00, 'Numerário');
    $this->group->refresh();

    expect($this->group->is_closed)->toBeTrue()
        ->and($this->group->status?->code)->toBe(BillingStatus::CLOSED)
        ->and($this->zone->refresh()->is_open)->toBeFalse();
});

it('reopens a closed group via service', function () {
    app(BillingService::class)->recordPayment($this->group, $this->cashier, 36.00, 'Numerário');
    app(BillingGroupService::class)->reopen($this->group->refresh(), $this->cashier);

    $this->group->refresh();
    expect($this->group->is_closed)->toBeFalse()
        ->and($this->group->status?->code)->toBe(BillingStatus::ACTIVE);
});
