<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\PrinterRouteResource\Pages;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\Venue;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PrinterRouteResource extends Resource
{
    protected static ?string $model = PrinterRoute::class;
    protected static string | UnitEnum | null $navigationGroup = 'app.navigation_group_config';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'app.navigation_label_printer_routes';
    protected static ?int $navigationSort = 41;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('venue_id')
                ->options(Venue::query()->pluck('name', 'id'))
                ->required()
                ->default(fn () => Venue::query()->value('id')),
            Forms\Components\Select::make('document_type')->options([
                'PRODUCTION_TICKET' => __('app.document_type_production_ticket'),
                'BILL'              => __('app.document_type_bill'),
                'VOID_SLIP'         => __('app.document_type_void_slip'),
            ])->required(),
            Forms\Components\Select::make('fulfillment_route')->options([
                'KITCHEN' => __('app.route_kitchen'),
                'BAR'     => __('app.route_bar'),
            ])->nullable()->helperText(__('app.helper_text_production_only')),
            Forms\Components\Select::make('printer_id')
                ->options(Printer::query()->where('is_active', true)->pluck('name', 'id'))
                ->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')->badge(),
                Tables\Columns\TextColumn::make('fulfillment_route')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('printer.name'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrinterRoutes::route('/'),
            'create' => Pages\CreatePrinterRoute::route('/create'),
            'edit'   => Pages\EditPrinterRoute::route('/{record}/edit'),
        ];
    }
}
