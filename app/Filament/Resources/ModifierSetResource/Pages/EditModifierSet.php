<?php

namespace App\Filament\Resources\ModifierSetResource\Pages;

use App\Filament\Resources\ModifierSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModifierSet extends EditRecord
{
    protected static string $resource = ModifierSetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
