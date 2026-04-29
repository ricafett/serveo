<?php

namespace App\Filament\Resources\PrinterRouteResource\Pages;

use App\Filament\Resources\PrinterRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrinterRoute extends EditRecord
{
    protected static string $resource = PrinterRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
