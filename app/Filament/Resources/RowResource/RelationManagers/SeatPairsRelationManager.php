<?php

namespace App\Filament\Resources\RowResource\RelationManagers;

use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeatPairsRelationManager extends RelationManager
{
    protected static string $relationship = 'seatPairs';

    protected static ?string $title = 'Seat Pairs';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('pair_sequence')
                    ->label(__('app.pair_sequence'))
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->unique(
                        table: 'seat_pairs',
                        column: 'pair_sequence',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('row_id', $this->getOwnerRecord()->id),
                    ),

                Forms\Components\Select::make('seat_a_id')
                    ->label(__('app.seat_a'))
                    ->options(function () {
                        return Seat::where('row_id', $this->getOwnerRecord()->id)
                            ->orderBy('seat_number')
                            ->get()
                            ->mapWithKeys(fn (Seat $seat) => [
                                $seat->id => (string) $seat->seat_number,
                            ]);
                    })
                    ->required()
                    ->searchable()
                    ->different('seat_b_id'),

                Forms\Components\Select::make('seat_b_id')
                    ->label(__('app.seat_b'))
                    ->options(function () {
                        return Seat::where('row_id', $this->getOwnerRecord()->id)
                            ->orderBy('seat_number')
                            ->get()
                            ->mapWithKeys(fn (Seat $seat) => [
                                $seat->id => (string) $seat->seat_number,
                            ]);
                    })
                    ->required()
                    ->searchable()
                    ->different('seat_a_id'),

                Forms\Components\Select::make('default_server_id')
                    ->label(__('app.default_server'))
                    ->options(User::role('SERVER')->orderBy('name')->pluck('name', 'id'))
                    ->nullable()
                    ->searchable(),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('app.is_active'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pair_sequence')
                    ->sortable()
                    ->label(__('app.pair_sequence')),

                Tables\Columns\TextColumn::make('seatA.seat_number')
                    ->label(__('app.seat_a')),

                Tables\Columns\TextColumn::make('seatB.seat_number')
                    ->label(__('app.seat_b')),

                Tables\Columns\TextColumn::make('defaultServer.name')
                    ->label(__('app.default_server'))
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('app.is_active')),
            ])
            ->defaultSort('pair_sequence')
            ->headerActions([
                Actions\CreateAction::make()
                    ->beforeFormValidated(function () {
                        $this->validateSeatNotReused();
                    }),
                Actions\Action::make('batchCreatePairs')
                    ->label(__('app.batch_create_pairs'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('pair_count')
                            ->label(__('app.number_of_pairs'))
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100),
                        Forms\Components\TextInput::make('start_pair_sequence')
                            ->label(__('app.pair_sequence'))
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(fn () => ($this->getOwnerRecord()->seatPairs()->max('pair_sequence') ?? 0) + 1),
                        Forms\Components\Select::make('default_server_id')
                            ->label(__('app.default_server'))
                            ->options(User::role('SERVER')->orderBy('name')->pluck('name', 'id'))
                            ->nullable()
                            ->searchable(),
                    ])
                    ->action(function (array $data): void {
                        $row = $this->getOwnerRecord();
                        $startSeq = (int) $data['start_pair_sequence'];
                        $count = (int) $data['pair_count'];
                        $defaultServerId = $data['default_server_id'] ?? null;

                        // Check for existing sequences
                        $exists = $row->seatPairs()
                            ->whereBetween('pair_sequence', [$startSeq, $startSeq + $count - 1])
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title(__('app.pair_sequence_exists'))
                                ->danger()
                                ->send();

                            $this->halt();

                            return;
                        }

                        DB::transaction(function () use ($row, $startSeq, $count, $defaultServerId): void {
                            for ($i = 0; $i < $count; $i++) {
                                $seq = $startSeq + $i;

                                $seatA = Seat::create([
                                    'row_id' => $row->id,
                                    'seat_number' => $seq * 2 - 1,
                                    'sort_order' => $seq * 2 - 1,
                                    'is_active' => true,
                                ]);

                                $seatB = Seat::create([
                                    'row_id' => $row->id,
                                    'seat_number' => $seq * 2,
                                    'sort_order' => $seq * 2,
                                    'is_active' => true,
                                ]);

                                SeatPair::create([
                                    'row_id' => $row->id,
                                    'pair_sequence' => $seq,
                                    'seat_a_id' => $seatA->id,
                                    'seat_b_id' => $seatB->id,
                                    'default_server_id' => $defaultServerId,
                                    'is_active' => true,
                                ]);
                            }
                        });

                        Notification::make()
                            ->title(__('app.pairs_created', ['count' => $count]))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->beforeFormValidated(function () {
                        $this->validateSeatNotReused();
                    }),
                Actions\DeleteAction::make(),
                Actions\Action::make('toggleActive')
                    ->label(fn (SeatPair $record) => $record->is_active
                        ? __('app.deactivate')
                        : __('app.activate'))
                    ->icon(fn (SeatPair $record) => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (SeatPair $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (SeatPair $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    }),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Validate that seat_a_id and seat_b_id are not already used
     * by another seat pair in the same row.
     */
    protected function validateSeatNotReused(): void
    {
        $data = $this->mountedTableActionData;
        $rowId = $this->getOwnerRecord()->id;

        // Determine the record being edited (null for create)
        $recordId = null;
        if ($this->mountedTableActionRecord instanceof SeatPair) {
            $recordId = $this->mountedTableActionRecord->id;
        } elseif (is_array($this->mountedTableActionRecord) && isset($this->mountedTableActionRecord['id'])) {
            $recordId = $this->mountedTableActionRecord['id'];
        }

        $seatAId = $data['seat_a_id'] ?? null;
        $seatBId = $data['seat_b_id'] ?? null;

        foreach (['seat_a_id' => $seatAId, 'seat_b_id' => $seatBId] as $field => $seatId) {
            if (! $seatId) {
                continue;
            }

            $query = SeatPair::where('row_id', $rowId)
                ->where(function ($q) use ($seatId) {
                    $q->where('seat_a_id', $seatId)
                      ->orWhere('seat_b_id', $seatId);
                });

            if ($recordId) {
                $query->where('id', '!=', $recordId);
            }

            if ($query->exists()) {
                $seat = Seat::find($seatId);
                $seatLabel = $seat ? $seat->seat_number : (string) $seatId;

                $this->addError(
                    "mountedTableActionData.{$field}",
                    __('app.seat_already_used', ['seat' => $seatLabel]),
                );
            }
        }
    }
}
