<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('venue_code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('session_type', 32);     // LUNCH / DINNER / EVENT
            $table->string('session_label');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('status', 32)->default('OPEN'); // PLANNED / OPEN / CLOSED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'session_label']);
            $table->index(['venue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_sessions');
        Schema::dropIfExists('venues');
    }
};
