<?php

namespace App\Filament\Resources\ServiceSessionResource\Pages;

use App\Filament\Resources\ServiceSessionResource;
use App\Models\ServiceSession;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceSession extends CreateRecord
{
    protected static string $resource = ServiceSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === 'OPEN') {
            $alreadyOpen = ServiceSession::where('venue_id', $data['venue_id'])
                ->where('status', 'OPEN')
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
