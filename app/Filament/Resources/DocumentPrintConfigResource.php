<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentPrintConfigResource\Pages;
use App\Models\DocumentPrintConfig;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DocumentPrintConfigResource extends BaseResource
{
    protected static ?string $model = DocumentPrintConfig::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'app.navigation_label_document_print_configs';

    protected static ?string $modelLabel = 'app.model_label_document_print_config';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_document_print_configs';

    protected static ?int $navigationSort = 43;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Created automatically via FulfillmentRoute observer or lazily for BILL
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('printer.route_change') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false; // System-managed rows
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('document_type')
                ->disabled()
                ->dehydrated(true),
            Forms\Components\TextInput::make('fulfillment_route')
                ->disabled()
                ->dehydrated(true)
                ->placeholder('—'),
            Forms\Components\Toggle::make('group_items')
                ->helperText(__('app.helper_text_group_items')),
            Forms\Components\Toggle::make('ignore_variants')
                ->helperText(__('app.helper_text_ignore_variants')),
            Forms\Components\Toggle::make('ignore_modifiers')
                ->helperText(__('app.helper_text_ignore_modifiers')),
                Forms\Components\Toggle::make('trigger_cash_drawer')
                ->helperText(__('app.helper_text_trigger_cash_drawer')),
            Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('branding_header')
                    ->label(__('app.branding_header'))
                    ->helperText(__('app.helper_text_branding_header'))
                    ->rows(3)
                    ->autosize(),
                Forms\Components\TextInput::make('print_begin_space')
                    ->label(__('app.print_begin_space'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->default(0)
                    ->helperText(__('app.print_begin_space_help')),
            Forms\Components\TextInput::make('print_end_space')
                ->label(__('app.print_end_space'))
                ->numeric()
                ->minValue(0)
                ->maxValue(10)
                ->default(3)
                ->helperText(__('app.print_end_space_help')),
            Forms\Components\TextInput::make('copies')
                ->label(__('app.copies'))
                ->numeric()
                ->minValue(0)
                ->maxValue(10)
                ->default(0)
                ->helperText(__('app.copies_help')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'PRODUCTION_TICKET' => __('app.document_type_production_ticket'),
                        'BILL' => __('app.document_type_bill'),
                        'SALE_VOUCHER' => __('app.document_type_sale_voucher'),
                        'SALE_RECEIPT' => __('app.document_type_sale_receipt'),
                        'SERVER_ORDER' => __('app.document_type_server_order'),
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('fulfillment_route')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('group_items')->boolean(),
                Tables\Columns\IconColumn::make('ignore_variants')->boolean(),
                Tables\Columns\IconColumn::make('ignore_modifiers')->boolean(),
                Tables\Columns\IconColumn::make('trigger_cash_drawer')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentPrintConfigs::route('/'),
            'edit' => Pages\EditDocumentPrintConfig::route('/{record}/edit'),
        ];
    }
}
