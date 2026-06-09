<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashMovementResource\Pages;
use App\Models\CashMovement;
use App\Models\ServiceSession;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CashMovementResource extends BaseResource
{
    protected static ?string $model = CashMovement::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_operation';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'app.navigation_label_cash_movements';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('cash_drawer.view_all') ?? false;
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
        $sessions = ServiceSession::orderBy('starts_at', 'desc')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (ServiceSession $s) => [$s->id => $s->session_label.' ('.$s->starts_at->format('Y-m-d').')'])
            ->all();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('cashier.name')
                    ->label(__('app.user'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('serviceSession.session_label')
                    ->label(__('app.session'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label(__('app.type'))
                    ->badge()
                    ->colors([
                        'success' => 'CASH_IN',
                        'danger' => 'CASH_OUT',
                    ])
                    ->formatStateUsing(fn (string $state): string => $state === 'CASH_IN'
                        ? __('cashdrawer.cash_in')
                        : __('cashdrawer.cash_out')),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('cashdrawer.amount'))
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('cashdrawer.label'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label(__('app.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('service_session_id')
                    ->label(__('app.session'))
                    ->options($sessions),
                Tables\Filters\SelectFilter::make('cashier_user_id')
                    ->label(__('app.user'))
                    ->relationship('cashier', 'name'),
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label(__('app.type'))
                    ->options([
                        'CASH_IN' => __('cashdrawer.cash_in'),
                        'CASH_OUT' => __('cashdrawer.cash_out'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashMovements::route('/'),
            'view' => Pages\ViewCashMovement::route('/{record}'),
        ];
    }
}
