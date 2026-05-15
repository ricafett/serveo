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
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;
    protected static string | UnitEnum | null $navigationGroup = 'app.navigation_group_audit';
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'app.navigation_label_audit_events';
    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('event_log.view_limited') || Auth::user()?->can('event_log.view_full') ?? false;
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
                Tables\Columns\TextColumn::make('actor.name')->label(__('app.user')),
                Tables\Columns\TextColumn::make('billing_group_id')->label(__('billing.group_title')),
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
