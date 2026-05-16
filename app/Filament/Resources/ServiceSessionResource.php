<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\ServiceSessionResource\Pages;
use App\Models\ServiceSession;
use App\Models\Venue;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ServiceSessionResource extends BaseResource
{
    protected static ?string $model = ServiceSession::class;
    protected static string | UnitEnum | null $navigationGroup = 'app.navigation_group_operation';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'app.navigation_label_service_sessions';
    protected static ?int $navigationSort = 5;

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
            Forms\Components\Select::make('venue_id')
                ->options(Venue::query()->pluck('name', 'id'))
                ->required(),
            Forms\Components\Select::make('session_type')->options([
                'LUNCH'  => __('app.session_type_lunch'),
                'DINNER' => __('app.session_type_dinner'),
                'EVENT'  => __('app.session_type_event'),
            ])->required(),
            Forms\Components\TextInput::make('session_label')->required()
                ->helperText(__('app.helper_text_session_label')),
            Forms\Components\DateTimePicker::make('starts_at')->required(),
            Forms\Components\DateTimePicker::make('ends_at')->nullable(),
            Forms\Components\Select::make('status')->options([
                'PLANNED' => __('app.status_planned'),
                'OPEN'    => __('app.status_open'),
                'CLOSED'  => __('app.status_closed'),
            ])->required()->default('OPEN'),
            Forms\Components\Textarea::make('notes')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('venue.name')->label(__('app.venue')),
                Tables\Columns\TextColumn::make('session_label'),
                Tables\Columns\TextColumn::make('session_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray'    => 'PLANNED',
                    'success' => 'OPEN',
                    'danger'  => 'CLOSED',
                ]),
                Tables\Columns\TextColumn::make('starts_at')->dateTime(),
                Tables\Columns\TextColumn::make('billing_groups_count')->counts('billingGroups')->label(__('billing.group_title')),
            ])
            ->defaultSort('starts_at', 'desc')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
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
