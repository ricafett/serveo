<?php

use App\Domain\Backup\BackupService;
use App\Filament\Resources\BackupResource\Pages\ImportBackup;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    bootScenario();
    Permission::findOrCreate('backup.import');

    $this->admin = makeUser('ADMIN');
    $this->admin->givePermissionTo('backup.import');

    Storage::fake('local');
});

it('stores uploaded backups as uploaded before dispatching restore', function () {
    Queue::fake();
    Storage::disk('local')->put('backups/upload/01JXWXYZABCDEF.dump', 'fake dump content');

    Livewire::actingAs($this->admin)
        ->test(ImportBackup::class)
        ->set('data.backup_file', ['backups/upload/01JXWXYZABCDEF.dump'])
        ->set('data.backup_file_name', 'serveo_config_20260616_225146.dump')
        ->call('submit');

    Queue::assertPushed(RestoreBackupJob::class);

    $backup = Backup::query()->sole();

    expect($backup->backup_status)->toBe('UPLOADED')
        ->and($backup->backup_type)->toBe('config')
        ->and($backup->file_name)->toStartWith('backups/import_');

    Storage::disk('local')->assertExists($backup->file_name);
});

it('transitions uploaded backups to restoring and then restored', function () {
    $backup = Backup::create([
        'backup_type' => 'config',
        'file_name' => 'backups/import_test.dump',
        'file_size' => 123,
        'backup_status' => 'UPLOADED',
        'requested_by_user_id' => $this->admin->id,
        'requested_at' => now(),
    ]);

    $service = \Mockery::mock(BackupService::class);
    $service->shouldReceive('restore')
        ->once()
        ->with('backups/import_test.dump', 'config');

    app()->instance(BackupService::class, $service);

    (new RestoreBackupJob($backup->id))->handle($service);

    expect($backup->fresh()->backup_status)->toBe('RESTORED');
});

it('throws when restore command exits unsuccessfully without postgres error tokens', function () {
    Storage::disk('local')->put('backups/import_test.dump', 'PGDMPfake dump');

    config()->set('database.connections.sqlite.host', 'localhost');
    config()->set('database.connections.sqlite.port', 5432);
    config()->set('database.connections.sqlite.database', 'serveo');
    config()->set('database.connections.sqlite.username', 'serveo');
    config()->set('database.connections.sqlite.password', 'serveo');

    Process::fake([
        '*' => Process::result('', 'WARNING: errors ignored on restore: 1', 1),
    ]);

    expect(fn () => app(BackupService::class)->restore('backups/import_test.dump', 'full'))
        ->toThrow(\RuntimeException::class, 'Restore failed: WARNING: errors ignored on restore: 1');
});
