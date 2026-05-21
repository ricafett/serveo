<?php

namespace App\Filament\Resources\BillingGroupResource\Pages;

use App\Filament\Resources\BillingGroupResource;
use App\Models\BillingStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateBillingGroup extends CreateRecord
{
    protected static string $resource = BillingGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $next = (int) \App\Models\BillingGroup::where('service_session_id', $data['service_session_id'])->count() + 1;
        $data['display_code'] = 'G-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        $data['opened_by_user_id'] = auth()->id();
        $data['opened_at'] = now();
        $data['is_closed'] = false;
        $data['version_number'] = 1;

        return $data;
    }
}
