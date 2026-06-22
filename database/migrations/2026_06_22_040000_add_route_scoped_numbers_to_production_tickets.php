<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tickets', function (Blueprint $table) {
            $table->string('ticket_sequence_route', 16)->nullable()->after('ticket_type');
            $table->unsignedInteger('route_ticket_number')->nullable()->after('ticket_sequence_route');

            $table->index(['service_session_id', 'ticket_sequence_route', 'route_ticket_number'], 'pt_route_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_tickets', function (Blueprint $table) {
            $table->dropIndex('pt_route_number_idx');
            $table->dropColumn(['ticket_sequence_route', 'route_ticket_number']);
        });
    }
};
