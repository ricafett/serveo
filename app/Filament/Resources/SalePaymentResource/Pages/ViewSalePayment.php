<?php

namespace App\Filament\Resources\SalePaymentResource\Pages;

use App\Filament\Resources\SalePaymentResource;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSalePayment extends ViewRecord
{
    protected static string $resource = SalePaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.details'))
                    ->schema([
                        Components\TextEntry::make('recorded_at')
                            ->label(__('app.date'))
                            ->dateTime(),

                        Components\TextEntry::make('sale.display_code')
                            ->label(__('app.model_label_sale'))
                            ->url(fn ($record): string => \App\Filament\Resources\SaleResource::getUrl('view', ['record' => $record->sale_id])),

                        Components\TextEntry::make('sale.serviceSession.session_label')
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

                        Components\TextEntry::make('notes')
                            ->label(__('app.notes'))
                            ->placeholder('—'),
                    ])->columns(2),

                Section::make(__('app.model_label_sale'))
                    ->schema([
                        Components\TextEntry::make('sale.total_amount')
                            ->label(__('app.total'))
                            ->money('EUR'),

                        Components\TextEntry::make('sale.payment_label')
                            ->label(__('payments.payment_label'))
                            ->badge(),

                        Components\TextEntry::make('sale.sold_at')
                            ->label(__('sales.sold_at'))
                            ->dateTime(),
                    ])->columns(3),
            ]);
    }
}
