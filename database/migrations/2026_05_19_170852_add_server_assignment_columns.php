<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Static venue config: default server for a seat pair (Admin domain)
        Schema::table('seat_pairs', function (Blueprint $table) {
            $table->foreignId('default_server_id')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Live operational state: server actively working a zone (Server domain)
        Schema::table('occupied_zones', function (Blueprint $table) {
            $table->foreignId('server_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('occupied_zones', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropColumn('server_id');
        });

        Schema::table('seat_pairs', function (Blueprint $table) {
            $table->dropForeign(['default_server_id']);
            $table->dropColumn('default_server_id');
        });
    }
};
