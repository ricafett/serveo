<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('section_code', 32);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'section_code']);
        });

        Schema::create('rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('row_code', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section_id', 'row_code']);
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('rows')->cascadeOnDelete();
            $table->unsignedInteger('seat_number');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['row_id', 'seat_number']);
        });

        Schema::create('seat_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('rows')->cascadeOnDelete();
            $table->unsignedInteger('pair_sequence');
            $table->foreignId('seat_a_id')->constrained('seats');
            $table->foreignId('seat_b_id')->constrained('seats');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['row_id', 'pair_sequence']);
            $table->unique(['row_id', 'seat_a_id']);
            $table->unique(['row_id', 'seat_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_pairs');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('rows');
        Schema::dropIfExists('sections');
    }
};
