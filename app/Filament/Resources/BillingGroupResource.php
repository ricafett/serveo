<?php

namespace App\Filament\Resources;

use App\Domain\Audit\Audit;
use App\Filament\Resources\BillingGroupResource\RelationManagers\BillingDocumentsRelationManager;
use App\Filament\Resources\BillingGroupResource\Pages;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\OccupiedZone;
use App\Models\ServiceSession;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BillingGroupResource extends BaseResource
{
    protected static ?string $model = BillingGroup::class;

    protected static string|\UnitEnum|null $navigationGroup = 'app.navigation_group_operation';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'app.navigation_label_billing_groups';

    protected static ?string $modelLabel = 'app.model_label_billing_group';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_billing_groups';

    protected static ?int $navigationSort = 25;

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

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('billing.name'))
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('billing_status_id')
                ->label(__('app.status'))
                ->options(BillingStatus::where('is_active', true)->orderBy('sort_order')->pluck('display_name', 'id'))
                ->required(),

            Forms\Components\Select::make('service_session_id')
                ->label(__('app.session'))
                ->options(ServiceSession::orderBy('starts_at', 'desc')->pluck('session_label', 'id'))
                ->required(),

            Forms\Components\TextInput::make('cover_count')
                ->label(__('app.cover_count'))
                ->numeric()
                ->minValue(1),

            Forms\Components\Textarea::make('notes')
                ->label(__('app.notes'))
                ->maxLength(500)
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->selectable()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('billing.name'))
                    ->sortable()
                    ->searchable(),

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
                        ->action(function (array $data, Collection $records): void {
                            $serverId = $data['server_id'];
                            $zoneIds = OccupiedZone::whereIn('billing_group_id', $records->pluck('id'))
                                ->pluck('id');

                            OccupiedZone::whereIn('id', $zoneIds)
                                ->update(['server_id' => $serverId]);

                            // Record audit events
                            foreach ($records as $group) {
                                Audit::record(
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
            'create' => Pages\CreateBillingGroup::route('/create'),
            'edit' => Pages\EditBillingGroup::route('/{record}/edit'),
            'view' => Pages\ViewBillingGroup::route('/{record}/view'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            BillingDocumentsRelationManager::class,
        ];
    }
}
