<?php

namespace App\Console\Commands;

use App\Jobs\GenerateBackupJob;
use App\Models\Backup;
use Illuminate\Console\Command;

/**
 * Create a database backup via pg_dump and dispatch to the queue.
 *
 * By default, the job is dispatched to the queue. Use --sync to run
 * synchronously (useful for testing or when the queue worker is down).
 *
 * Examples:
 *   php artisan backup:create --type=config
 *   php artisan backup:create --type=full
 *   php artisan backup:create --type=config --sync
 */
class CreateBackup extends Command
{
    protected $signature = 'backup:create
                            {--type=config : Backup type (config or full)}
                            {--sync : Run the backup job synchronously instead of queuing}';

    protected $description = 'Create a config or full database backup';

    public function handle(): int
    {
        $type = $this->option('type');

        if (! in_array($type, ['config', 'full'], true)) {
            $this->error("Invalid backup type '{$type}'. Use 'config' or 'full'.");

            return self::FAILURE;
        }

        $backup = Backup::create([
            'backup_type' => $type,
            'backup_status' => 'REQUESTED',
            'requested_by_user_id' => 1,
            'requested_at' => now(),
        ]);

        $this->info("Backup #{$backup->id} ({$type}) created.");

        if ($this->option('sync')) {
            $this->info('Running synchronously...');

            try {
                GenerateBackupJob::dispatchSync($backup->id);
                $backup->refresh();

                $this->info("Status: {$backup->backup_status}");
                $this->info("File: {$backup->file_name}");
                $this->info("Size: " . ($backup->file_size ? round($backup->file_size / 1024, 1) . ' KB' : 'N/A'));

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $backup->update([
                    'backup_status' => 'FAILED',
                    'completed_at' => now(),
                ]);

                $this->error('Backup failed: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        GenerateBackupJob::dispatch($backup->id);
        $this->info('Job dispatched. Monitor status via: php artisan backup:status');

        return self::SUCCESS;
    }
}
