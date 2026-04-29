<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceSessionResource\Pages;
use App\Models\ServiceSession;
use App\Models\Venue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceSessionResource extends Resource
{
    protected static ?string $model = ServiceSession::class;
    protected static ?string $navigationGroup = 'Operação';
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Sessões de serviço';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('venue_id')
                ->options(Venue::query()->pluck('name', 'id'))
                ->required(),
            Forms\Components\Select::make('session_type')->options([
                'LUNCH'  => 'Almoço',
                'DINNER' => 'Jantar',
                'EVENT'  => 'Evento',
            ])->required(),
            Forms\Components\TextInput::make('session_label')->required()
                ->helperText('Único por venue. Ex.: "2026-04-29 DINNER"'),
            Forms\Components\DateTimePicker::make('starts_at')->required(),
            Forms\Components\DateTimePicker::make('ends_at')->nullable(),
            Forms\Components\Select::make('status')->options([
                'PLANNED' => 'Planeada',
                'OPEN'    => 'Aberta',
                'CLOSED'  => 'Fechada',
            ])->required()->default('OPEN'),
            Forms\Components\Textarea::make('notes')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('venue.name')->label('Venue'),
                Tables\Columns\TextColumn::make('session_label'),
                Tables\Columns\TextColumn::make('session_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray'    => 'PLANNED',
                    'success' => 'OPEN',
                    'danger'  => 'CLOSED',
                ]),
                Tables\Columns\TextColumn::make('starts_at')->dateTime(),
                Tables\Columns\TextColumn::make('billing_groups_count')->counts('billingGroups')->label('Grupos'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServiceSessions::route('/'),
            'create' => Pages\CreateServiceSession::route('/create'),
            'edit'   => Pages\EditServiceSession::route('/{record}/edit'),
        ];
    }
}
