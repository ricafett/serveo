<?php

namespace App\Filament\Resources\FulfillmentRouteResource\Pages;

use App\Filament\Resources\FulfillmentRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentRoutes extends ListRecords
{
    protected static string $resource = FulfillmentRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
