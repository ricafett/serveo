<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_sets', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('selection_mode', 16)->default('single'); // single | multiple
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('modifier_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_set_id')->constrained('modifier_sets')->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['modifier_set_id', 'display_name']);
        });

        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['menu_item_id', 'display_name']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('modifier_set_id')->nullable()->constrained('modifier_sets')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variant_name')->nullable();
            $table->string('modifier_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['variant_name', 'modifier_name']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modifier_set_id');
        });

        Schema::dropIfExists('menu_item_variants');
        Schema::dropIfExists('modifier_set_items');
        Schema::dropIfExists('modifier_sets');
    }
};
