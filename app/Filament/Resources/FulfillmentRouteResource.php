<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FulfillmentRouteResource\Pages;
use App\Models\FulfillmentRoute;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FulfillmentRouteResource extends BaseResource
{
    protected static ?string $model = FulfillmentRoute::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'app.navigation_label_fulfillment_routes';

    protected static ?int $navigationSort = 42;

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
            Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(32),
            Forms\Components\TextInput::make('display_name')->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('code')->badge(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentRoutes::route('/'),
            'create' => Pages\CreateFulfillmentRoute::route('/create'),
            'edit' => Pages\EditFulfillmentRoute::route('/{record}/edit'),
        ];
    }
}
