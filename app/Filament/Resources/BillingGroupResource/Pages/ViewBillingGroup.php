<?php

namespace App\Filament\Resources\BillingGroupResource\Pages;

use App\Domain\Audit\Audit;
use App\Filament\Resources\BillingGroupResource;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
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
