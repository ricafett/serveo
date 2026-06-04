<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->text('branding_header')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('document_print_configs', function (Blueprint $table) {
            $table->dropColumn('branding_header');
        });
    }
};
