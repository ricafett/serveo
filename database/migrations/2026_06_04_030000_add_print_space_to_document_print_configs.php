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
            $table->unsignedTinyInteger('print_begin_space')->default(0)->after('branding_header');
            $table->unsignedTinyInteger('print_end_space')->default(3)->after('print_begin_space');
        });

        // Sale vouchers default to 0 end space (single-item tickets don't need extra feeds).
        DocumentPrintConfig::where('document_type', DocumentPrintConfig::DOC_SALE_VOUCHER)
            ->update(['print_end_space' => 0]);
    }

    public function down(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->dropColumn(['print_begin_space', 'print_end_space']);
        });
    }
};
