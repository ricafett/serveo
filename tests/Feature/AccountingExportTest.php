<?php

use App\Models\AccountingExport;

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
