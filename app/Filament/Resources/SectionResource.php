<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SectionResource\Pages;
use App\Models\Section;
use App\Models\Venue;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SectionResource extends BaseResource
{
    protected static ?string $model = Section::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'app.navigation_label_sections';

    protected static ?int $navigationSort = 20;

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
                ->required()
                ->default(fn () => Venue::query()->value('id')),
            Forms\Components\TextInput::make('section_code')->required()->maxLength(32),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('venue.name')->label(__('app.venue'))->sortable(),
                Tables\Columns\TextColumn::make('section_code')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('rows_count')->counts('rows')->label(__('app.rows')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSections::route('/'),
            'create' => Pages\CreateSection::route('/create'),
            'edit' => Pages\EditSection::route('/{record}/edit'),
        ];
    }
}
