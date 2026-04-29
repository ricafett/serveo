<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RowResource\Pages;
use App\Models\Row;
use App\Models\Section;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RowResource extends Resource
{
    protected static ?string $model = Row::class;
    protected static ?string $navigationGroup = 'Configuração';
    protected static ?string $navigationIcon  = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'Linhas (Rows)';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
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
            ->columns([
                Tables\Columns\TextColumn::make('section.section_code')->label('Sala')->sortable(),
                Tables\Columns\TextColumn::make('row_code')->sortable(),
                Tables\Columns\TextColumn::make('seats_count')->counts('seats')->label('Lugares'),
                Tables\Columns\TextColumn::make('seat_pairs_count')->counts('seatPairs')->label('Pares'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRows::route('/'),
            'create' => Pages\CreateRow::route('/create'),
            'edit'   => Pages\EditRow::route('/{record}/edit'),
        ];
    }
}
