<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingExportResource\Pages;
use App\Models\AccountingExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AccountingExportResource extends BaseResource
{
    protected static ?string $model = AccountingExport::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'app.navigation_label_accounting_exports';

    protected static ?string $modelLabel = 'app.model_label_accounting_export';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_accounting_exports';

    protected static ?int $navigationSort = 95;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('accounting_export.generate') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('accounting_export.generate') ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('service_session_id')
                ->relationship('serviceSession', 'session_label')
                ->label(__('app.service_session'))
                ->nullable(),
            Forms\Components\Select::make('export_type')
                ->options([
                    'SESSION_SUMMARY' => __('app.type_session_summary'),
                    'FULL_LEDGER' => __('app.type_full_ledger'),
                ])
                ->required()
                ->default('SESSION_SUMMARY'),
            Forms\Components\Select::make('source_domain')
                ->options([
                    'ALL' => __('app.export_source_all'),
                    'BILLING' => __('app.export_source_billing'),
                    'SALES' => __('app.export_source_sales'),
                ])
                ->required()
                ->default('ALL'),
            Forms\Components\DateTimePicker::make('export_range_start')
                ->label(__('app.range_start'))
                ->nullable(),
            Forms\Components\DateTimePicker::make('export_range_end')
                ->label(__('app.range_end'))
                ->nullable(),
            Forms\Components\Select::make('file_format')
                ->options(['CSV' => 'CSV'])
                ->required()
                ->default('CSV'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('export_type')->badge()->label(__('app.type')),
                Tables\Columns\TextColumn::make('source_domain')->badge()->label(__('app.source_domain')),
                Tables\Columns\TextColumn::make('export_range_start')->dateTime()->label(__('app.start')),
                Tables\Columns\TextColumn::make('export_range_end')->dateTime()->label(__('app.end')),
                Tables\Columns\TextColumn::make('file_format')->label(__('app.format')),
                Tables\Columns\TextColumn::make('export_status')->badge()->colors([
                    'warning' => 'REQUESTED',
                    'success' => 'COMPLETED',
                    'danger' => 'FAILED',
                ])->label(__('app.status')),
                Tables\Columns\TextColumn::make('requestedBy.name')->label(__('app.requested_by')),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->label(__('app.completed')),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('download')
                    ->label(__('app.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (AccountingExport $record) => $record->export_status === 'COMPLETED' && $record->file_name)
                    ->url(fn (AccountingExport $record) => route('accounting-export.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingExports::route('/'),
            'create' => Pages\CreateAccountingExport::route('/create'),
        ];
    }
}
