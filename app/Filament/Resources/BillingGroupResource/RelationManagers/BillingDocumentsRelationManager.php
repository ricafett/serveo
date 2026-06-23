<?php

namespace App\Filament\Resources\BillingGroupResource\RelationManagers;

use App\Domain\Billing\BillingService;
use App\Models\BillingDocument;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BillingDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'billingDocuments';

    protected static ?string $title = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('billing.printed_bills'))
            ->modifyQueryUsing(fn ($query) => $query->with(['createdBy', 'printer'])->latest('requested_at')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('app.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label(__('app.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BillingDocument::TYPE_INTERNAL_BILL => __('app.document_type_bill'),
                        BillingDocument::TYPE_BILL_REPRINT => __('cashier.reprint'),
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('document_status')
                    ->label(__('app.status'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('app.total'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' €')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_reprint')
                    ->label(__('cashier.reprint'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label(__('app.requested_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('printed_at')
                    ->label(__('app.completed'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('app.user'))
                    ->placeholder('—'),
            ])
            ->actions([
                Actions\Action::make('reprint')
                    ->label(__('cashier.reprint'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('cashier.reprint'))
                    ->visible(fn (BillingDocument $record): bool => Auth::user()?->can('billing_document.reprint', $record) ?? false)
                    ->action(function (BillingDocument $record): void {
                        app(BillingService::class)->reprintBill($record, Auth::user());

                        Notification::make()
                            ->title(__('cashier.reprint_sent'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('billing.no_bills'));
    }
}
