<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\MenuCategoryResource\Pages;
use App\Models\MenuCategory;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;
    protected static string | UnitEnum | null $navigationGroup = 'Configuração';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categorias de menu';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(64)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('display_name')->required(),
            Forms\Components\Select::make('route_type')->options([
                'KITCHEN' => 'Cozinha',
                'BAR'     => 'Bar',
                'NONE'    => 'Sem rota',
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
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuCategories::route('/'),
            'create' => Pages\CreateMenuCategory::route('/create'),
            'edit'   => Pages\EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
