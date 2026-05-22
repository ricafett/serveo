<?php

namespace App\Filament\Resources\ModifierSetResource\Pages;

use App\Filament\Resources\ModifierSetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierSets extends ListRecords
{
    protected static string $resource = ModifierSetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
