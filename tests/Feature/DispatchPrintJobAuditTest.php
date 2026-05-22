<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintResult;
use App\Domain\Printing\TicketRenderer;
use App\Jobs\DispatchPrintJob;
use App\Models\AuditEvent;
use App\Models\BillingDocument;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
});

it('emits PRODUCTION_TICKET_PRINTED on successful production ticket print', function () {
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
        public function supports(Printer $printer): bool
        {
            return true;
        }

        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(true, 'OK');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $event = AuditEvent::where('event_type', 'PRODUCTION_TICKET_PRINTED')
        ->where('production_ticket_id', $ticket->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id)
        ->and($event->billing_group_id)->toBe($this->group->id);

    expect($ticket->refresh()->ticket_status)->toBe('PRINTED');
});

it('emits BILL_PRINTED on successful bill print', function () {
    Auth::login($this->cashier);

    $printer = Printer::first();

    $bill = BillingDocument::create([
        'billing_group_id' => $this->group->id,
        'printer_id' => $printer->id,
        'document_type' => BillingDocument::TYPE_INTERNAL_BILL,
        'document_status' => 'GENERATED',
        'document_number' => 'B-20260515-0001',
        'subtotal_amount' => 100.00,
        'total_amount' => 100.00,
        'requested_at' => now(),
        'created_by_user_id' => $this->cashier->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_BILL,
        'printable_type' => BillingDocument::class,
        'printable_id' => $bill->id,
        'printer_id' => $printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 3,
        'requested_by_user_id' => $this->cashier->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        public function supports(Printer $printer): bool
        {
            return true;
        }

        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(true, 'OK');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderBill')->andReturn('test-payload');

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $event = AuditEvent::where('event_type', 'BILL_PRINTED')
        ->where('billing_document_id', $bill->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id)
        ->and($event->billing_group_id)->toBe($this->group->id);

    expect($bill->refresh()->document_status)->toBe('PRINTED');
});

it('emits PRODUCTION_TICKET_FAILED on failed production ticket print', function () {
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
        public function supports(Printer $printer): bool
        {
            return true;
        }

        public function send(Printer $printer, string $payload): PrintResult
        {
            return new PrintResult(false, 'Printer unreachable');
        }
    };

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $renderer = Mockery::mock(TicketRenderer::class);
    $renderer->shouldReceive('renderProductionTicket')->andReturn('test-payload');

    $dispatchJob = new DispatchPrintJob($job->id);
    $dispatchJob->handle($registry, $renderer);

    $event = AuditEvent::where('event_type', 'PRODUCTION_TICKET_FAILED')
        ->where('production_ticket_id', $ticket->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id)
        ->and($event->billing_group_id)->toBe($this->group->id);

    expect($ticket->refresh()->ticket_status)->toBe('FAILED');
});
