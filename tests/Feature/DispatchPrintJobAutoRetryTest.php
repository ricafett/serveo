<?php

use App\Domain\Audit\Audit;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintResult;
use App\Domain\Printing\PrintQueueService;
use App\Jobs\DispatchPrintJob;
use App\Models\AuditEvent;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
});

// ─── Auto-retry: re-dispatch on failure ────────────────────────────────

it('re-dispatches itself on transport failure when under max_attempts', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Printer unreachable'));

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Should have re-dispatched itself
    Queue::assertPushed(DispatchPrintJob::class, function (DispatchPrintJob $dispatched) use ($job) {
        return $dispatched->printJobId === $job->id;
    });
});

it('does NOT re-dispatch on transport failure when max_attempts reached', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    // Job at attempt 3, max 4 → claim sets to 4 → 4 >= 4 → final
    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 3,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Printer still unreachable'));

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Should NOT re-dispatch (max_attempts reached)
    Queue::assertNotPushed(DispatchPrintJob::class);
});

// ─── next_attempt_at is set on failure ──────────────────────────────────

it('sets next_attempt_at on transport failure', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Timeout'));

    $before = now();
    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    $job->refresh();
    expect($job->next_attempt_at)->not->toBeNull()
        ->and($job->next_attempt_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
});

// ─── Max attempts enforced by atomic claim ──────────────────────────

it('claim fails silently when attempts already equal max_attempts (job stays PENDING)', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    // attempts=4, max=4 → claim filter: 4 < 4 → false → 0 rows
    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 4,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Job stays PENDING — claim was rejected by the attempts < max_attempts filter.
    // The admin must manually retry (which resets attempts to 0 → PENDING → dispatches).
    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($job->attempts)->toBe(4)
        ->and($ticket->refresh()->ticket_status)->toBe('PENDING');
});

it('does NOT emit PRINT_JOB_MAX_ATTEMPTS on claim rejection (only on transport final)', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 4,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $before = AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count();

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // PRINT_JOB_MAX_ATTEMPTS is emitted on transport failure final, not on claim rejection
    expect(AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')->count())->toBe($before);
});

// ─── Intermediate failure does NOT mark ticket FAILED ──────────────────

it('does NOT mark ProductionTicket FAILED on intermediate transport failure', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Printer unreachable'));

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Ticket should still be PENDING (not FAILED) on intermediate failure
    expect($ticket->refresh()->ticket_status)->toBe('PENDING');
});

it('DOES mark ProductionTicket FAILED on final transport failure', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    // Attempt 3 → claim sets to 4 = max_attempts → final attempt
    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 3,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Printer still unreachable'));

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // On final attempt failure, ticket should be FAILED
    expect($ticket->refresh()->ticket_status)->toBe('FAILED');
});

it('emits PRODUCTION_TICKET_FAILED audit on final transport failure only', function () {
    Auth::login($this->server);

    $printer = Printer::first();

    // Intermediate failure (attempt 0, max 4)
    $ticket1 = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);
    $job1 = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket1->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->andReturn(PrintResult::fail('Unreachable'));

    $failuresBefore = AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')->count();

    // First failure (intermediate) — should NOT emit FAILED audit
    (new DispatchPrintJob($job1->id))->handle($registry);
    expect(AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')->count())->toBe($failuresBefore);

    // Final failure (attempt 3, max 4 → claim sets to 4 = final)
    $ticket2 = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);
    $job2 = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket2->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 3,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    (new DispatchPrintJob($job2->id))->handle($registry);

    // Final failure — SHOULD emit FAILED audit
    $event = AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')
        ->where('production_ticket_id', $ticket2->id)
        ->first();
    expect($event)->not->toBeNull();
});

// ─── Backoff calculation (via reflection) ──────────────────────────────

it('transport backoff produces values in expected range with jitter', function () {
    $dispatchJob = new DispatchPrintJob(1);
    $ref = new ReflectionMethod(DispatchPrintJob::class, 'transportBackoff');

    // Run multiple iterations per attempt to account for jitter
    $attempts = [
        1 => 3,   // base = 3 * 2^0 = 3
        2 => 6,   // base = 3 * 2^1 = 6
        3 => 10,  // base = min(3 * 2^2, 10) = 10
        4 => 10,  // capped at 10
        5 => 10,  // capped at 10
    ];

    foreach ($attempts as $attempt => $expectedBase) {
        $minExpected = max(1, (int) round($expectedBase * 0.8));
        $maxExpected = (int) round($expectedBase * 1.2);

        for ($i = 0; $i < 30; $i++) {
            $delay = $ref->invoke($dispatchJob, $attempt);
            expect($delay)->toBeGreaterThanOrEqual($minExpected)
                ->and($delay)->toBeLessThanOrEqual($maxExpected);
        }
    }
});

// ─── Manual retry resets attempts ──────────────────────────────────────

it('manual retry resets attempt counter to 0', function () {
    Auth::login($this->cashier);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_FAILED,
        'attempts' => 4,
        'max_attempts' => 4,
        'last_error' => 'All attempts exhausted',
        'requested_by_user_id' => $this->server->id,
    ]);

    Queue::fake();
    $ok = app(PrintQueueService::class)->retry($job, $this->cashier);

    expect($ok)->toBeTrue()
        ->and($job->refresh()->attempts)->toBe(0)
        ->and($job->status)->toBe(PrintJob::STATUS_PENDING);
    Queue::assertPushed(DispatchPrintJob::class);
});

// ─── Successful print still works ──────────────────────────────────────

it('successfully prints on first attempt and does not re-dispatch', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->attempts)->toBe(1)
        ->and($job->completed_at)->not->toBeNull()
        ->and($ticket->refresh()->ticket_status)->toBe('PRINTED');

    Queue::assertNotPushed(DispatchPrintJob::class);
});

// ─── Idempotency guard (via atomic claim) ───────────────────────────

it('skips already-printed job (atomic claim fails, returns immediately)', function () {
    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PRINTED',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PRINTED,
        'attempts' => 1,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Job should remain PRINTED
    expect($job->refresh()->status)->toBe(PrintJob::STATUS_PRINTED);
});

// ─── Re-dispatch has correct delay ─────────────────────────────────────

it('re-dispatches with backoff delay on transport failure', function () {
    Auth::login($this->server);

    $printer = Printer::first();
    $ticket = ProductionTicket::create([
        'service_session_id' => $this->session->id,
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'ticket_type' => 'KITCHEN',
        'ticket_status' => 'PENDING',
        'requested_at' => now(),
        'created_by_user_id' => $this->server->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0, // claim increments to 1, not final
        'max_attempts' => 4,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::fail('Timeout'));

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry);

    // Verify the job was pushed (attempts=1 after claim, not final)
    Queue::assertPushed(DispatchPrintJob::class, function ($dispatched) use ($job) {
        return $dispatched->printJobId === $job->id;
    });
});
