<?php

use App\Models\DocumentPrintConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentPrintConfig::query()
            ->where(function ($query) {
                $query->whereNull('branding_header')
                    ->orWhere('branding_header', '');
            })
            ->update([
                'branding_header' => DocumentPrintConfig::defaultBrandingHeader(),
            ]);
    }

    public function down(): void
    {
        // Intentionally left blank: do not remove branding headers once backfilled.
    }
};
