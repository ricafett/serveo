<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained('service_sessions');
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->string('movement_type'); // CASH_IN, CASH_OUT
            $table->decimal('amount', 10, 2);
            $table->string('label');
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['service_session_id', 'recorded_at']);
            $table->index(['cashier_user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
