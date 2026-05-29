<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Printer;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'app.navigation_label_users';

    protected static ?string $modelLabel = 'app.model_label_user';

    protected static ?string $pluralModelLabel = 'app.plural_model_label_users';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('user.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('user.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('user.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('user.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('username')->required()->unique(ignoreRecord: true)->maxLength(64),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('preferred_language_code')
                ->options(['pt-PT' => __('app.language_pt'), 'en-US' => __('app.language_en')])
                ->default('pt-PT')->required(),
            Forms\Components\Select::make('theme')
                ->options([
                    User::THEME_LIGHT => __('app.theme_light'),
                    User::THEME_DARK => __('app.theme_dark'),
                    User::THEME_SYSTEM => __('app.theme_system'),
                ])
                ->default(User::THEME_SYSTEM)
                ->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                ->required(fn (string $context) => $context === 'create')
                ->autocomplete('new-password'),
            Forms\Components\Select::make('roles')
                ->multiple()
                ->relationship('roles', 'name')
                ->options(Role::pluck('name', 'name'))
                ->preload()
                ->live()
                ->required(),
            Forms\Components\Select::make('cashier_printer_id')
                ->label(__('app.cashier_printer'))
                ->options(
                    Printer::where('is_active', true)
                        ->pluck('name', 'id')
                )
                ->visible(function (Get $get) {
                    return collect($get('roles'))
                        ->map(fn ($id) => (int) $id)
                        ->contains(Role::where('name', 'CASHIER')->value('id'));
                })
                ->helperText(__('app.cashier_printer_help'))
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label(__('app.roles')),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label(__('app.active')),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->label(__('app.last_login'))->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
