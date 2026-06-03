<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_voucher_enabled')->default(false)->after('is_active');
            $table->index('is_voucher_enabled');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained('service_sessions');
            $table->string('display_code')->unique();
            $table->foreignId('sold_by_user_id')->constrained('users');
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_label', 64);
            $table->timestampTz('sold_at');
            $table->timestamps();

            $table->index(['service_session_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->string('display_name_snapshot');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestampTz('recorded_at');
            $table->decimal('amount', 12, 2);
            $table->string('payment_label', 64);
            $table->text('notes')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['sale_id', 'recorded_at']);
        });

        Schema::create('sale_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained('printers');
            $table->string('document_type', 32);
            $table->string('document_status', 16)->default('GENERATED');
            $table->string('document_number')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestampTz('printed_at')->nullable();
            $table->timestampTz('requested_at');
            $table->foreignId('reprint_of_sale_document_id')->nullable()->constrained('sale_documents');
            $table->boolean('is_reprint')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['sale_id', 'document_status']);
            $table->index(['document_status', 'requested_at']);
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('payment_record_id')->constrained('sales');
            $table->foreignId('sale_payment_id')->nullable()->after('sale_id')->constrained('sale_payments');
            $table->foreignId('sale_document_id')->nullable()->after('sale_payment_id')->constrained('sale_documents');
            $table->index(['sale_id', 'event_time']);
        });

        Schema::table('accounting_exports', function (Blueprint $table) {
            $table->string('source_domain', 16)->default('ALL')->after('export_type');
        });

        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_document_id');
            $table->dropConstrainedForeignId('sale_payment_id');
            $table->dropConstrainedForeignId('sale_id');
        });

        Schema::table('accounting_exports', function (Blueprint $table) {
            $table->dropColumn('source_domain');
        });

        Schema::dropIfExists('sale_documents');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['is_voucher_enabled']);
            $table->dropColumn('is_voucher_enabled');
        });
    }
};
