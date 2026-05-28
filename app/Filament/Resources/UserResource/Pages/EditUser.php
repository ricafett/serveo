<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\CashierPrinterAssignment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cashier_printer_id'] = $this->record->cashierPrinterAssignment?->printer_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['cashier_printer_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $printerId = $this->data['cashier_printer_id'] ?? null;

        if ($printerId && $this->record->hasRole('CASHIER')) {
            CashierPrinterAssignment::updateOrCreate(
                ['user_id' => $this->record->id],
                ['printer_id' => $printerId, 'is_active' => true]
            );
        } else {
            // Deactivate assignment if user is no longer CASHIER or no printer selected
            $this->record->cashierPrinterAssignment?->update(['is_active' => false]);
        }
    }
}
