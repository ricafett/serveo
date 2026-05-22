<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Domain\Printing\PrintQueueService;
use App\Jobs\DispatchPrintJob;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use App\Models\Row;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );
});

it('persists print jobs against a printer for visibility', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    $job = PrintJob::first();
    expect($job)->not->toBeNull()
        ->and($job->printable_type)->toBe(ProductionTicket::class)
        ->and($job->printer_id)->not->toBeNull();
});

it('retries a failed job back to PENDING', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    /** @var PrintJob $job */
    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_FAILED, 'last_error' => 'simulated']);

    Queue::fake();
    $ok = app(PrintQueueService::class)->retry($job, $this->cashier);

    expect($ok)->toBeTrue()
        ->and($job->refresh()->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($job->last_error)->toBeNull();
    Queue::assertPushed(DispatchPrintJob::class);
});

it('refuses to retry a printed job', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    /** @var PrintJob $job */
    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_PRINTED, 'completed_at' => now()]);

    expect(app(PrintQueueService::class)->retry($job))->toBeFalse();
});
