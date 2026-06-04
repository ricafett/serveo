<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Backup\BackupService;
use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 110;

    public function __construct(
        public int $backupId,
    ) {}

    public function handle(BackupService $service): void
    {
        $backup = Backup::findOrFail($this->backupId);

        Audit::record(
            'BACKUP_REQUESTED',
            "Backup #{$backup->id} ({$backup->backup_type}) requested",
            ['backup_type' => $backup->backup_type, 'backup_id' => $backup->id],
            ['actor_user_id' => $backup->requested_by_user_id],
        );

        try {
            $filePath = $service->generate($backup);
            $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            $fileSize = file_exists($absolutePath) ? filesize($absolutePath) : null;

            $backup->update([
                'file_name' => $filePath,
                'file_size' => $fileSize,
                'backup_status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'BACKUP_COMPLETED',
                "Backup #{$backup->id} ({$backup->backup_type}) completed: {$filePath} (" . ($fileSize ? round($fileSize / 1024) . ' KB' : 'unknown size') . ')',
                ['file_path' => $filePath, 'file_size' => $fileSize],
                ['actor_user_id' => $backup->requested_by_user_id],
            );
        } catch (Throwable $e) {
            $backup->update([
                'backup_status' => 'FAILED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'BACKUP_FAILED',
                "Backup #{$backup->id} ({$backup->backup_type}) failed: {$e->getMessage()}",
                ['error' => $e->getMessage()],
                ['actor_user_id' => $backup->requested_by_user_id],
            );

            throw $e;
        }
    }
}
