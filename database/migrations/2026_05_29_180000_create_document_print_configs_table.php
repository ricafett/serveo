<?php

use App\Models\DocumentPrintConfig;
use App\Models\FulfillmentRoute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_print_configs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->string('fulfillment_route', 32)->nullable();
            $table->boolean('group_items')->default(true);
            $table->boolean('ignore_variants')->default(false);
            $table->boolean('ignore_modifiers')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['document_type', 'fulfillment_route']);
        });

        // Backfill configs for existing deployments that already have fulfillment routes.
        foreach (FulfillmentRoute::all() as $route) {
            DocumentPrintConfig::firstOrCreate(
                ['document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => $route->code],
                ['group_items' => true, 'ignore_variants' => false, 'ignore_modifiers' => false],
            );
        }

        // BILL config (no sub-type).
        DocumentPrintConfig::firstOrCreate(
            ['document_type' => 'BILL', 'fulfillment_route' => null],
            ['group_items' => false, 'ignore_variants' => false, 'ignore_modifiers' => false],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('document_print_configs');
    }
};
