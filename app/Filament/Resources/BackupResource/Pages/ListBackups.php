<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListBackups extends ListRecords
{
    protected static string $resource = BackupResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (Auth::user()?->can('backup.export_config') || Auth::user()?->can('backup.export_full')) {
            $actions[] = Actions\CreateAction::make();
        }

        if (Auth::user()?->can('backup.import')) {
            $actions[] = Actions\Action::make('import')
                ->label(__('app.backup_import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->url(BackupResource::getUrl('import'));
        }

        return $actions;
    }
}
