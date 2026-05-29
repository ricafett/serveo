<?php

namespace App\Filament\Resources\ServiceSessionResource\Pages;

use App\Filament\Resources\ServiceSessionResource;
use App\Models\ServiceSession;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceSession extends EditRecord
{
    protected static string $resource = ServiceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'OPEN') {
            $alreadyOpen = ServiceSession::where('venue_id', $data['venue_id'])
                ->where('status', 'OPEN')
                ->where('id', '!=', $this->record->id)
                ->exists();

            if ($alreadyOpen) {
                throw new \RuntimeException(
                    __('app.session_already_open')
                );
            }
        }

        if (($data['status'] ?? null) !== 'OPEN' && $this->record->status === 'OPEN') {
            $hasOpenGroups = $this->record->billingGroups()->where('is_closed', false)->exists();

            if ($hasOpenGroups) {
                throw new \RuntimeException(
                    __('app.session_has_open_groups')
                );
            }
        }

        return $data;
    }
}
