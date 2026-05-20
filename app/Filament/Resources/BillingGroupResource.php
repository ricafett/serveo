<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingGroupResource\Pages;
use App\Models\BillingGroup;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BillingGroupResource extends BaseResource
{
    protected static ?string $model = BillingGroup::class;
    protected static string | \UnitEnum | null $navigationGroup = 'app.navigation_group_config';
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'app.navigation_label_billing_groups';
    protected static ?string $modelLabel = 'app.model_label_billing_group';
    protected static ?string $pluralModelLabel = 'app.plural_model_label_billing_groups';
    protected static ?int $navigationSort = 25;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('venue.configure') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->selectable()
            ->columns([
                Tables\Columns\TextColumn::make('display_code')
                    ->label(__('app.code'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status.display_name')
                    ->label(__('app.status'))
                    ->badge()
                    ->color(fn ($record): string => match ($record->status?->code) {
                        'ACTIVE' => 'success',
                        'CLOSED' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('serviceSession.session_label')
                    ->label(__('app.session'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('openedBy.name')
                    ->label(__('app.opened_by'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('cover_count')
                    ->label(__('app.cover_count'))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('occupied_zones_count')
                    ->label(__('app.zones'))
                    ->counts('occupiedZones')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('zone_servers')
                    ->label(__('app.servers'))
                    ->state(function (BillingGroup $record): string {
                        return $record->assignedServers()->pluck('name')->join(', ') ?: '—';
                    }),

                Tables\Columns\IconColumn::make('is_closed')
                    ->label(__('app.closed'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('setServer')
                        ->label(__('app.assign_server'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Forms\Components\Select::make('server_id')
                                ->label(__('app.server'))
                                ->options(User::role('SERVER')->orderBy('name')->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records): void {
                            $serverId = $data['server_id'];
                            $zoneIds = \App\Models\OccupiedZone::whereIn('billing_group_id', $records->pluck('id'))
                                ->pluck('id');

                            \App\Models\OccupiedZone::whereIn('id', $zoneIds)
                                ->update(['server_id' => $serverId]);

                            // Record audit events
                            foreach ($records as $group) {
                                \App\Domain\Audit\Audit::record(
                                    'server_assigned',
                                    "Server ID {$serverId} assigned to all zones of billing group {$group->display_code}",
                                    ['server_id' => $serverId, 'billing_group_id' => $group->id],
                                    ['service_session_id' => $group->service_session_id],
                                );
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingGroups::route('/'),
            'view'  => Pages\ViewBillingGroup::route('/{record}'),
        ];
    }
}
