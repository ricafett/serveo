<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModifierSetResource\Pages;
use App\Models\ModifierSet;
use App\Models\ModifierSetItem;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ModifierSetResource extends BaseResource
{
    protected static ?string $model = ModifierSet::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'app.navigation_label_modifier_sets';

    protected static ?string $modelLabel = 'app.model_label_modifier_set';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_modifier_sets';

    protected static ?int $navigationSort = 32;

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
            Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
            Forms\Components\Select::make('selection_mode')
                ->options([
                    'single' => __('app.modifier_selection_single'),
                    'multiple' => __('app.modifier_selection_multiple'),
                ])
                ->default('single')
                ->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Toggle::make('assume_default')
                ->default(false)
                ->helperText(__('app.assume_default_help')),
            Forms\Components\Select::make('default_modifier_set_item_id')
                ->label(__('app.default_modifier_item'))
                ->relationship('items', 'display_name', fn ($q) => $q->where('is_active', true))
                ->nullable()
                ->placeholder(__('app.none'))
                ->helperText(__('app.is_default_help')),
            Forms\Components\Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])
                ->orderColumn('sort_order')
                ->defaultItems(0)
                ->addActionLabel(__('app.add_modifier_item'))
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('selection_mode')->badge(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label(__('app.items')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModifierSets::route('/'),
            'create' => Pages\CreateModifierSet::route('/create'),
            'edit' => Pages\EditModifierSet::route('/{record}/edit'),
        ];
    }
}
