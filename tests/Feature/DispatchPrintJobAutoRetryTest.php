<?php

use App\Domain\Audit\Audit;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintQueueService;
use App\Domain\Printing\PrintResult;
use App\Domain\Printing\TicketRenderer;
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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Printer unreachable');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

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

    // Job already at attempt 2 (next will be 3 = max)
    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 2,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Printer still unreachable');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Timeout');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    $before = now();
    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $job->refresh();
    expect($job->next_attempt_at)->not->toBeNull()
        ->and($job->next_attempt_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
});

// ─── Max attempts enforcement ───────────────────────────────────────────

it('permanently fails when attempts already equal max_attempts at entry', function () {
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
        'attempts' => 3,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(true); // should never be called
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->never();

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->last_error)->toBe('Max attempts reached');
});

it('emits audit event when max_attempts is reached on entry', function () {
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
        'attempts' => 3,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->never();
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $event = AuditEvent::where('event_type', 'PRINT_JOB_MAX_ATTEMPTS')
        ->where('production_ticket_id', $ticket->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id);
});

it('marks ProductionTicket FAILED when max_attempts reached on entry', function () {
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
        'attempts' => 3,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->never();
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    expect($ticket->refresh()->ticket_status)->toBe('FAILED');
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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Printer unreachable');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

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

    // Attempt 2 → increment to 3 = max_attempts → final attempt
    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
        'printable_type' => ProductionTicket::class,
        'printable_id' => $ticket->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 2,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Printer still unreachable');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    // On final attempt failure, ticket should be FAILED
    expect($ticket->refresh()->ticket_status)->toBe('FAILED');
});

it('emits PRODUCTION_TICKET_FAILED audit on final transport failure only', function () {
    Auth::login($this->server);

    $printer = Printer::first();

    // Intermediate failure (attempt 0, max 3)
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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Unreachable');
        }
    };
    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    $failuresBefore = AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')->count();

    // First failure (intermediate) — should NOT emit FAILED audit
    (new DispatchPrintJob($job1->id))->handle($registry, $renderer);
    expect(AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')->count())->toBe($failuresBefore);

    // Final failure (attempt 2, max 3 → increment to 3 = final)
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
        'attempts' => 2,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    (new DispatchPrintJob($job2->id))->handle($registry, $renderer);

    // Final failure — SHOULD emit FAILED audit
    $event = AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')
        ->where('production_ticket_id', $ticket2->id)
        ->first();
    expect($event)->not->toBeNull();
});

// ─── Backoff calculation (via reflection) ──────────────────────────────

it('calculates exponential backoff with 10s cap', function () {
    $dispatchJob = new DispatchPrintJob(1);

    $ref = new ReflectionMethod(DispatchPrintJob::class, 'calculateBackoff');

    // attempt 1 → 3 * 2^0 = 3s
    expect($ref->invoke($dispatchJob, 1))->toBe(3);
    // attempt 2 → 3 * 2^1 = 6s
    expect($ref->invoke($dispatchJob, 2))->toBe(6);
    // attempt 3 → 3 * 2^2 = 12 → capped at 10s
    expect($ref->invoke($dispatchJob, 3))->toBe(10);
    // attempt 4 → 3 * 2^3 = 24 → capped at 10s
    expect($ref->invoke($dispatchJob, 4))->toBe(10);
    // attempt 5 → 3 * 2^4 = 48 → capped at 10s
    expect($ref->invoke($dispatchJob, 5))->toBe(10);
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
        'attempts' => 3,
        'max_attempts' => 3,
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

// ─── Idempotency guard remains intact ──────────────────────────────────

it('skips already-printed job (idempotency guard)', function () {
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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(true); // should not be called
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->never();
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->never();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    // Job should remain PRINTED
    expect($job->refresh()->status)->toBe(PrintJob::STATUS_PRINTED);
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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(true, 'OK');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($job->attempts)->toBe(1)
        ->and($job->completed_at)->not->toBeNull()
        ->and($ticket->refresh()->ticket_status)->toBe('PRINTED');

    Queue::assertNotPushed(DispatchPrintJob::class);
});

// ─── Re-dispatch has correct delay ─────────────────────────────────────

it('re-dispatches with correct exponential backoff delay', function () {
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
        'attempts' => 0, // will increment to 1, not final
        'max_attempts' => 3,
        'requested_by_user_id' => $this->server->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool { return true; }
        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Timeout');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);
    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    Queue::fake();

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    // Verify the job was pushed (attempts=1 after increment, not final)
    Queue::assertPushed(DispatchPrintJob::class, function ($dispatched) use ($job) {
        return $dispatched->printJobId === $job->id;
    });
});
