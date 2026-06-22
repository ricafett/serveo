<?php

namespace App\Filament\Resources;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrintQueueService;
use App\Filament\Resources\PrintJobResource\Pages;
use App\Models\PrintJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PrintJobResource extends BaseResource
{
    protected static ?string $model = PrintJob::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_operation';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'app.navigation_label_print_jobs';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('print_job.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('job_kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'PRODUCTION_TICKET' => __('app.job_kind_production_ticket'),
                        'BILL' => __('app.job_kind_bill'),
                        'SALE_VOUCHER' => __('app.job_kind_sale_voucher'),
                        'SALE_RECEIPT' => __('app.job_kind_sale_receipt'),
                        'SERVER_ORDER' => __('app.job_kind_server_order'),
                        'CASHIER_TOTALS' => __('app.job_kind_cashier_totals'),
                        'SESSION_TOTALS' => __('app.job_kind_session_totals'),
                        'INVENTORY_MOVEMENTS' => __('app.job_kind_inventory_movements'),
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('printer.name')->label(__('app.printer')),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => 'PENDING',
                    'warning' => 'IN_PROGRESS',
                    'success' => 'PRINTED',
                    'danger' => 'FAILED',
                    'secondary' => 'CANCELED',
                ]),
                Tables\Columns\TextColumn::make('attempts')->label(__('app.attempts')),
                Tables\Columns\TextColumn::make('last_error')->wrap()->limit(60)->color('danger'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label(__('app.created')),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->label(__('app.completed')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'PENDING' => __('app.status_pending'),
                    'IN_PROGRESS' => __('app.status_in_progress'),
                    'PRINTED' => __('app.status_printed'),
                    'FAILED' => __('app.status_failed'),
                    'CANCELED' => __('app.status_canceled'),
                ]),
                Tables\Filters\SelectFilter::make('job_kind')->options([
                    'PRODUCTION_TICKET' => __('app.job_kind_production_ticket'),
                    'BILL' => __('app.job_kind_bill'),
                    'SALE_VOUCHER' => __('app.job_kind_sale_voucher'),
                    'SALE_RECEIPT' => __('app.job_kind_sale_receipt'),
                    'SERVER_ORDER' => __('app.job_kind_server_order'),
                    'CASHIER_TOTALS' => __('app.job_kind_cashier_totals'),
                    'SESSION_TOTALS' => __('app.job_kind_session_totals'),
                    'INVENTORY_MOVEMENTS' => __('app.job_kind_inventory_movements'),
                ]),
            ])
            ->actions([
                Action::make('retry')
                    ->label(__('app.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PrintJob $record) => in_array($record->status, ['FAILED', 'CANCELED'], true) && Auth::user()?->can('print_job.retry'))
                    ->action(function (PrintJob $record, PrintQueueService $queue) {
                        $queue->retry($record, Auth::user());
                        Audit::record(
                            'PRINT_JOB_RETRIED',
                            "Print job #{$record->id} reenviado",
                            ['kind' => $record->job_kind],
                            ['actor_user_id' => Auth::id()],
                        );
                        Notification::make()->title('Reenviado')->success()->send();
                    }),
                Action::make('cancel')
                    ->label(__('app.cancel_action'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PrintJob $record) => in_array($record->status, ['PENDING', 'FAILED', 'IN_PROGRESS'], true) && Auth::user()?->can('print_job.retry'))
                    ->action(function (PrintJob $record) {
                        $record->update(['status' => 'CANCELED']);
                        Notification::make()->title('Cancelado')->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('retry_batch')
                    ->label(__('app.retry_selected'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => Auth::user()?->can('print_job.retry'))
                    ->action(function (Collection $records, PrintQueueService $queue) {
                        $results = $queue->retryBatch($records->pluck('id')->toArray(), Auth::user());

                        foreach ($records as $record) {
                            Audit::record(
                                'PRINT_JOB_RETRIED',
                                "Print job #{$record->id} reenviado (batch)",
                                ['kind' => $record->job_kind],
                                ['actor_user_id' => Auth::id()],
                            );
                        }

                        $message = __('app.retry_batch_result', [
                            'success' => $results['success'],
                            'skipped' => $results['skipped'],
                        ]);

                        Notification::make()
                            ->title($results['success'] > 0 ? __('app.retry_batch_success') : __('app.retry_batch_none'))
                            ->body($message)
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintJobs::route('/'),
        ];
    }
}
