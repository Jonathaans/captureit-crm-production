<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('billing_type', 20)->default('down_payment');
            $table->unsignedInteger('dp_invoice_id')->nullable();
            $table->string('conversion_reason', 30)->nullable();
            $table->unsignedInteger('converted_by')->nullable();
            $table->text('conversion_note')->nullable();
            $table->foreign('dp_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });

        // The first proforma workflow only converted on an actual DP receipt.
        DB::table('proforma_invoices')->where('status', 'converted')
            ->update(['conversion_reason' => 'payment_received']);
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropForeign(['dp_invoice_id']);
            $table->dropColumn(['billing_type', 'dp_invoice_id', 'conversion_reason', 'converted_by', 'conversion_note']);
        });
    }
};
