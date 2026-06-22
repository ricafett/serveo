<?php

use App\Domain\Sales\VoucherSaleService;
use App\Models\AuditEvent;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Domain\Printing\TicketRenderer;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->session = bootScenario();
    $this->cashier = makeUser('CASHIER');
    $this->printer = Printer::where('is_active', true)->firstOrFail();

    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $this->printer->id],
        ['is_active' => true],
    );

    $this->voucherItem = MenuItem::where('display_name', 'Bacalhau')->firstOrFail();
    $this->voucherItem->update(['is_voucher_enabled' => true]);

    Queue::fake();
});

it('completes a sale with vouchers, payment, print jobs, and audit events', function () {
    $sale = app(VoucherSaleService::class)->complete(
        $this->session,
        $this->cashier,
        [['menu_item_id' => $this->voucherItem->id, 'quantity' => 2]],
        36.00,
        'Cash',
        'Counter sale',
        true,
    );

    expect($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->payments)->toHaveCount(1)
        ->and((float) $sale->total_amount)->toBe(36.00);

    expect($sale->documents()->where('document_type', SaleDocument::TYPE_VOUCHER)->count())->toBe(2)
        ->and($sale->documents()->where('document_type', SaleDocument::TYPE_RECEIPT)->count())->toBe(1)
        ->and(PrintJob::where('printable_type', SaleDocument::class)->count())->toBe(2); // 1 batch voucher + 1 receipt

    expect(AuditEvent::where('event_type', 'SALE_COMPLETED')->where('sale_id', $sale->id)->exists())->toBeTrue()
        ->and(AuditEvent::where('event_type', 'SALE_PAYMENT_RECORDED')->where('sale_id', $sale->id)->exists())->toBeTrue()
        ->and(AuditEvent::where('event_type', 'SALE_VOUCHER_QUEUED')->where('sale_id', $sale->id)->count())->toBe(2)
        ->and(AuditEvent::where('event_type', 'SALE_RECEIPT_QUEUED')->where('sale_id', $sale->id)->count())->toBe(1);
});

it('groups voucher documents when sale voucher config groups items', function () {
    DocumentPrintConfig::updateOrCreate(
        ['document_type' => DocumentPrintConfig::DOC_SALE_VOUCHER, 'fulfillment_route' => null],
        ['group_items' => true, 'ignore_variants' => true, 'ignore_modifiers' => true, 'is_active' => true],
    );

    $sale = app(VoucherSaleService::class)->complete(
        $this->session,
        $this->cashier,
        [['menu_item_id' => $this->voucherItem->id, 'quantity' => 3]],
        54.00,
        'Cash',
    );

    $voucherDocuments = $sale->documents()->where('document_type', SaleDocument::TYPE_VOUCHER)->get();

    expect($voucherDocuments)->toHaveCount(1)
        ->and($voucherDocuments->first()->quantity)->toBe(3);
});

it('rejects sales that do not fully cover the total before printing', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Payment amount must cover the full sale total before printing vouchers.');

    app(VoucherSaleService::class)->complete(
        $this->session,
        $this->cashier,
        [['menu_item_id' => $this->voucherItem->id, 'quantity' => 2]],
        10.00,
        'Cash',
    );
});

it('rejects menu items that are not voucher enabled', function () {
    $this->voucherItem->update(['is_voucher_enabled' => false]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage("Item {$this->voucherItem->display_name} is not voucher-enabled.");

    app(VoucherSaleService::class)->complete(
        $this->session,
        $this->cashier,
        [['menu_item_id' => $this->voucherItem->id, 'quantity' => 1]],
        18.00,
        'Cash',
    );
});

it('renders branding headers on voucher and receipt documents', function () {
    $sale = app(VoucherSaleService::class)->complete(
        $this->session,
        $this->cashier,
        [['menu_item_id' => $this->voucherItem->id, 'quantity' => 1]],
        18.00,
        'Cash',
        printReceipt: true,
    );

    $voucher = $sale->documents()->where('document_type', SaleDocument::TYPE_VOUCHER)->firstOrFail();
    $receipt = $sale->documents()->where('document_type', SaleDocument::TYPE_RECEIPT)->firstOrFail();

    $voucherConfig = DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_SALE_VOUCHER)->firstOrFail();
    $receiptConfig = DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_SALE_RECEIPT)->firstOrFail();

    $voucherOutput = new TicketRenderer(documentConfig: $voucherConfig)->renderSaleDocument($voucher);
    $receiptOutput = new TicketRenderer(documentConfig: $receiptConfig)->renderSaleDocument($receipt);

    expect($voucherConfig->branding_header)->toBe(DocumentPrintConfig::defaultBrandingHeader())
        ->and($receiptConfig->branding_header)->toBe(DocumentPrintConfig::defaultBrandingHeader())
        ->and($voucherOutput)->toContain(DocumentPrintConfig::defaultBrandingHeader())
        ->and($receiptOutput)->toContain(DocumentPrintConfig::defaultBrandingHeader());
});
