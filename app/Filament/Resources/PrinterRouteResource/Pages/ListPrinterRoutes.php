<?php

namespace App\Filament\Resources\PrinterRouteResource\Pages;

use App\Filament\Resources\PrinterRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrinterRoutes extends ListRecords
{
    protected static string $resource = PrinterRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
