<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingGroupPaymentResource\Pages;
use App\Models\PaymentRecord;
use App\Models\ServiceSession;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BillingGroupPaymentResource extends BaseResource
{
    protected static ?string $model = PaymentRecord::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_payments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'app.navigation_label_billing_group_payments';

    protected static ?string $modelLabel = 'app.model_label_billing_group_payment';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_billing_group_payments';

    protected static ?int $navigationSort = 27;

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

                Tables\Columns\TextColumn::make('billingGroup.display_code')
                    ->label(__('billing.group_title'))
                    ->sortable()
                    ->searchable()
                    ->url(fn (PaymentRecord $record): string => BillingGroupResource::getUrl('view', ['record' => $record->billing_group_id])),

                Tables\Columns\TextColumn::make('billingGroup.serviceSession.session_label')
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

                Tables\Columns\TextColumn::make('billingGroup.status.display_name')
                    ->label(__('app.status'))
                    ->badge()
                    ->color(fn ($record): string => match ($record->billingGroup?->status?->code) {
                        'ACTIVE' => 'success',
                        'CLOSED' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('billingGroup.serviceSession')
                    ->label(__('app.session'))
                    ->relationship('billingGroup.serviceSession', 'session_label')
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
            'index' => Pages\ListBillingGroupPayments::route('/'),
            'view' => Pages\ViewBillingGroupPayment::route('/{record}'),
        ];
    }
}
