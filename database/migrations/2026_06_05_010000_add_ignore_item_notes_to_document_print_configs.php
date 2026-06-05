<?php

use App\Models\DocumentPrintConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->boolean('ignore_item_notes')->default(false)->after('ignore_modifiers');
        });

        // Set defaults: ignore_item_notes = true for BILL, SALE_VOUCHER, SALE_RECEIPT.
        // Production tickets keep the default of false (notes shown).
        DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_BILL)
            ->update(['ignore_item_notes' => true]);

        DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_SALE_VOUCHER)
            ->update(['ignore_item_notes' => true]);

        DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_SALE_RECEIPT)
            ->update(['ignore_item_notes' => true]);
    }

    public function down(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->dropColumn('ignore_item_notes');
        });
    }
};
