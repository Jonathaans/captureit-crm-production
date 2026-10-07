<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_equipment_template_items', function (Blueprint $table) {
            $table->string('quantity_basis', 20)->default('equipment');
            $table->boolean('requires_inventory')->default(false);
        });
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->boolean('requires_inventory')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('product_equipment_template_items')->where('requires_inventory', true)->orWhere('quantity_basis', '!=', 'equipment')->exists()
            || DB::table('delivery_order_items')->where('requires_inventory', true)->exists()) {
            throw new RuntimeException('Requirement rules are in use; preserve them and roll forward.');
        }
        Schema::table('delivery_order_items', fn (Blueprint $table) => $table->dropColumn('requires_inventory'));
        Schema::table('product_equipment_template_items', fn (Blueprint $table) => $table->dropColumn(['quantity_basis', 'requires_inventory']));
    }
};
