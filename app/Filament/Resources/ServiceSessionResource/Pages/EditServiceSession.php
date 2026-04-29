<?php

namespace App\Filament\Resources\ServiceSessionResource\Pages;

use App\Filament\Resources\ServiceSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceSession extends EditRecord
{
    protected static string $resource = ServiceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
