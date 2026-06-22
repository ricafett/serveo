<?php

use App\Domain\Printing\PrintResult;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Jobs\DispatchPrintJob;
use App\Jobs\OpenCashDrawerJob;
use App\Models\AuditEvent;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->session = bootScenario();
    $this->cashier = makeUser('CASHIER');
    $this->printer = Printer::firstOrFail();

    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $this->printer->id],
        ['is_active' => true],
    );

    $item = MenuItem::where('display_name', 'Bacalhau')->firstOrFail();

    $this->sale = Sale::create([
        'service_session_id' => $this->session->id,
        'display_code' => 'S-TEST-0001',
        'sold_by_user_id' => $this->cashier->id,
        'subtotal_amount' => 18.00,
        'total_amount' => 18.00,
        'payment_label' => 'Cash',
        'sold_at' => now(),
    ]);

    $this->saleItem = SaleItem::create([
        'sale_id' => $this->sale->id,
        'menu_item_id' => $item->id,
        'display_name_snapshot' => $item->display_name,
        'unit_price' => 18.00,
        'quantity' => 1,
        'line_subtotal' => 18.00,
    ]);

    SalePayment::create([
        'sale_id' => $this->sale->id,
        'recorded_by_user_id' => $this->cashier->id,
        'recorded_at' => now(),
        'amount' => 18.00,
        'payment_label' => 'Cash',
        'is_voided' => false,
    ]);
});

it('emits SALE_VOUCHER_PRINTED on successful sale voucher print', function () {
    Auth::login($this->cashier);

    $document = SaleDocument::create([
        'sale_id' => $this->sale->id,
        'sale_item_id' => $this->saleItem->id,
        'printer_id' => $this->printer->id,
        'document_type' => SaleDocument::TYPE_VOUCHER,
        'document_status' => 'GENERATED',
        'document_number' => 'V-TEST-0001',
        'quantity' => 1,
        'requested_at' => now(),
        'created_by_user_id' => $this->cashier->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_SALE_VOUCHER,
        'printable_type' => SaleDocument::class,
        'printable_id' => $document->id,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->cashier->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    expect(AuditEvent::where('event_type', 'SALE_VOUCHER_PRINTED')->where('sale_document_id', $document->id)->exists())->toBeTrue()
        ->and($document->refresh()->document_status)->toBe('PRINTED');
});

it('emits SALE_RECEIPT_PRINTED on successful sale receipt print', function () {
    Auth::login($this->cashier);

    $document = SaleDocument::create([
        'sale_id' => $this->sale->id,
        'printer_id' => $this->printer->id,
        'document_type' => SaleDocument::TYPE_RECEIPT,
        'document_status' => 'GENERATED',
        'document_number' => 'R-TEST-0001',
        'quantity' => 1,
        'requested_at' => now(),
        'created_by_user_id' => $this->cashier->id,
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_SALE_RECEIPT,
        'printable_type' => SaleDocument::class,
        'printable_id' => $document->id,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->cashier->id,
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    expect(AuditEvent::where('event_type', 'SALE_RECEIPT_PRINTED')->where('sale_document_id', $document->id)->exists())->toBeTrue()
        ->and($document->refresh()->document_status)->toBe('PRINTED');
});

it('prints voucher batch documents individually without using duplicate-copy batching', function () {
    Auth::login($this->cashier);

    $documents = collect([
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0002',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0003',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_SALE_VOUCHER_BATCH,
        'printable_type' => SaleDocument::class,
        'printable_id' => 0,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->cashier->id,
        'payload' => ['document_ids' => $documents->pluck('id')->all()],
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('sendPayloadBatch')
        ->once()
        ->withArgs(function (Printer $printer, array $payloads) use ($documents) {
            return $printer->is($this->printer)
                && count($payloads) === $documents->count()
                && collect($payloads)->every(fn (string $payload) => str_contains($payload, 'Bacalhau'));
        })
        ->andReturn(PrintResult::ok('OK'));
    $registry->shouldReceive('send')->never();
    $registry->shouldReceive('sendBatch')->never();

    (new DispatchPrintJob($job->id))->handle($registry);

    expect($job->refresh()->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($documents->every(fn (SaleDocument $document) => $document->fresh()->document_status === 'PRINTED'))
        ->toBeTrue();
});

it('triggers the cash drawer once before voucher batch printing when enabled', function () {
    Auth::login($this->cashier);
    Queue::fake();

    DocumentPrintConfig::updateOrCreate(
        ['document_type' => DocumentPrintConfig::DOC_SALE_VOUCHER, 'fulfillment_route' => null],
        ['group_items' => false, 'ignore_variants' => true, 'ignore_modifiers' => true, 'ignore_item_notes' => true, 'trigger_cash_drawer' => true, 'is_active' => true],
    );

    $documents = collect([
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0100',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0101',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_SALE_VOUCHER_BATCH,
        'printable_type' => SaleDocument::class,
        'printable_id' => 0,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->cashier->id,
        'payload' => ['document_ids' => $documents->pluck('id')->all()],
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('sendPayloadBatch')
        ->once()
        ->withArgs(function (Printer $printer, array $payloads) use ($documents) {
            Queue::assertPushed(OpenCashDrawerJob::class, 1);

            return $printer->is($this->printer)
                && count($payloads) === $documents->count();
        })
        ->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    Queue::assertPushed(OpenCashDrawerJob::class, function (OpenCashDrawerJob $drawerJob) {
        return $drawerJob->printerId === $this->printer->id
            && $drawerJob->actorId === $this->cashier->id;
    });

    expect($job->fresh()->status)->toBe(PrintJob::STATUS_PRINTED)
        ->and($documents->every(fn (SaleDocument $document) => $document->fresh()->document_status === 'PRINTED'))
        ->toBeTrue();
});

it('does not trigger the cash drawer for voucher batch printing when disabled', function () {
    Auth::login($this->cashier);
    Queue::fake();

    DocumentPrintConfig::updateOrCreate(
        ['document_type' => DocumentPrintConfig::DOC_SALE_VOUCHER, 'fulfillment_route' => null],
        ['group_items' => false, 'ignore_variants' => true, 'ignore_modifiers' => true, 'ignore_item_notes' => true, 'trigger_cash_drawer' => false, 'is_active' => true],
    );

    $documents = collect([
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0200',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
        SaleDocument::create([
            'sale_id' => $this->sale->id,
            'sale_item_id' => $this->saleItem->id,
            'printer_id' => $this->printer->id,
            'document_type' => SaleDocument::TYPE_VOUCHER,
            'document_status' => 'GENERATED',
            'document_number' => 'V-TEST-0201',
            'quantity' => 1,
            'requested_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]),
    ]);

    $job = PrintJob::create([
        'job_kind' => PrintJob::KIND_SALE_VOUCHER_BATCH,
        'printable_type' => SaleDocument::class,
        'printable_id' => 0,
        'printer_id' => $this->printer->id,
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 4,
        'requested_by_user_id' => $this->cashier->id,
        'payload' => ['document_ids' => $documents->pluck('id')->all()],
    ]);

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('sendPayloadBatch')
        ->once()
        ->andReturn(PrintResult::ok('OK'));

    (new DispatchPrintJob($job->id))->handle($registry);

    Queue::assertNotPushed(OpenCashDrawerJob::class);
    expect($job->fresh()->status)->toBe(PrintJob::STATUS_PRINTED);
});
