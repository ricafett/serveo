<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;

use App\Filament\Resources\BillingStatusResource\Pages;
use App\Models\BillingStatus;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BillingStatusResource extends Resource
{
    protected static ?string $model = BillingStatus::class;
    protected static string | UnitEnum | null $navigationGroup = 'Configuração';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-flag';
    protected static ?string $navigationLabel = 'Estados de grupo';
    protected static ?int $navigationSort = 50;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('status.configure') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('status.configure') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('status.configure') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('status.configure') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(64),
            Forms\Components\TextInput::make('display_name')->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('code')->badge(),
                Tables\Columns\TextColumn::make('display_name'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBillingStatuses::route('/'),
            'create' => Pages\CreateBillingStatus::route('/create'),
            'edit'   => Pages\EditBillingStatus::route('/{record}/edit'),
        ];
    }
}
