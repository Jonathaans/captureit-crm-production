<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('proforma_number')->unique();
            $table->unsignedInteger('quote_id')->index();
            $table->unsignedInteger('invoice_id')->nullable()->unique();
            $table->string('status', 20)->default('issued')->index();
            $table->decimal('amount', 16, 2);
            $table->string('billing_method', 20);
            $table->decimal('billing_percentage', 8, 4);
            $table->json('quote_snapshot');
            $table->date('issued_at');
            $table->date('due_at');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // Preserve issued documents; deleting their quote/invoice requires
            // an explicit correction instead of silently destroying the link.
            $table->foreign('quote_id')->references('id')->on('quotes')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
