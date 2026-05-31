<?php

use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrintResult;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Jobs\DispatchPrintJob;
use App\Models\AuditEvent;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Auth;
use Tests\Traits\DelegatesProbeToSend;

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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->cashier->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        use DelegatesProbeToSend;

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
        'max_attempts' => 3,
        'requested_by_user_id' => $this->cashier->id,
    ]);

    $adapter = new class implements PrinterAdapter
    {
        use DelegatesProbeToSend;

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

    (new DispatchPrintJob($job->id))->handle($registry);

    expect(AuditEvent::where('event_type', 'SALE_RECEIPT_PRINTED')->where('sale_document_id', $document->id)->exists())->toBeTrue()
        ->and($document->refresh()->document_status)->toBe('PRINTED');
});
