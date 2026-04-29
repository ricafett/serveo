<?php

namespace App\Filament\Resources\BillingStatusResource\Pages;

use App\Filament\Resources\BillingStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBillingStatus extends EditRecord
{
    protected static string $resource = BillingStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
