<?php

namespace App\Filament\Resources\ServiceSessionResource\Pages;

use App\Filament\Resources\ServiceSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceSessions extends ListRecords
{
    protected static string $resource = ServiceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
