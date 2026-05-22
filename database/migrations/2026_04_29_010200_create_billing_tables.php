<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('display_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained('service_sessions')->cascadeOnDelete();
            $table->string('display_code', 64);
            $table->foreignId('billing_status_id')->constrained('billing_statuses');
            $table->unsignedInteger('cover_count')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('opened_by_user_id')->constrained('users');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->unsignedBigInteger('version_number')->default(1);
            $table->timestamps();

            $table->unique(['service_session_id', 'display_code']);
            $table->index(['service_session_id', 'is_closed']);
        });

        Schema::create('occupied_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained('billing_groups')->cascadeOnDelete();
            $table->foreignId('row_id')->constrained('rows');
            $table->unsignedInteger('start_seat_pair_sequence');
            $table->unsignedInteger('end_seat_pair_sequence');
            $table->string('default_delivery_mode', 16)->default('CENTER'); // CENTER / SPECIFIC
            $table->string('delivery_center_label')->nullable();
            $table->foreignId('delivery_seat_pair_id')->nullable()->constrained('seat_pairs');
            $table->timestampTz('opened_at');
            $table->timestampTz('released_at')->nullable();
            $table->boolean('is_open')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['billing_group_id', 'is_open']);
            $table->index(['row_id', 'is_open', 'start_seat_pair_sequence', 'end_seat_pair_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupied_zones');
        Schema::dropIfExists('billing_groups');
        Schema::dropIfExists('billing_statuses');
    }
};
