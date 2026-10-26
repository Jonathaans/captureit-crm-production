<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_consumable_usages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('delivery_order_id');
            $table->unsignedInteger('inventory_item_id');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['delivery_order_id', 'inventory_item_id'], 'consumable_usage_order_item_unique');
            $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->restrictOnDelete();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_consumable_usages');
    }
};
