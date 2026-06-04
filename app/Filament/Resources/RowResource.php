<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RowResource\Pages;
use App\Filament\Resources\RowResource\RelationManagers\SeatPairsRelationManager;
use App\Models\Row;
use App\Models\Section;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RowResource extends BaseResource
{
    protected static ?string $model = Row::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'app.navigation_label_rows';

    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('venue.configure') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('venue.configure') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('venue.configure') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('venue.configure') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('section_id')
                ->options(Section::query()->orderBy('section_code')->pluck('name', 'id'))
                ->required(),
            Forms\Components\TextInput::make('row_code')->required()->maxLength(32),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->selectable()
            ->columns([
                Tables\Columns\TextColumn::make('section.section_code')->label(__('app.room'))->sortable(),
                Tables\Columns\TextColumn::make('row_code')->sortable(),
                Tables\Columns\TextColumn::make('seats_count')->counts('seats')->label(__('app.seats')),
                Tables\Columns\TextColumn::make('seat_pairs_count')->counts('seatPairs')->label(__('app.pairs')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([Actions\EditAction::make()])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('assignServer')
                        ->label(__('app.assign_server'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Forms\Components\Select::make('server_id')
                                ->label(__('app.server'))
                                ->options(User::role('SERVER')->orderBy('name')->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            /** @var Collection<int, Row> $records */
                            $records->each(function (Row $row) use ($data): void {
                                $row->seatPairs()->update(['default_server_id' => $data['server_id']]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SeatPairsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRows::route('/'),
            'create' => Pages\CreateRow::route('/create'),
            'edit' => Pages\EditRow::route('/{record}/edit'),
        ];
    }
}
