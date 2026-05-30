<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_sets', function (Blueprint $table) {
            $table->foreignId('default_modifier_set_item_id')->nullable()->constrained('modifier_set_items')->nullOnDelete();
        });

        Schema::table('modifier_set_items', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_set_items', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });

        Schema::table('modifier_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_modifier_set_item_id');
        });
    }
};
