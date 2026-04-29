<?php

namespace App\Filament\Resources\BillingStatusResource\Pages;

use App\Filament\Resources\BillingStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillingStatuses extends ListRecords
{
    protected static string $resource = BillingStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
