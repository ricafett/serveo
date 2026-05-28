<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\CashierPrinterAssignment;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['cashier_printer_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $printerId = $this->data['cashier_printer_id'] ?? null;

        if ($printerId && $this->record->hasRole('CASHIER')) {
            CashierPrinterAssignment::updateOrCreate(
                ['user_id' => $this->record->id],
                ['printer_id' => $printerId, 'is_active' => true]
            );
        }
    }
}
