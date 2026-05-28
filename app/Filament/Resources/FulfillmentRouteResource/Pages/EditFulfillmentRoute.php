<?php

namespace App\Filament\Resources\FulfillmentRouteResource\Pages;

use App\Filament\Resources\FulfillmentRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFulfillmentRoute extends EditRecord
{
    protected static string $resource = FulfillmentRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
