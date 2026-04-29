<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\PrintJobResource\Pages;
use App\Domain\Audit\Audit;
use App\Domain\Printing\PrintQueueService;
use App\Models\PrintJob;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PrintJobResource extends Resource
{
    protected static ?string $model = PrintJob::class;
    protected static string | UnitEnum | null $navigationGroup = 'Operação';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Fila de impressão';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
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
                Tables\Columns\TextColumn::make('job_kind')->badge(),
                Tables\Columns\TextColumn::make('printer.name')->label('Impressora'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray'    => 'PENDING',
                    'warning' => 'IN_PROGRESS',
                    'success' => 'PRINTED',
                    'danger'  => 'FAILED',
                    'secondary' => 'CANCELED',
                ]),
                Tables\Columns\TextColumn::make('attempts')->label('Tentativas'),
                Tables\Columns\TextColumn::make('last_error')->wrap()->limit(60)->color('danger'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Criado'),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->label('Concluído'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'PENDING'     => 'Pendente',
                    'IN_PROGRESS' => 'Em curso',
                    'PRINTED'     => 'Impresso',
                    'FAILED'      => 'Falhou',
                    'CANCELED'    => 'Cancelado',
                ]),
                Tables\Filters\SelectFilter::make('job_kind')->options([
                    'PRODUCTION_TICKET' => 'Ticket de produção',
                    'BILL'              => 'Conta',
                ]),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Reenviar')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PrintJob $record) => in_array($record->status, ['FAILED', 'CANCELED'], true))
                    ->action(function (PrintJob $record, PrintQueueService $queue) {
                        $queue->retry($record, Auth::user());
                        Audit::record(
                            'PRINT_JOB_RETRIED',
                            "Print job #{$record->id} reenviado",
                            ['kind' => $record->job_kind],
                        );
                        Notification::make()->title('Reenviado')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PrintJob $record) => in_array($record->status, ['PENDING', 'FAILED', 'IN_PROGRESS'], true))
                    ->action(function (PrintJob $record) {
                        $record->update(['status' => 'CANCELED']);
                        Notification::make()->title('Cancelado')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintJobs::route('/'),
        ];
    }
}
