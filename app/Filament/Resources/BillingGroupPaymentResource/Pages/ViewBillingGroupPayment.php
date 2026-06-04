<?php

namespace App\Filament\Resources\BillingGroupPaymentResource\Pages;

use App\Filament\Resources\BillingGroupPaymentResource;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewBillingGroupPayment extends ViewRecord
{
    protected static string $resource = BillingGroupPaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.details'))
                    ->schema([
                        Components\TextEntry::make('recorded_at')
                            ->label(__('app.date'))
                            ->dateTime(),

                        Components\TextEntry::make('billingGroup.display_code')
                            ->label(__('billing.group_title'))
                            ->url(fn ($record): string => \App\Filament\Resources\BillingGroupResource::getUrl('view', ['record' => $record->billing_group_id])),

                        Components\TextEntry::make('billingGroup.serviceSession.session_label')
                            ->label(__('app.session')),

                        Components\TextEntry::make('amount')
                            ->label(__('payments.amount'))
                            ->money('EUR'),

                        Components\TextEntry::make('payment_label')
                            ->label(__('payments.payment_label'))
                            ->badge(),

                        Components\TextEntry::make('recordedBy.name')
                            ->label(__('app.user')),

                        Components\IconEntry::make('is_voided')
                            ->label(__('payments.voided'))
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-check-circle'),

                        Components\TextEntry::make('billingGroup.status.display_name')
                            ->label(__('app.status'))
                            ->badge(),

                        Components\TextEntry::make('notes')
                            ->label(__('app.notes'))
                            ->placeholder('—'),
                    ])->columns(2),

                Section::make(__('billing.group_title'))
                    ->schema([
                        Components\TextEntry::make('billingGroup.charges_total_computed')
                            ->label(__('app.charges'))
                            ->money('EUR')
                            ->state(fn ($record): float => $record->billingGroup?->chargesTotal() ?? 0),

                        Components\TextEntry::make('billingGroup.payments_total_computed')
                            ->label(__('billing.paid'))
                            ->money('EUR')
                            ->state(fn ($record): float => $record->billingGroup?->paymentsTotal() ?? 0),

                        Components\TextEntry::make('billingGroup.balance_computed')
                            ->label(__('app.balance'))
                            ->money('EUR')
                            ->state(fn ($record): float => $record->billingGroup?->balance() ?? 0),
                    ])->columns(3),
            ]);
    }
}
