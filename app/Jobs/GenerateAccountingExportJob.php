<?php

namespace App\Jobs;

use App\Domain\Accounting\AccountingExportService;
use App\Domain\Audit\Audit;
use App\Models\AccountingExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateAccountingExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $accountingExportId,
    ) {}

    public function handle(AccountingExportService $service): void
    {
        $export = AccountingExport::findOrFail($this->accountingExportId);

        Audit::record(
            'EXPORT_REQUESTED',
            "Export #{$export->id} requested",
            ['type' => $export->export_type, 'format' => $export->file_format],
            ['accounting_export_id' => $export->id, 'actor_user_id' => $export->requested_by_user_id],
        );

        try {
            $filePath = $service->generate($export);

            $export->update([
                'file_name' => $filePath,
                'export_status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'EXPORT_COMPLETED',
                "Export #{$export->id} completed: {$filePath}",
                ['file_path' => $filePath],
                ['accounting_export_id' => $export->id, 'actor_user_id' => $export->requested_by_user_id],
            );
        } catch (Throwable $e) {
            $export->update([
                'export_status' => 'FAILED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'EXPORT_FAILED',
                "Export #{$export->id} failed: {$e->getMessage()}",
                ['error' => $e->getMessage()],
                ['accounting_export_id' => $export->id, 'actor_user_id' => $export->requested_by_user_id],
            );

            throw $e;
        }
    }
}
