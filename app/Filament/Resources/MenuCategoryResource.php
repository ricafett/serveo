<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuCategoryResource\Pages;
use App\Models\MenuCategory;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MenuCategoryResource extends BaseResource
{
    protected static ?string $model = MenuCategory::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'app.navigation_label_menu_categories';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('menu.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('menu.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('menu.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('menu.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(64)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('display_name')->required(),
            Forms\Components\Select::make('route_type')->options([
                'KITCHEN' => 'Cozinha',
                'BAR' => 'Bar',
                'NONE' => 'Sem rota',
            ])->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('route_type')->badge(),
                Tables\Columns\TextColumn::make('items_count')->counts('items'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuCategories::route('/'),
            'create' => Pages\CreateMenuCategory::route('/create'),
            'edit' => Pages\EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
