<?php

namespace App\Filament\Resources;

use App\Domain\Audit\Audit;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierSet;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MenuItemResource extends BaseResource
{
    protected static ?string $model = MenuItem::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'app.navigation_label_menu_items';

    protected static ?int $navigationSort = 31;

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
            Forms\Components\Toggle::make('is_voucher_enabled')
                ->label(__('app.is_voucher_enabled'))
                ->helperText(__('app.is_voucher_enabled_help'))
                ->default(false),
            Forms\Components\Select::make('modifier_set_id')
                ->label(__('app.modifier_set'))
                ->options(ModifierSet::query()->where('is_active', true)->pluck('display_name', 'id'))
                ->nullable()
                ->placeholder(__('app.none')),
            Forms\Components\Repeater::make('variants')
                ->relationship('variants')
                ->schema([
                    Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])
                ->orderColumn('sort_order')
                ->defaultItems(0)
                ->addActionLabel(__('app.add_variant'))
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.display_name')->label(__('app.category'))->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('unit_price')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('category.route_type')->label(__('app.route'))->badge(),
                Tables\Columns\IconColumn::make('is_voucher_enabled')->label(__('app.is_voucher_enabled'))->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_category_id')
                    ->label(__('app.category'))
                    ->options(MenuCategory::query()->pluck('display_name', 'id')),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([
                Actions\BulkAction::make('enableSelected')
                    ->label(__('app.enable_selected'))
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records): string => __('app.bulk_menu_item_confirmation', ['count' => $records->count()]))
                    ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_active' => true], 'MENU_ITEMS_BULK_ENABLED')),
                Actions\BulkAction::make('disableSelected')
                    ->label(__('app.disable_selected'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records): string => __('app.bulk_menu_item_confirmation', ['count' => $records->count()]))
                    ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_active' => false], 'MENU_ITEMS_BULK_DISABLED')),
                Actions\BulkAction::make('enableSelectedVouchers')
                    ->label(__('app.enable_selected_vouchers'))
                    ->icon('heroicon-o-ticket')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records): string => __('app.bulk_menu_item_confirmation', ['count' => $records->count()]))
                    ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_voucher_enabled' => true], 'MENU_ITEMS_BULK_VOUCHERS_ENABLED')),
                Actions\BulkAction::make('disableSelectedVouchers')
                    ->label(__('app.disable_selected_vouchers'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records): string => __('app.bulk_menu_item_confirmation', ['count' => $records->count()]))
                    ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_voucher_enabled' => false], 'MENU_ITEMS_BULK_VOUCHERS_DISABLED')),
                Actions\DeleteBulkAction::make(),
            ]);
    }

    protected static function bulkUpdate(Collection $records, array $attributes, string $eventType): void
    {
        if ($records->isEmpty()) {
            return;
        }

        MenuItem::query()
            ->whereKey($records->modelKeys())
            ->update($attributes);

        Audit::record(
            $eventType,
            'Bulk menu item update',
            [
                'count' => $records->count(),
                'menu_item_ids' => $records->modelKeys(),
                'attributes' => $attributes,
            ],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
