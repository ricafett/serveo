<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use App\Jobs\GenerateBackupJob;
use App\Models\Backup;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CreateBackup extends CreateRecord
{
    protected static string $resource = BackupResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('backup_type')
                ->label(__('app.backup_type'))
                ->options(function () {
                    $options = [];
                    if (Auth::user()?->can('backup.export_config')) {
                        $options['config'] = __('app.backup_type_config') . ' — ' . __('app.backup_type_config_desc');
                    }
                    if (Auth::user()?->can('backup.export_full')) {
                        $options['full'] = __('app.backup_type_full') . ' — ' . __('app.backup_type_full_desc');
                    }

                    return $options;
                })
                ->required()
                ->helperText(__('app.backup_type_help')),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['backup_status'] = 'REQUESTED';
        $data['requested_by_user_id'] = Auth::id();
        $data['requested_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Backup $backup */
        $backup = $this->record;

        GenerateBackupJob::dispatch($backup->id);
    }
}
