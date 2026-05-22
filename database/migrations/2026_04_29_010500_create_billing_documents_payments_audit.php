<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained('billing_groups')->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained('printers');
            $table->string('document_type', 32); // INTERNAL_BILL / BILL_REPRINT
            $table->string('document_status', 16)->default('GENERATED'); // GENERATED/PRINTED/VOIDED
            $table->string('document_number')->nullable();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestampTz('printed_at')->nullable();
            $table->timestampTz('requested_at');
            $table->foreignId('reprint_of_billing_document_id')->nullable()->constrained('billing_documents');
            $table->boolean('is_reprint')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['billing_group_id', 'document_status']);
            $table->index(['document_status', 'requested_at']);
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained('billing_groups')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestampTz('recorded_at');
            $table->decimal('amount', 12, 2);
            $table->string('payment_label', 64); // CASH / CARD_EXTERNAL / OTHER
            $table->text('notes')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['billing_group_id', 'recorded_at']);
        });

        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues');
            $table->foreignId('service_session_id')->nullable()->constrained('service_sessions');
            $table->string('export_type', 32);   // SESSION / RANGE
            $table->timestampTz('export_range_start')->nullable();
            $table->timestampTz('export_range_end')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_format', 16)->default('CSV');
            $table->string('export_status', 16)->default('REQUESTED');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->nullable()->constrained('service_sessions');
            $table->string('event_type', 64);
            $table->timestampTz('event_time');
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->foreignId('billing_group_id')->nullable()->constrained('billing_groups');
            $table->foreignId('occupied_zone_id')->nullable()->constrained('occupied_zones');
            $table->foreignId('order_header_id')->nullable()->constrained('order_headers');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items');
            $table->foreignId('production_ticket_id')->nullable()->constrained('production_tickets');
            $table->foreignId('billing_document_id')->nullable()->constrained('billing_documents');
            $table->foreignId('payment_record_id')->nullable()->constrained('payment_records');
            $table->foreignId('accounting_export_id')->nullable()->constrained('accounting_exports');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('summary');
            $table->jsonb('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_session_id', 'event_time']);
            $table->index(['billing_group_id', 'event_time']);
            $table->index(['event_type', 'event_time']);
        });

        Schema::create('translation_keys', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 16);
            $table->string('translation_namespace', 64);
            $table->string('translation_key', 128);
            $table->text('translation_value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['language_code', 'translation_namespace', 'translation_key'], 'uniq_translation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_keys');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('payment_records');
        Schema::dropIfExists('billing_documents');
    }
};
