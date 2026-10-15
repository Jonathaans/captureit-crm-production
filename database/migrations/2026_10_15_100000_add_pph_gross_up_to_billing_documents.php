<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'proforma_invoices', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('pph_gross_up')->default(false);
                $table->decimal('pph_rate', 5, 2)->default(0);
                $table->decimal('pph_amount', 18, 4)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'proforma_invoices', 'invoices'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['pph_gross_up', 'pph_rate', 'pph_amount']));
        }
    }
};
