<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalePaymentResource\Pages;
use App\Models\SalePayment;
use BackedEnum;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SalePaymentResource extends BaseResource
{
    protected static ?string $model = SalePayment::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_payments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'app.navigation_label_sale_payments';

    protected static ?string $modelLabel = 'app.model_label_sale_payment';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_sale_payments';

    protected static ?int $navigationSort = 28;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('payment.view') ?? false;
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
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label(__('app.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale.display_code')
                    ->label(__('app.model_label_sale'))
                    ->sortable()
                    ->searchable()
                    ->url(fn (SalePayment $record): string => SaleResource::getUrl('view', ['record' => $record->sale_id])),

                Tables\Columns\TextColumn::make('sale.serviceSession.session_label')
                    ->label(__('app.session'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('payments.amount'))
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_label')
                    ->label(__('payments.payment_label'))
                    ->badge(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label(__('app.user'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_voided')
                    ->label(__('payments.voided'))
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle'),

                Tables\Columns\TextColumn::make('sale.total_amount')
                    ->label(__('app.total'))
                    ->money('EUR')
                    ->sortable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('sale.serviceSession')
                    ->label(__('app.session'))
                    ->relationship('sale.serviceSession', 'session_label')
                    ->preload(),

                Tables\Filters\Filter::make('is_voided')
                    ->label(__('payments.voided'))
                    ->query(fn ($query) => $query->where('is_voided', true))
                    ->toggle(),

                Tables\Filters\Filter::make('not_voided')
                    ->label(__('payments.not_voided'))
                    ->query(fn ($query) => $query->where('is_voided', false))
                    ->toggle(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalePayments::route('/'),
            'view' => Pages\ViewSalePayment::route('/{record}'),
        ];
    }
}
