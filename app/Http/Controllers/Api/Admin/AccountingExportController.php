<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Audit\Audit;
use App\Http\Controllers\ApiController;
use App\Jobs\GenerateAccountingExportJob;
use App\Models\AccountingExport;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AccountingExportController extends ApiController
{
    public function index(): JsonResponse
    {
        $exports = AccountingExport::orderBy('requested_at', 'desc')->get();

        return $this->success($exports->map(fn ($e) => [
            'accountingExportId' => $e->id,
            'exportType' => $e->export_type,
            'fileFormat' => $e->file_format,
            'exportStatus' => $e->export_status,
            'requestedAt' => $e->requested_at?->toIso8601String(),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serviceSessionId' => ['nullable', 'exists:service_sessions,id'],
            'exportType' => ['required', 'string', 'in:SESSION_SUMMARY,FULL_LEDGER'],
            'fileFormat' => ['required', 'string', 'in:CSV'],
            'exportRangeStart' => ['nullable', 'date'],
            'exportRangeEnd' => ['nullable', 'date', 'after_or_equal:exportRangeStart'],
        ]);

        $export = AccountingExport::create([
            'venue_id' => Venue::first()?->id,
            'service_session_id' => $validated['serviceSessionId'] ?? null,
            'export_type' => $validated['exportType'],
            'export_range_start' => $validated['exportRangeStart'] ?? null,
            'export_range_end' => $validated['exportRangeEnd'] ?? null,
            'file_format' => $validated['fileFormat'],
            'export_status' => 'REQUESTED',
            'requested_by_user_id' => $request->user()->id,
            'requested_at' => now(),
        ]);

        GenerateAccountingExportJob::dispatch($export->id);

        Audit::record(
            'EXPORT_REQUESTED',
            "Exportação #{$export->id} solicitada",
            ['type' => $export->export_type, 'format' => $export->file_format],
            ['accounting_export_id' => $export->id, 'actor_user_id' => $request->user()->id],
        );

        return $this->success([
            'accountingExportId' => $export->id,
            'exportStatus' => $export->export_status,
        ], status: 201);
    }

    public function show(AccountingExport $accountingExport): JsonResponse
    {
        return $this->success([
            'accountingExportId' => $accountingExport->id,
            'exportType' => $accountingExport->export_type,
            'fileFormat' => $accountingExport->file_format,
            'exportStatus' => $accountingExport->export_status,
            'fileName' => $accountingExport->file_name,
            'requestedAt' => $accountingExport->requested_at?->toIso8601String(),
            'completedAt' => $accountingExport->completed_at?->toIso8601String(),
        ]);
    }

    public function download(AccountingExport $accountingExport)
    {
        if (! $accountingExport->file_name || $accountingExport->export_status !== 'COMPLETED') {
            return $this->error('NOT_FOUND', 'Export file not ready.', status: 404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($accountingExport->file_name)) {
            return $this->error('NOT_FOUND', 'Export file missing.', status: 404);
        }

        return $disk->download($accountingExport->file_name, basename($accountingExport->file_name), [
            'Content-Type' => 'text/csv',
        ]);
    }
}
