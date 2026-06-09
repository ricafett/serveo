<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('copies')->default(0)->after('print_end_space');
        });
    }

    public function down(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->dropColumn('copies');
        });
    }
};
