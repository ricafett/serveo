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

        return $data;
    }
}
