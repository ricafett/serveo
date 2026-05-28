<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->unsignedTinyInteger('print_char_width')->default(42)->after('agent_printer_id');
            $table->unsignedTinyInteger('print_begin_space')->default(0)->after('print_char_width');
            $table->unsignedTinyInteger('print_end_space')->default(3)->after('print_begin_space');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['print_char_width', 'print_begin_space', 'print_end_space']);
        });
    }
};
