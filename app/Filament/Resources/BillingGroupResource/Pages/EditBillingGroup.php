<?php

namespace App\Filament\Resources\BillingGroupResource\Pages;

use App\Filament\Resources\BillingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBillingGroup extends EditRecord
{
    protected static string $resource = BillingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
