<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintResult;
use App\Jobs\DispatchPrintJob;
use App\Models\AuditEvent;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->printer = Printer::first();
    Auth::login($this->server);

    Cache::store('array')->flush();
});

// ─── Helpers ──────────────────────────────────────────────────────────

function makePendingJob(Printer $printer, ProductionTicket $ticket, int $attempts = 0, int $maxAttempts = 4, string $status = 'PENDING'): PrintJob
{
    return PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => $status,
        'attempts' => $attempts,
        'max_attempts' => $maxAttempts,
        'requested_by_user_id' => auth()->id(),
    ]);
}

function makeTicket(): ProductionTicket
{
    return ProductionTicket::create([
        'service_session_id' => test()->session->id,
        'billing_group_id' => test()->group->id,
        'printer_id' => test()->printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => auth()->id(),
    ]);
}

// ─── Atomic claim ─────────────────────────────────────────────────────

it('atomic claim: PENDING job is claimed and processed', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->attempts)->toBe(1);
});

it('atomic claim: PRINTED job is skipped (claim fails, returns immediately)', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);
    $job->update(['status' => PrintJob::STATUS_PRINTED]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED);
});

it('atomic claim: CANCELED job is skipped', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);
    $job->update(['status' => PrintJob::STATUS_CANCELED]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    expect($job->refresh()->status)->toBe(PrintJob::STATUS_CANCELED);
});

it('atomic claim: FAILED job with attempts < max IS claimable (retry)', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 1, maxAttempts: 4, status: PrintJob::STATUS_FAILED);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->attempts)->toBe(2);
});

it('atomic claim: FAILED job with attempts >= max is NOT claimable', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 4, maxAttempts: 4, status: PrintJob::STATUS_FAILED);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->attempts)->toBe(4);
});

it('atomic claim: only ONE worker can claim the same PENDING job', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);

    $claimed1 = PrintJob::where('id', $job->id)
        ->where('status', PrintJob::STATUS_PENDING)
        ->update([
            'status' => PrintJob::STATUS_IN_PROGRESS,
            'attempts' => DB::raw('attempts + 1'),
        ]);
    expect($claimed1)->toBe(1);

    $claimed2 = PrintJob::where('id', $job->id)
        ->where('status', PrintJob::STATUS_PENDING)
        ->update([
            'status' => PrintJob::STATUS_IN_PROGRESS,
            'attempts' => DB::raw('attempts + 1'),
        ]);
    expect($claimed2)->toBe(0);
});

it('atomic claim: PENDING job with attempts >= max is NOT claimed', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 4, maxAttempts: 4);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($job->attempts)->toBe(4);
});

// ─── Lock contention ──────────────────────────────────────────────────

it('lock contention: reverts to PENDING and re-dispatches', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::contended());

    Queue::fake();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($job->last_error)->toContain('busy');

    Queue::assertPushed(DispatchPrintJob::class, function (DispatchPrintJob $dispatched) use ($job) {
        return $dispatched->printJobId === $job->id
            && $dispatched->lockContentionAttempt === 1;
    });
});

it('lock contention: attempt counter is not incremented (decremented back)', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 0);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::contended());

    Queue::fake();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->attempts)->toBe(0);
});

it('lock contention: does NOT count toward max_attempts after multiple contentions', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 0, maxAttempts: 4);

    for ($i = 0; $i < 3; $i++) {
        $registry = Mockery::mock(PrinterAdapterRegistry::class);
        $registry->shouldReceive('send')->once()->andReturn(PrintResult::contended());
        Queue::fake();
        (new DispatchPrintJob($job->id, lockContentionAttempt: $i))->handle($registry);
        $job->refresh();
        expect($job->attempts)->toBe(0)->and($job->status)->toBe(PrintJob::STATUS_PENDING);
    }
});

it('lock contention: eventually succeeds when lock is released', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);

    // Contention
    $r1 = Mockery::mock(PrinterAdapterRegistry::class);
    $r1->shouldReceive('send')->once()->andReturn(PrintResult::contended());
    Queue::fake();
    (new DispatchPrintJob($job->id))->handle($r1);

    // Success on retry
    $r2 = Mockery::mock(PrinterAdapterRegistry::class);
    $r2->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));
    (new DispatchPrintJob($job->id, lockContentionAttempt: 1))->handle($r2);
    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->attempts)->toBe(1);
});

// ─── Transport failure ────────────────────────────────────────────────

it('transport failure: increments attempts and re-dispatches on non-final attempt', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 0, maxAttempts: 4);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Unreachable'));

    Queue::fake();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->attempts)->toBe(1)
        ->and($job->last_error)->toBe('Unreachable')
        ->and($job->next_attempt_at)->not->toBeNull();

    Queue::assertPushed(DispatchPrintJob::class, function (DispatchPrintJob $dispatched) use ($job) {
        return $dispatched->printJobId === $job->id;
    });
});

it('transport failure: permanently FAILED on final attempt, no re-dispatch', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 3, maxAttempts: 4);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Still unreachable'));

    Queue::fake();

    (new DispatchPrintJob($job->id))->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->attempts)->toBe(4);

    Queue::assertNotPushed(DispatchPrintJob::class);
});

it('transport failure: next dispatch is skipped because attempts >= max blocks claim', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 4, maxAttempts: 4, status: PrintJob::STATUS_FAILED);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    expect($job->refresh()->status)->toBe(PrintJob::STATUS_FAILED);
});

it('transport failure: marks ProductionTicket FAILED on final attempt only', function () {
    $t1 = makeTicket();
    $t2 = makeTicket();

    // Intermediate
    $j1 = makePendingJob($this->printer, $t1, attempts: 0, maxAttempts: 4);
    $r1 = Mockery::mock(PrinterAdapterRegistry::class);
    $r1->shouldReceive('send')->once()->andReturn(PrintResult::fail('Down'));
    Queue::fake();
    (new DispatchPrintJob($j1->id))->handle($r1);
    expect($t1->refresh()->ticket_status)->toBe('PENDING');

    // Final
    $j2 = makePendingJob($this->printer, $t2, attempts: 3, maxAttempts: 4);
    $r2 = Mockery::mock(PrinterAdapterRegistry::class);
    $r2->shouldReceive('send')->once()->andReturn(PrintResult::fail('Down'));
    Queue::fake();
    (new DispatchPrintJob($j2->id))->handle($r2);
    expect($t2->refresh()->ticket_status)->toBe('FAILED');
});

it('transport failure: emits PRODUCTION_TICKET_FAILED and PRINT_JOB_MAX_ATTEMPTS on final', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 3, maxAttempts: 4);

    $beforeMaxAttempts = AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count();

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Gone'));

    (new DispatchPrintJob($job->id))->handle($registry);

    expect(AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')
        ->where('production_ticket_id', $ticket->id)->exists())->toBeTrue();

    // PRINT_JOB_MAX_ATTEMPTS emitted (at least one new event)
    expect(AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count())
        ->toBe($beforeMaxAttempts + 1);
});

it('transport failure: does NOT emit PRINT_JOB_MAX_ATTEMPTS on intermediate failure', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket, attempts: 0, maxAttempts: 4);

    $before = AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count();

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Down'));
    Queue::fake();
    (new DispatchPrintJob($job->id))->handle($registry);

    expect(AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count())->toBe($before);
});

// ─── Render failure handled by real TicketRenderer ──────────────────
// (tested indirectly via DispatchPrintJobAuditTest — a ticket with
//  valid data renders successfully via the real registry+adapter)

// ─── failed() method ──────────────────────────────────────────────────

it('failed(): reverts IN_PROGRESS to FAILED on unhandled exception', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);
    $job->update(['status' => PrintJob::STATUS_IN_PROGRESS]);

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->failed(new RuntimeException('Worker killed'));

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->last_error)->toContain('Worker killed');
});

it('failed(): does not touch jobs that are not IN_PROGRESS', function () {
    $ticket = makeTicket();
    $job = makePendingJob($this->printer, $ticket);
    $job->update(['status' => PrintJob::STATUS_PRINTED, 'last_error' => null]);

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->failed(new RuntimeException('Something'));

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->last_error)->toBeNull();
});

// ─── Backoff with jitter ──────────────────────────────────────────────

it('transport backoff: produces values in expected range (±20% jitter)', function () {
    $job = new DispatchPrintJob(1);
    $ref = new ReflectionMethod(DispatchPrintJob::class, 'transportBackoff');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $expectedBase = min(3 * (int) pow(2, $attempt - 1), 10);
        $minExpected = max(1, (int) round($expectedBase * 0.8));
        $maxExpected = (int) round($expectedBase * 1.2);

        for ($i = 0; $i < 100; $i++) {
            $delay = $ref->invoke($job, $attempt);
            expect($delay)->toBeGreaterThanOrEqual($minExpected)
                ->and($delay)->toBeLessThanOrEqual($maxExpected);
        }
    }
});

it('contention backoff: produces values in expected range (±20% jitter)', function () {
    $ref = new ReflectionMethod(DispatchPrintJob::class, 'contentionBackoff');

    $attempts = [-1 => 1, 0 => 1, 1 => 2, 2 => 4, 3 => 8, 4 => 10, 5 => 10];

    foreach ($attempts as $lockAttempt => $expectedBase) {
        $job = new DispatchPrintJob(1, $lockAttempt);
        $minExpected = max(1, (int) round($expectedBase * 0.8));
        $maxExpected = (int) round($expectedBase * 1.2);

        for ($i = 0; $i < 50; $i++) {
            $delay = $ref->invoke($job);
            expect($delay)->toBeGreaterThanOrEqual($minExpected)
                ->and($delay)->toBeLessThanOrEqual($maxExpected);
        }
    }
});

// ─── Lock key isolation ───────────────────────────────────────────────

it('different printers get different lock keys', function () {
    $printer1 = Printer::first();
    $printer2 = Printer::skip(1)->first();

    if (! $printer2) {
        $printer2 = Printer::create([
            'name' => 'Test Printer 2',
            'connection_type' => Printer::CONN_NULL,
            'is_active' => true,
        ]);
    }

    $key1 = PrinterAdapterRegistry::lockKey($printer1);
    $key2 = PrinterAdapterRegistry::lockKey($printer2);

    expect($key1)->not->toBe($key2)
        ->and($key1)->toContain((string) $printer1->id)
        ->and($key2)->toContain((string) $printer2->id);
});

// ─── PrintResult contended detection ──────────────────────────────────

it('PrintResult::contended() sets contended flag to true', function () {
    $result = PrintResult::contended('Busy');
    expect($result->success)->toBeFalse()
        ->and($result->contended)->toBeTrue()
        ->and($result->message)->toBe('Busy');
});

it('PrintResult::fail() leaves contended flag false', function () {
    $result = PrintResult::fail('Error');
    expect($result->success)->toBeFalse()
        ->and($result->contended)->toBeFalse();
});

it('PrintResult::ok() has contended flag false', function () {
    $result = PrintResult::ok('Done');
    expect($result->success)->toBeTrue()
        ->and($result->contended)->toBeFalse();
});

// ─── Printer lock works with Cache::lock ──────────────────────────────

it('printer lock serializes access to the same printer', function () {
    $lockKey = PrinterAdapterRegistry::lockKey($this->printer);

    $lock = Cache::lock($lockKey, 20);
    $acquired = $lock->get();
    expect($acquired)->toBeTrue();

    $lock2 = Cache::lock($lockKey, 20);
    expect($lock2->get())->toBeFalse();

    $lock->release();
    $lock3 = Cache::lock($lockKey, 20);
    expect($lock3->get())->toBeTrue();
    $lock3->release();
});

// ─── Regression: manual retry (PrintQueueService) still works ────────

it('manual retry resets attempt counter and dispatches', function () {
    Auth::login($this->cashier);
    $ticket = makeTicket();

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_FAILED,
        'attempts' => 4,
        'max_attempts' => 4,
        'last_error' => 'All attempts exhausted',
        'requested_by_user_id' => $this->server->id,
    ]);

    Queue::fake();
    $ok = app(\App\Domain\Printing\PrintQueueService::class)->retry($job, $this->cashier);

    expect($ok)->toBeTrue()
        ->and($job->refresh()->attempts)->toBe(0)
        ->and($job->status)->toBe(PrintJob::STATUS_PENDING);

    Queue::assertPushed(DispatchPrintJob::class);
});
