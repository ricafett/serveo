<?php

namespace App\Filament\Resources;

use App\Domain\Audit\Audit;
use App\Filament\Resources\AccountingExportResource\Pages;
use App\Jobs\GenerateAccountingExportJob;
use App\Models\AccountingExport;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class AccountingExportResource extends Resource
{
    protected static ?string $model = AccountingExport::class;
    protected static string | UnitEnum | null $navigationGroup = 'Auditoria';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Exportações contabilísticas';
    protected static ?string $modelLabel      = 'Exportação';
    protected static ?string $pluralModelLabel = 'Exportações contabilísticas';
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
                ->label('Sessão de serviço')
                ->nullable(),
            Forms\Components\Select::make('export_type')
                ->options([
                    'SESSION_SUMMARY' => 'Resumo de sessão',
                    'FULL_LEDGER' => 'Livro completo',
                ])
                ->required()
                ->default('SESSION_SUMMARY'),
            Forms\Components\DateTimePicker::make('export_range_start')
                ->label('Início do intervalo')
                ->nullable(),
            Forms\Components\DateTimePicker::make('export_range_end')
                ->label('Fim do intervalo')
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
                Tables\Columns\TextColumn::make('export_type')->badge()->label('Tipo'),
                Tables\Columns\TextColumn::make('export_range_start')->dateTime()->label('Início'),
                Tables\Columns\TextColumn::make('export_range_end')->dateTime()->label('Fim'),
                Tables\Columns\TextColumn::make('file_format')->label('Formato'),
                Tables\Columns\TextColumn::make('export_status')->badge()->colors([
                    'warning' => 'REQUESTED',
                    'success' => 'COMPLETED',
                    'danger'  => 'FAILED',
                ])->label('Estado'),
                Tables\Columns\TextColumn::make('requestedBy.name')->label('Solicitado por'),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->label('Concluído'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('download')
                    ->label('Descarregar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (AccountingExport $record) => $record->export_status === 'COMPLETED' && $record->file_name)
                    ->url(fn (AccountingExport $record) => route('accounting-export.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccountingExports::route('/'),
            'create' => Pages\CreateAccountingExport::route('/create'),
        ];
    }
}
