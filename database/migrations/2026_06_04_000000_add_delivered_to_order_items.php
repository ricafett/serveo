<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestampTz('delivered_at')->nullable()->after('void_reason');
            $table->foreignId('delivered_by_user_id')->nullable()->after('delivered_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivered_by_user_id');
            $table->dropColumn('delivered_at');
        });
    }
};
