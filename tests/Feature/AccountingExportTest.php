<?php

use App\Domain\Accounting\AccountingExportService;
use App\Jobs\GenerateAccountingExportJob;
use App\Models\AccountingExport;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    bootScenario();
});

it('has an accounting exports table', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('accounting_exports'))->toBeTrue();
});

it('can create an accounting export record', function () {
    $admin = makeUser('ADMIN');
    $venue = \App\Models\Venue::first();

    $export = AccountingExport::create([
        'venue_id' => $venue->id,
        'service_session_id' => null,
        'export_type' => 'SESSION_SUMMARY',
        'export_range_start' => now()->subDay(),
        'export_range_end' => now(),
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $admin->id,
        'requested_at' => now(),
    ]);

    expect($export->id)->not->toBeNull()
        ->and($export->export_status)->toBe('REQUESTED');
});

it('generates a csv export via service', function () {
    $admin = makeUser('ADMIN');
    $venue = \App\Models\Venue::first();
    $session = \App\Models\ServiceSession::first();
    $status = \App\Models\BillingStatus::where('code', 'ACTIVE')->first();

    $group = BillingGroup::create([
        'service_session_id' => $session->id,
        'display_code' => 'G-TEST',
        'billing_status_id' => $status->id,
        'opened_by_user_id' => $admin->id,
        'opened_at' => now(),
        'version_number' => 1,
    ]);

    $row = \App\Models\Row::first();
    OccupiedZone::create([
        'billing_group_id' => $group->id,
        'row_id' => $row->id,
        'start_seat_pair_sequence' => 1,
        'end_seat_pair_sequence' => 3,
        'default_delivery_mode' => 'CENTER',
        'opened_at' => now(),
        'is_open' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $order = OrderHeader::create([
        'billing_group_id' => $group->id,
        'ordered_by_user_id' => $admin->id,
        'ordered_at' => now(),
        'submission_status' => 'SUBMITTED',
    ]);

    $menuItem = \App\Models\MenuItem::first();
    OrderItem::create([
        'order_header_id' => $order->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => 2,
        'unit_price' => 10.00,
        'line_subtotal' => 20.00,
        'fulfillment_route' => 'KITCHEN',
    ]);

    PaymentRecord::create([
        'billing_group_id' => $group->id,
        'recorded_by_user_id' => $admin->id,
        'recorded_at' => now(),
        'amount' => 15.00,
        'payment_label' => 'CASH',
    ]);

    $export = AccountingExport::create([
        'venue_id' => $venue->id,
        'service_session_id' => $session->id,
        'export_type' => 'SESSION_SUMMARY',
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $admin->id,
        'requested_at' => now(),
    ]);

    $service = new AccountingExportService();
    $path = $service->generate($export);

    expect($path)->toBe("exports/accounting_export_{$export->id}.csv")
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    $content = Storage::disk('local')->get($path);
    expect($content)->toContain('billing_group_code')
        ->toContain('G-TEST')
        ->toContain('20.00')
        ->toContain('15.00')
        ->toContain('5.00')
        ->toContain('CASH');
});

it('dispatches generate job from api and completes export', function () {
    $admin = makeUser('ADMIN');
    $session = bootScenario();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/accounting-exports', [
            'serviceSessionId' => $session->id,
            'exportType' => 'SESSION_SUMMARY',
            'fileFormat' => 'CSV',
        ])
        ->assertCreated()
        ->assertJsonPath('data.exportStatus', 'REQUESTED');

    $export = AccountingExport::latest()->first();
    expect($export)->not->toBeNull()
        ->and($export->export_status)->toBe('COMPLETED')
        ->and($export->file_name)->not->toBeNull();

    $audit = \App\Models\AuditEvent::where('accounting_export_id', $export->id)
        ->where('event_type', 'EXPORT_COMPLETED')
        ->first();
    expect($audit)->not->toBeNull();
});

it('allows api download for completed exports', function () {
    $admin = makeUser('ADMIN');
    $session = bootScenario();

    $export = AccountingExport::create([
        'venue_id' => \App\Models\Venue::first()->id,
        'service_session_id' => $session->id,
        'export_type' => 'SESSION_SUMMARY',
        'file_format' => 'CSV',
        'export_status' => 'COMPLETED',
        'file_name' => 'exports/test.csv',
        'requested_by_user_id' => $admin->id,
        'requested_at' => now(),
        'completed_at' => now(),
    ]);

    Storage::disk('local')->put('exports/test.csv', "header,code\nval,1");

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/admin/accounting-exports/{$export->id}/download")
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('rejects api download for incomplete exports', function () {
    $admin = makeUser('ADMIN');
    $session = bootScenario();

    $export = AccountingExport::create([
        'venue_id' => \App\Models\Venue::first()->id,
        'service_session_id' => $session->id,
        'export_type' => 'SESSION_SUMMARY',
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $admin->id,
        'requested_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/accounting-exports/{$export->id}/download")
        ->assertNotFound();
});

it('marks export as failed when service throws', function () {
    $admin = makeUser('ADMIN');
    $session = bootScenario();

    $export = AccountingExport::create([
        'venue_id' => \App\Models\Venue::first()->id,
        'service_session_id' => $session->id,
        'export_type' => 'SESSION_SUMMARY',
        'file_format' => 'CSV',
        'export_status' => 'REQUESTED',
        'requested_by_user_id' => $admin->id,
        'requested_at' => now(),
    ]);

    app()->bind(AccountingExportService::class, function () {
        return new class extends AccountingExportService {
            public function generate(\App\Models\AccountingExport $export): string
            {
                throw new \RuntimeException('Simulated failure');
            }
        };
    });

    $job = new GenerateAccountingExportJob($export->id);
    try {
        $job->handle(app(AccountingExportService::class));
    } catch (\Throwable $e) {
        // Expected
    }

    $export->refresh();
    expect($export->export_status)->toBe('FAILED');

    $audit = \App\Models\AuditEvent::where('accounting_export_id', $export->id)
        ->where('event_type', 'EXPORT_FAILED')
        ->first();
    expect($audit)->not->toBeNull();
});

it('queues export generation job from api store', function () {
    $admin = makeUser('ADMIN');
    $session = bootScenario();

    Queue::fake();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/accounting-exports', [
            'serviceSessionId' => $session->id,
            'exportType' => 'FULL_LEDGER',
            'fileFormat' => 'CSV',
        ])
        ->assertCreated();

    Queue::assertPushed(GenerateAccountingExportJob::class);
});
