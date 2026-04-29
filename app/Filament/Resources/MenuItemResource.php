<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationGroup = 'Configuração';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Itens de menu';
    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('menu_category_id')
                ->options(MenuCategory::query()->pluck('display_name', 'id'))
                ->required(),
            Forms\Components\TextInput::make('display_name')->required(),
            Forms\Components\TextInput::make('short_name')->maxLength(64),
            Forms\Components\TextInput::make('sku')->maxLength(64),
            Forms\Components\TextInput::make('code')->maxLength(64),
            Forms\Components\TextInput::make('unit_price')->numeric()->step('0.01')->required()->default(0),
            Forms\Components\TextInput::make('tax_code')->maxLength(16),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.display_name')->label('Categoria')->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('unit_price')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('category.route_type')->label('Rota')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_category_id')
                    ->label('Categoria')
                    ->options(MenuCategory::query()->pluck('display_name', 'id')),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
