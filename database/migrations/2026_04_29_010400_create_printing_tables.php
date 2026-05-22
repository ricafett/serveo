<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('printer_type', 16); // KITCHEN/BAR/BILL/GENERIC
            $table->string('connection_type', 16); // LAN/USB_AGENT/NULL
            $table->string('address')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('agent_endpoint')->nullable(); // for USB_AGENT
            $table->string('agent_printer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('health_status', 16)->default('UNKNOWN'); // OK/UNREACHABLE/WARNING/UNKNOWN
            $table->timestampTz('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('printer_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues');
            $table->string('document_type', 32); // PRODUCTION_TICKET / BILL / VOID_SLIP
            $table->string('fulfillment_route', 16)->nullable(); // KITCHEN/BAR/NULL for bills
            $table->foreignId('printer_id')->constrained('printers');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'document_type', 'fulfillment_route'], 'uniq_route');
        });

        Schema::create('cashier_printer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained('printers');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'printer_id']);
        });

        Schema::create('production_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained('service_sessions');
            $table->foreignId('billing_group_id')->constrained('billing_groups')->cascadeOnDelete();
            $table->foreignId('occupied_zone_id')->nullable()->constrained('occupied_zones');
            $table->foreignId('printer_id')->constrained('printers');
            $table->string('ticket_type', 16);   // KITCHEN / BAR / VOID
            $table->string('ticket_status', 16)->default('PENDING'); // PENDING/PRINTED/FAILED/CANCELED
            $table->string('delivery_reference_label')->nullable();
            $table->timestampTz('printed_at')->nullable();
            $table->timestampTz('requested_at');
            $table->foreignId('reprint_of_ticket_id')->nullable()->constrained('production_tickets');
            $table->boolean('is_void_slip')->default(false);
            $table->boolean('is_reprint')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['ticket_status', 'requested_at']);
            $table->index(['billing_group_id']);
        });

        Schema::create('production_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_ticket_id')->constrained('production_tickets')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['production_ticket_id', 'order_item_id'], 'uniq_pti');
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_kind', 32); // PRODUCTION_TICKET / BILL
            $table->morphs('printable');     // production_ticket / billing_document
            $table->foreignId('printer_id')->constrained('printers');
            $table->string('status', 16)->default('PENDING'); // PENDING/IN_PROGRESS/PRINTED/FAILED/CANCELED
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->text('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('production_ticket_items');
        Schema::dropIfExists('production_tickets');
        Schema::dropIfExists('cashier_printer_assignments');
        Schema::dropIfExists('printer_routes');
        Schema::dropIfExists('printers');
    }
};
