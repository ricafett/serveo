<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\PrinterResource\Pages;
use App\Models\Printer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;
    protected static string | UnitEnum | null $navigationGroup = 'Configuração';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Impressoras';
    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('printer_type')->options([
                'KITCHEN' => 'Cozinha',
                'BAR'     => 'Bar',
                'BILL'    => 'Conta (caixa)',
                'GENERIC' => 'Genérica',
            ])->required(),
            Forms\Components\Select::make('connection_type')->options([
                'LAN'       => 'LAN direta (ESC/POS porta 9100)',
                'USB_AGENT' => 'USB através do agente local',
                'NULL'      => 'Simulada (escreve no disco)',
            ])->required()->reactive(),
            Forms\Components\TextInput::make('address')->label('Endereço IP / hostname')
                ->visible(fn (Forms\Get $get) => $get('connection_type') === 'LAN'),
            Forms\Components\TextInput::make('port')->numeric()->default(9100)
                ->visible(fn (Forms\Get $get) => $get('connection_type') === 'LAN'),
            Forms\Components\TextInput::make('agent_endpoint')->label('URL do agente')
                ->visible(fn (Forms\Get $get) => $get('connection_type') === 'USB_AGENT'),
            Forms\Components\TextInput::make('agent_printer_id')->label('ID interno no agente')
                ->visible(fn (Forms\Get $get) => $get('connection_type') === 'USB_AGENT'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('printer_type')->badge(),
                Tables\Columns\TextColumn::make('connection_type')->badge(),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('health_status')->badge()->colors([
                    'success' => 'OK',
                    'danger'  => 'UNREACHABLE',
                    'warning' => 'WARNING',
                    'gray'    => 'UNKNOWN',
                ])->label('Saúde'),
                Tables\Columns\TextColumn::make('last_seen_at')->dateTime()->label('Visto pela última vez'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrinters::route('/'),
            'create' => Pages\CreatePrinter::route('/create'),
            'edit'   => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
