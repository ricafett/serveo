<?php

use App\Domain\Accounting\AccountingExportService;
use App\Models\AccountingExport;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->session = bootScenario();
    $this->admin = makeUser('ADMIN');
    $item = \App\Models\MenuItem::where('display_name', 'Bacalhau')->firstOrFail();

    $this->sale = Sale::create([
        'service_session_id' => $this->session->id,
        'display_code' => 'S-EXPORT-0001',
        'sold_by_user_id' => $this->admin->id,
        'subtotal_amount' => 18.00,
        'total_amount' => 18.00,
        'payment_label' => 'Cash',
        'sold_at' => now(),
    ]);

    SaleItem::create([
        'sale_id' => $this->sale->id,
        'menu_item_id' => $item->id,
        'display_name_snapshot' => $item->display_name,
        'unit_price' => 18.00,
        'quantity' => 1,
        'line_subtotal' => 18.00,
    ]);

    SalePayment::create([
        'sale_id' => $this->sale->id,
        'recorded_by_user_id' => $this->admin->id,
        'recorded_at' => now(),
        'amount' => 18.00,
        'payment_label' => 'Cash',
        'is_voided' => false,
    ]);

    Storage::fake('local');
});

it('includes sales rows in accounting exports', function () {
    $export = AccountingExport::create([
        'venue_id' => Venue::first()->id,
        'service_session_id' => $this->session->id,
        'export_type' => 'SESSION_SUMMARY',
        'source_domain' => 'ALL',
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $this->admin->id,
        'requested_at' => now(),
    ]);

    $path = app(AccountingExportService::class)->generate($export);
    $content = Storage::disk('local')->get($path);

    expect($content)->toContain('source_domain')
        ->toContain($this->sale->display_code)
        ->toContain('SALES')
        ->toContain('18.00 Cash');
});

it('can export only sales records when source domain is SALES', function () {
    $export = AccountingExport::create([
        'venue_id' => Venue::first()->id,
        'service_session_id' => $this->session->id,
        'export_type' => 'SESSION_SUMMARY',
        'source_domain' => 'SALES',
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $this->admin->id,
        'requested_at' => now(),
    ]);

    $path = app(AccountingExportService::class)->generate($export);
    $content = Storage::disk('local')->get($path);

    expect($content)->toContain($this->sale->display_code)
        ->not->toContain('BG-');
});
