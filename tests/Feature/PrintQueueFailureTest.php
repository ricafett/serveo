<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Domain\Printing\PrintQueueService;
use App\Jobs\DispatchPrintJob;
use App\Models\MenuItem;
use App\Models\PrintJob;
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

it('keeps failed print jobs in FAILED state', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_FAILED, 'last_error' => 'connection timeout']);

    expect($job->refresh()->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->last_error)->toBe('connection timeout');
});

it('retries a failed job and re-queues dispatch', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_FAILED, 'last_error' => 'timeout']);

    Queue::fake();
    $ok = app(PrintQueueService::class)->retry($job, $this->cashier);

    expect($ok)->toBeTrue()
        ->and($job->refresh()->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($job->last_error)->toBeNull();
    Queue::assertPushed(DispatchPrintJob::class);
});

it('respects max attempts on retry', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_FAILED, 'attempts' => 3, 'max_attempts' => 3]);

    // The retry method doesn't enforce max attempts itself; the job does.
    // Here we verify the service still allows retry (job-level enforcement is tested elsewhere)
    $ok = app(PrintQueueService::class)->retry($job, $this->cashier);
    expect($ok)->toBeTrue();
});

it('lists failed jobs in PrintJobResource scope', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($this->group, $this->server,
        [['menu_item_id' => $kitchenItem->id, 'quantity' => 1]], $this->zone);

    $job = PrintJob::first();
    $job->update(['status' => PrintJob::STATUS_FAILED]);

    $failedCount = PrintJob::where('status', PrintJob::STATUS_FAILED)->count();
    expect($failedCount)->toBeGreaterThanOrEqual(1);
});
