<?php

namespace App\Filament\Resources\BillingGroupResource\Pages;

use App\Domain\Audit\Audit;
use App\Filament\Resources\BillingGroupResource;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\PaymentRecord;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewBillingGroup extends ViewRecord
{
    protected static string $resource = BillingGroupResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.details'))
                    ->schema([
                        Components\TextEntry::make('name')
                            ->label(__('billing.name')),
                        Components\TextEntry::make('display_code')
                            ->label(__('app.code')),
                        Components\TextEntry::make('status.display_name')
                            ->label(__('app.status'))
                            ->badge(),
                        Components\TextEntry::make('serviceSession.session_label')
                            ->label(__('app.session')),
                        Components\TextEntry::make('openedBy.name')
                            ->label(__('app.opened_by')),
                        Components\TextEntry::make('cover_count')
                            ->label(__('app.cover_count')),
                        Components\TextEntry::make('opened_at')
                            ->label(__('app.opened_at'))
                            ->dateTime(),
                        Components\TextEntry::make('closed_at')
                            ->label(__('app.closed_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make(__('app.zones'))
                    ->schema([
                        Components\RepeatableEntry::make('occupiedZones')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('location')
                                    ->label(__('app.location'))
                                    ->state(fn (OccupiedZone $record): string => $record->location()),
                                Components\TextEntry::make('range_label')
                                    ->label(__('app.range'))
                                    ->state(fn (OccupiedZone $record): string => $record->rangeLabel()),
                                Components\TextEntry::make('server.name')
                                    ->label(__('app.server')),
                                Components\IconEntry::make('is_open')
                                    ->label(__('app.open'))
                                    ->boolean(),
                            ])
                            ->columns(4),
                    ]),

                Section::make(__('app.totals'))
                    ->schema([
                        Components\TextEntry::make('charges_total')
                            ->label(__('billing.charges'))
                            ->state(fn (BillingGroup $record): string => number_format($record->chargesTotal(), 2).' €'),
                        Components\TextEntry::make('paid_total')
                            ->label(__('billing.paid'))
                            ->state(fn (BillingGroup $record): string => number_format($record->paymentsTotal(), 2).' €'),
                        Components\TextEntry::make('balance_total')
                            ->label(__('app.balance'))
                            ->state(fn (BillingGroup $record): string => number_format($record->balance(), 2).' €'),
                    ])
                    ->columns(3),

                Section::make(__('billing.orders'))
                    ->schema([
                        Components\RepeatableEntry::make('orderHeaders')
                            ->label('')
                            ->contained(false)
                            ->schema([
                                Components\TextEntry::make('ordered_at')
                                    ->label(__('app.opened_at'))
                                    ->dateTime(),
                                Components\TextEntry::make('orderedBy.name')
                                    ->label(__('app.user')),
                                Components\TextEntry::make('occupiedZone.range_label')
                                    ->label(__('app.range'))
                                    ->state(fn (OrderHeader $record): string => $record->occupiedZone?->rangeLabel() ?? '—'),
                                Components\TextEntry::make('submission_status')
                                    ->label(__('app.status'))
                                    ->badge(),
                                Components\RepeatableEntry::make('items')
                                    ->label(__('app.items'))
                                    ->contained(false)
                                    ->columnSpanFull()
                                    ->schema([
                                        Components\TextEntry::make('menuItem.display_name')
                                            ->label(__('billing.item'))
                                            ->columnSpan(2)
                                            ->state(fn (OrderItem $record): string => $record->menuItem?->display_name ?? '#'.$record->menu_item_id),
                                        Components\TextEntry::make('quantity')
                                            ->label(__('billing.qty')),
                                        Components\TextEntry::make('unit_price')
                                            ->label(__('cashier.amount'))
                                            ->state(fn (OrderItem $record): string => number_format((float) $record->unit_price, 2).' €'),
                                        Components\TextEntry::make('line_subtotal')
                                            ->label(__('billing.subtotal'))
                                            ->state(fn (OrderItem $record): string => number_format((float) $record->line_subtotal, 2).' €'),
                                        Components\IconEntry::make('voided_at')
                                            ->label(__('billing.voided'))
                                            ->boolean()
                                            ->state(fn (OrderItem $record): bool => $record->voided_at !== null),
                                    ])
                                    ->columns(5),
                            ])
                            ->columns(3),
                    ])
                    ->collapsed()
                    ->visible(fn (BillingGroup $record): bool => $record->orderHeaders()->exists()),

                Section::make(__('billing.payments'))
                    ->schema([
                        Components\RepeatableEntry::make('paymentRecords')
                            ->label('')
                            ->contained(false)
                            ->schema([
                                Components\TextEntry::make('recorded_at')
                                    ->label(__('app.date'))
                                    ->dateTime(),
                                Components\TextEntry::make('amount')
                                    ->label(__('cashier.amount'))
                                    ->state(fn (PaymentRecord $record): string => number_format((float) $record->amount, 2).' €'),
                                Components\TextEntry::make('payment_label')
                                    ->label(__('cashier.payment_method')),
                                Components\TextEntry::make('recordedBy.name')
                                    ->label(__('app.user')),
                                Components\IconEntry::make('is_voided')
                                    ->label(__('billing.voided'))
                                    ->boolean(),
                            ])
                            ->columns(5),
                    ])
                    ->collapsed()
                    ->visible(fn (BillingGroup $record): bool => $record->paymentRecords()->exists()),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assignServerToZones')
                ->label(__('app.assign_server'))
                ->icon('heroicon-o-user-group')
                ->schema([
                    Forms\Components\Select::make('server_id')
                        ->label(__('app.server'))
                        ->options(User::role('SERVER')->orderBy('name')->pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var BillingGroup $record */
                    $record = $this->getRecord();

                    $zoneIds = $record->occupiedZones()->pluck('id');
                    OccupiedZone::whereIn('id', $zoneIds)
                        ->update(['server_id' => $data['server_id']]);

                    Audit::record(
                        'server_assigned',
                        "Server ID {$data['server_id']} assigned to all zones of billing group {$record->display_code}",
                        ['server_id' => $data['server_id'], 'billing_group_id' => $record->id],
                        ['service_session_id' => $record->service_session_id],
                    );
                }),
        ];
    }
}
