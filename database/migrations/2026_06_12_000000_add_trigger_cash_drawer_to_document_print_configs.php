<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->boolean('trigger_cash_drawer')->default(false)->after('copies');
        });
    }

    public function down(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->dropColumn('trigger_cash_drawer');
        });
    }
};
