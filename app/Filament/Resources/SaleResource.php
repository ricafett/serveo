<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use BackedEnum;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SaleResource extends BaseResource
{
    protected static ?string $model = Sale::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_operation';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'app.navigation_label_sales';

    protected static ?string $modelLabel = 'app.model_label_sale';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_sales';

    protected static ?int $navigationSort = 26;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('sale.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_code')->label(__('app.code'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('serviceSession.session_label')->label(__('app.session'))->sortable(),
                Tables\Columns\TextColumn::make('soldBy.name')->label(__('app.user'))->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->label(__('app.total'))->sortable(),
                Tables\Columns\TextColumn::make('payment_label')->label(__('sales.payment_method'))->badge(),
                Tables\Columns\TextColumn::make('sold_at')->dateTime()->label(__('sales.sold_at'))->sortable(),
            ])
            ->defaultSort('sold_at', 'desc')
            ->recordActions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
