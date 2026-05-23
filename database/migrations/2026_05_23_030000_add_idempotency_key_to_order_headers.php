<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_headers', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('notes');
            $table->unique(['billing_group_id', 'idempotency_key'], 'uq_order_headers_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('order_headers', function (Blueprint $table) {
            $table->dropUnique('uq_order_headers_idempotency');
            $table->dropColumn('idempotency_key');
        });
    }
};
