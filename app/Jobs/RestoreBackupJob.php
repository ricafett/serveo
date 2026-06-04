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

class RestoreBackupJob implements ShouldQueue
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

        if ($backup->backup_status !== 'UPLOADED') {
            throw new \RuntimeException("Backup #{$backup->id} is not in UPLOADED status (current: {$backup->backup_status})");
        }

        if (! $backup->file_name) {
            throw new \RuntimeException("Backup #{$backup->id} has no file attached");
        }

        Audit::record(
            'BACKUP_RESTORE_STARTED',
            "Backup restore #{$backup->id} ({$backup->backup_type}) started",
            ['backup_type' => $backup->backup_type, 'backup_id' => $backup->id, 'file_name' => $backup->file_name],
            ['actor_user_id' => $backup->requested_by_user_id],
        );

        try {
            $service->restore($backup->file_name, $backup->backup_type);

            $backup->update([
                'backup_status' => 'RESTORED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'BACKUP_RESTORE_COMPLETED',
                "Backup restore #{$backup->id} ({$backup->backup_type}) completed successfully",
                ['backup_type' => $backup->backup_type, 'backup_id' => $backup->id, 'file_name' => $backup->file_name],
                ['actor_user_id' => $backup->requested_by_user_id],
            );
        } catch (Throwable $e) {
            $backup->update([
                'backup_status' => 'RESTORE_FAILED',
                'completed_at' => now(),
            ]);

            Audit::record(
                'BACKUP_RESTORE_FAILED',
                "Backup restore #{$backup->id} ({$backup->backup_type}) failed: {$e->getMessage()}",
                ['error' => $e->getMessage(), 'backup_type' => $backup->backup_type, 'backup_id' => $backup->id],
                ['actor_user_id' => $backup->requested_by_user_id],
            );

            throw $e;
        }
    }
}
