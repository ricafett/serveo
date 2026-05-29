<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_sets', function (Blueprint $table) {
            $table->boolean('assume_default')->default(false);
        });

        Schema::table('modifier_set_items', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('modifier_sets', function (Blueprint $table) {
            $table->dropColumn('assume_default');
        });

        Schema::table('modifier_set_items', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
