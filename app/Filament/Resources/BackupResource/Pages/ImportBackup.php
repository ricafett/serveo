<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ImportBackup extends Page
{
    protected static string $resource = BackupResource::class;

    protected static string $view = 'filament.resources.backup-resource.pages.import-backup';

    public ?array $data = [];

    public function mount(): void
    {
        if (! Auth::user()?->can('backup.import')) {
            abort(403);
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('backup_file')
                    ->label(__('app.backup_file'))
                    ->acceptedFileTypes(['application/octet-stream', 'application/gzip', 'application/x-gzip', 'application/sql', 'text/plain'])
                    ->maxSize(512000) // 500 MB
                    ->directory('backups/upload')
                    ->disk('local')
                    ->required()
                    ->helperText(__('app.backup_file_help')),
                Forms\Components\Placeholder::make('warning')
                    ->label(__('app.warning'))
                    ->content(__('app.backup_import_warning'))
                    ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400']),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        if (empty($state['backup_file'])) {
            return;
        }

        // Detect backup type from filename or let user specify
        // For uploaded files, we detect: if filename contains 'config' → config, else → full
        $fileName = $state['backup_file'];
        $backupType = str_contains(basename($fileName), '_config_') ? 'config' : 'full';

        // Move file from temp upload to backups directory
        $targetPath = 'backups/import_' . now()->format('Ymd_His') . '_' . basename($fileName);
        Storage::disk('local')->move($fileName, $targetPath);

        $absolutePath = Storage::disk('local')->path($targetPath);
        $fileSize = file_exists($absolutePath) ? filesize($absolutePath) : null;

        $backup = Backup::create([
            'backup_type' => $backupType,
            'file_name' => $targetPath,
            'file_size' => $fileSize,
            'backup_status' => 'RESTORING',
            'requested_by_user_id' => Auth::id(),
            'requested_at' => now(),
        ]);

        RestoreBackupJob::dispatch($backup->id);

        Notification::make()
            ->title(__('app.backup_restore_dispatched'))
            ->body(__('app.backup_restore_dispatched_body'))
            ->success()
            ->send();

        $this->redirect(BackupResource::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_list')
                ->label(__('app.back'))
                ->icon('heroicon-o-arrow-left')
                ->url(BackupResource::getUrl('index')),
        ];
    }
}
