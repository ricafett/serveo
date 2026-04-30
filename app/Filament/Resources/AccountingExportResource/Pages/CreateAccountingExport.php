<?php

namespace App\Filament\Resources\AccountingExportResource\Pages;

use App\Filament\Resources\AccountingExportResource;
use App\Jobs\GenerateAccountingExportJob;
use App\Models\AccountingExport;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingExport extends CreateRecord
{
    protected static string $resource = AccountingExportResource::class;

    protected function afterCreate(): void
    {
        /** @var AccountingExport $export */
        $export = $this->record;

        GenerateAccountingExportJob::dispatch($export->id);
    }
}
