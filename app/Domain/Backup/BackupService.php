<?php

namespace App\Domain\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    /**
     * Tables included in a config-only backup. These represent the admin-configured
     * reference data: venue layout, menu catalog, printers, users, roles, translations.
     *
     * Any table NOT in this list is operational data and excluded from config backups.
     *
     * Maintainers: keep this list in sync when adding new config tables.
     *
     * Tables must be ordered so that child tables (with FKs to other config tables)
     * come BEFORE their parent tables. This ensures correct TRUNCATE order during restore.
     */
    public const CONFIG_TABLES = [
        // Child tables first (have FKs to config tables below)
        'menu_item_variants',
        'modifier_set_items',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'seat_pairs',
        'seats',
        'rows',
        'sections',
        'printer_routes',
        'cashier_printer_assignments',
        // Parent tables
        'migrations',
        'settings',
        'billing_statuses',
        'menu_items',
        'menu_categories',
        'modifier_sets',
        'printers',
        'document_print_configs',
        'fulfillment_routes',
        'venues',
        'users',
        'roles',
        'permissions',
        'translation_keys',
    ];

    protected function dbConnectionParams(): array
    {
        $db = config('database.connections.' . config('database.default'));

        return [
            'host' => $db['host'],
            'port' => $db['port'] ?? '5432',
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
        ];
    }

    protected function pgDumpEnv(): array
    {
        $params = $this->dbConnectionParams();

        // pg_dump uses PGPASSWORD env var for non-interactive auth
        return ['PGPASSWORD' => $params['password']];
    }

    /**
     * Generate a database backup and store it on the local disk.
     *
     * @throws RuntimeException
     */
    public function generate(Backup $backup): string
    {
        $driver = config('database.default');

        if ($driver !== 'pgsql') {
            throw new RuntimeException(
                "Backups are only supported with PostgreSQL. Current database driver: {$driver}."
            );
        }

        $params = $this->dbConnectionParams();
        $fileName = $this->buildFileName($backup);
        $diskPath = "backups/{$fileName}";
        $absolutePath = Storage::disk('local')->path($diskPath);

        // Ensure directory exists
        Storage::disk('local')->makeDirectory('backups');

        $env = $this->pgDumpEnv();

        if ($backup->backup_type === 'config') {
            $cmd = $this->buildConfigDumpCommand($params, $absolutePath);
        } else {
            $cmd = $this->buildFullDumpCommand($params, $absolutePath);
        }

        $result = Process::timeout(300)
            ->env($env)
            ->run($cmd);

        if (! $result->successful()) {
            $error = $result->errorOutput();
            throw new RuntimeException("pg_dump failed: {$error}");
        }

        if (! file_exists($absolutePath)) {
            throw new RuntimeException("pg_dump completed but no output file was created at {$absolutePath}");
        }

        return $diskPath;
    }

    /**
     * Restore a database backup from a stored file.
     *
     * - Full restore: uses pg_restore --clean --if-exists to drop and recreate everything.
     * - Config restore: truncates config tables first, then uses --data-only to insert data.
     *   This avoids cascading deletes on operational tables that reference config.
     *
     * @throws RuntimeException
     */
    public function restore(string $filePath, string $backupType): void
    {
        $params = $this->dbConnectionParams();
        $absolutePath = Storage::disk('local')->path($filePath);

        if (! file_exists($absolutePath)) {
            throw new RuntimeException("Backup file not found: {$filePath}");
        }

        $isCustomFormat = $this->isCustomFormatDump($absolutePath);
        $env = $this->pgDumpEnv();

        if ($backupType === 'config') {
            $this->truncateConfigTables();
            $cmd = $this->buildRestoreDataOnlyCommand($params, $absolutePath, $isCustomFormat);
        } else {
            if ($isCustomFormat) {
                $cmd = $this->buildRestoreCustomCommand($params, $absolutePath);
            } else {
                $cmd = $this->buildRestoreSqlCommand($params, $absolutePath);
            }
        }

        $result = Process::timeout(600)
            ->env($env)
            ->run($cmd);

        if (! $result->successful()) {
            $error = $result->errorOutput();
            if (str_contains($error, 'ERROR:') || str_contains($error, 'FATAL:')) {
                throw new RuntimeException("Restore failed: {$error}");
            }
        }
    }

    /**
     * Truncate all config tables in the correct FK order (child tables first).
     * Temporarily disables FK triggers to allow truncation of tables referenced by operational data.
     */
    protected function truncateConfigTables(): void
    {
        DB::statement('SET session_replication_role = \'replica\'');

        foreach (self::CONFIG_TABLES as $table) {
            try {
                DB::statement("TRUNCATE TABLE \"{$table}\" CASCADE");
            } catch (\Throwable $e) {
                // Table might not exist in this deployment; skip silently
            }
        }

        DB::statement('SET session_replication_role = \'origin\'');
    }

    protected function buildFileName(Backup $backup): string
    {
        $timestamp = now()->format('Ymd_His');
        $type = $backup->backup_type;

        return "serveo_{$type}_{$timestamp}.dump";
    }

    protected function buildFullDumpCommand(array $params, string $outputPath): array
    {
        return [
            'pg_dump',
            '--host=' . $params['host'],
            '--port=' . $params['port'],
            '--username=' . $params['username'],
            '--dbname=' . $params['database'],
            '--format=custom',
            '--compress=9',
            '--no-owner',
            '--no-privileges',
            '--file=' . $outputPath,
        ];
    }

    protected function buildConfigDumpCommand(array $params, string $outputPath): array
    {
        $cmd = [
            'pg_dump',
            '--host=' . $params['host'],
            '--port=' . $params['port'],
            '--username=' . $params['username'],
            '--dbname=' . $params['database'],
            '--format=custom',
            '--compress=9',
            '--no-owner',
            '--no-privileges',
            '--file=' . $outputPath,
        ];

        foreach (self::CONFIG_TABLES as $table) {
            $cmd[] = '--table=' . $table;
        }

        return $cmd;
    }

    protected function buildRestoreCustomCommand(array $params, string $filePath): array
    {
        return [
            'pg_restore',
            '--host=' . $params['host'],
            '--port=' . $params['port'],
            '--username=' . $params['username'],
            '--dbname=' . $params['database'],
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--no-comments',
            '--single-transaction',
            $filePath,
        ];
    }

    protected function buildRestoreDataOnlyCommand(array $params, string $filePath, bool $isCustomFormat): array
    {
        if ($isCustomFormat) {
            return [
                'pg_restore',
                '--host=' . $params['host'],
                '--port=' . $params['port'],
                '--username=' . $params['username'],
                '--dbname=' . $params['database'],
                '--data-only',
                '--disable-triggers',
                '--no-owner',
                '--no-privileges',
                '--no-comments',
                '--single-transaction',
                $filePath,
            ];
        }

        // For plain SQL dumps, use psql directly
        return [
            'psql',
            '--host=' . $params['host'],
            '--port=' . $params['port'],
            '--username=' . $params['username'],
            '--dbname=' . $params['database'],
            '--file=' . $filePath,
            '--single-transaction',
            '--set=ON_ERROR_STOP=1',
        ];
    }

    protected function buildRestoreSqlCommand(array $params, string $filePath): array
    {
        return [
            'psql',
            '--host=' . $params['host'],
            '--port=' . $params['port'],
            '--username=' . $params['username'],
            '--dbname=' . $params['database'],
            '--file=' . $filePath,
            '--single-transaction',
            '--set=ON_ERROR_STOP=1',
        ];
    }

    /**
     * Check if a dump file is in PostgreSQL custom format (binary).
     * Custom format dumps start with the magic bytes "PGDMP".
     */
    protected function isCustomFormatDump(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        $header = fread($handle, 5);
        fclose($handle);

        return $header === 'PGDMP';
    }
}
