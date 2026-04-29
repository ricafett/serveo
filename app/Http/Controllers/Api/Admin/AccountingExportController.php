<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Audit\Audit;
use App\Http\Controllers\ApiController;
use App\Models\AccountingExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingExportController extends ApiController
{
    public function index(): JsonResponse
    {
        $exports = AccountingExport::orderBy('requested_at', 'desc')->get();

        return $this->success($exports->map(fn ($e) => [
            'accountingExportId' => $e->id,
            'exportType'         => $e->export_type,
            'fileFormat'         => $e->file_format,
            'exportStatus'       => $e->export_status,
            'requestedAt'        => $e->requested_at?->toIso8601String(),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serviceSessionId' => ['nullable', 'exists:service_sessions,id'],
            'exportType'       => ['required', 'string', 'in:ACCOUNTING_SUMMARY'],
            'fileFormat'       => ['required', 'string', 'in:CSV,JSON'],
        ]);

        $export = AccountingExport::create([
            'venue_id'           => \App\Models\Venue::first()?->id,
            'service_session_id' => $validated['serviceSessionId'] ?? null,
            'export_type'        => $validated['exportType'],
            'file_format'        => $validated['fileFormat'],
            'export_status'      => 'REQUESTED',
            'requested_by_user_id' => $request->user()->id,
            'requested_at'       => now(),
        ]);

        Audit::record(
            'EXPORT_REQUESTED',
            "Exportação #{$export->id} solicitada",
            ['type' => $export->export_type, 'format' => $export->file_format],
            ['accounting_export_id' => $export->id],
        );

        return $this->success([
            'accountingExportId' => $export->id,
            'exportStatus'       => $export->export_status,
        ], status: 201);
    }

    public function show(AccountingExport $accountingExport): JsonResponse
    {
        return $this->success([
            'accountingExportId' => $accountingExport->id,
            'exportType'         => $accountingExport->export_type,
            'fileFormat'         => $accountingExport->file_format,
            'exportStatus'       => $accountingExport->export_status,
            'fileName'           => $accountingExport->file_name,
            'requestedAt'        => $accountingExport->requested_at?->toIso8601String(),
            'completedAt'        => $accountingExport->completed_at?->toIso8601String(),
        ]);
    }

    public function download(AccountingExport $accountingExport): JsonResponse
    {
        if (! $accountingExport->file_name || $accountingExport->export_status !== 'COMPLETED') {
            return $this->error('NOT_FOUND', 'Export file not ready.', status: 404);
        }

        // MVP placeholder: in real implementation this would stream the file.
        return $this->error('NOT_FOUND', 'Export download not yet implemented.', status: 404);
    }
}
