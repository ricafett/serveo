<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('display_name');
            $table->string('route_type', 16); // KITCHEN / BAR / NONE
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('menu_categories');
            $table->string('sku')->nullable();
            $table->string('code')->nullable();
            $table->string('display_name');
            $table->string('short_name')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('tax_code', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_category_id', 'is_active']);
        });

        Schema::create('order_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained('billing_groups')->cascadeOnDelete();
            $table->foreignId('occupied_zone_id')->nullable()->constrained('occupied_zones');
            $table->foreignId('ordered_by_user_id')->constrained('users');
            $table->timestampTz('ordered_at');
            $table->string('submission_status', 32)->default('SUBMITTED'); // DRAFT/SUBMITTED/PARTIALLY_VOIDED/VOIDED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['billing_group_id', 'ordered_at']);
            $table->index(['occupied_zone_id', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_header_id')->constrained('order_headers')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->string('fulfillment_route', 16); // KITCHEN/BAR/NONE - denormalized
            $table->foreignId('delivery_seat_pair_id')->nullable()->constrained('seat_pairs');
            $table->string('delivery_reference_label')->nullable();
            $table->timestampTz('sent_to_production_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->text('void_reason')->nullable();
            $table->foreignId('parent_order_item_id')->nullable()->constrained('order_items');
            $table->timestamps();

            $table->index(['order_header_id']);
            $table->index(['fulfillment_route', 'sent_to_production_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_headers');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
