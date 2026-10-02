<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('unit', 10)->default('pcs');
        });

        foreach (['lead_products', 'quote_items', 'invoice_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Do not rewrite historical quantities, prices or totals.
                $table->string('unit', 10)->nullable();
                $table->decimal('equipment_quantity', 12, 4)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Keeping the columns prevents loss of billing semantics on rollback.
        // Roll forward with a corrective migration after any documents use units.
        throw new RuntimeException('Sales units contain document data; this migration cannot be rolled back destructively.');
    }
};
