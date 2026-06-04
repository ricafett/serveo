<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages;
use App\Models\Backup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BackupResource extends BaseResource
{
    protected static ?string $model = Backup::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_system';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationLabel = 'app.navigation_label_backups';

    protected static ?string $modelLabel = 'app.model_label_backup';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_backups';

    protected static ?int $navigationSort = 100;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('backup.export_config')
            || Auth::user()?->can('backup.export_full')
            || Auth::user()?->can('backup.import') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('backup.export_config')
            || Auth::user()?->can('backup.export_full') ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('backup.import') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('backup_type')
                    ->badge()
                    ->label(__('app.backup_type'))
                    ->colors([
                        'info' => 'config',
                        'warning' => 'full',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'config' => __('app.backup_type_config'),
                        'full' => __('app.backup_type_full'),
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('file_name')
                    ->label(__('app.file_name'))
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('file_size')
                    ->label(__('app.file_size'))
                    ->formatStateUsing(fn (?int $state): string => $state ? round($state / 1024, 1) . ' KB' : '—'),
                Tables\Columns\TextColumn::make('backup_status')
                    ->badge()
                    ->label(__('app.status'))
                    ->colors([
                        'gray' => 'REQUESTED',
                        'success' => 'COMPLETED',
                        'success' => 'RESTORED',
                        'warning' => 'RESTORING',
                        'warning' => 'UPLOADED',
                        'danger' => 'FAILED',
                        'danger' => 'RESTORE_FAILED',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'REQUESTED' => __('app.backup_status_requested'),
                        'COMPLETED' => __('app.backup_status_completed'),
                        'FAILED' => __('app.backup_status_failed'),
                        'UPLOADED' => __('app.backup_status_uploaded'),
                        'RESTORING' => __('app.backup_status_restoring'),
                        'RESTORED' => __('app.backup_status_restored'),
                        'RESTORE_FAILED' => __('app.backup_status_restore_failed'),
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label(__('app.requested_by')),
                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime()
                    ->label(__('app.requested_at'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->label(__('app.completed')),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('download')
                    ->label(__('app.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Backup $record) => $record->backup_status === 'COMPLETED' && $record->file_name)
                    ->url(fn (Backup $record) => route('backup.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->label(__('app.delete'))
                    ->visible(fn (Backup $record) => in_array($record->backup_status, ['COMPLETED', 'FAILED', 'UPLOADED', 'RESTORED', 'RESTORE_FAILED']))
                    ->before(function (Backup $record) {
                        if ($record->file_name) {
                            \Illuminate\Support\Facades\Storage::disk('local')->delete($record->file_name);
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
            'create' => Pages\CreateBackup::route('/create'),
            'import' => Pages\ImportBackup::route('/import'),
        ];
    }
}
