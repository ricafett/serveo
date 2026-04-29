<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditEventResource\Pages;
use App\Domain\Audit\Audit;
use App\Models\AuditEvent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;
    protected static string | UnitEnum | null $navigationGroup = 'Auditoria';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Eventos de auditoria';
    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_time')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('event_type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('actor.name')->label('Utilizador'),
                Tables\Columns\TextColumn::make('billing_group_id')->label('Grupo'),
                Tables\Columns\TextColumn::make('summary')->wrap()->searchable(),
            ])
            ->defaultSort('event_time', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')->options(
                    collect(Audit::TYPES)->mapWithKeys(fn ($t) => [$t => $t])->all()
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditEvents::route('/'),
        ];
    }
}
