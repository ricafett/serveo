<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.details'))
                    ->schema([
                        Components\TextEntry::make('display_code')->label(__('app.code')),
                        Components\TextEntry::make('serviceSession.session_label')->label(__('app.session')),
                        Components\TextEntry::make('soldBy.name')->label(__('app.user')),
                        Components\TextEntry::make('payment_label')->label(__('sales.payment_method')),
                        Components\TextEntry::make('total_amount')->money('EUR')->label(__('app.total')),
                        Components\TextEntry::make('sold_at')->dateTime()->label(__('sales.sold_at')),
                    ])->columns(2),
                Section::make(__('sales.items'))
                    ->schema([
                        Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('display_name_snapshot')->label(__('app.name')),
                                Components\TextEntry::make('quantity')->label(__('sales.quantity')),
                                Components\TextEntry::make('line_subtotal')->money('EUR')->label(__('billing.subtotal')),
                            ])->columns(3),
                    ]),
                Section::make(__('billing.payments'))
                    ->schema([
                        Components\RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('amount')->money('EUR')->label(__('sales.amount')),
                                Components\TextEntry::make('payment_label')->label(__('sales.payment_method')),
                                Components\TextEntry::make('recorded_at')->dateTime()->label(__('sales.sold_at')),
                            ])->columns(3),
                    ]),
                Section::make(__('sales.documents'))
                    ->schema([
                        Components\RepeatableEntry::make('documents')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('document_type')->badge()->label(__('app.type')),
                                Components\TextEntry::make('document_number')->label(__('ticket.document')),
                                Components\TextEntry::make('document_status')->badge()->label(__('app.status')),
                                Components\TextEntry::make('quantity')->label(__('sales.quantity')),
                            ])->columns(4),
                    ]),
            ]);
    }
}
