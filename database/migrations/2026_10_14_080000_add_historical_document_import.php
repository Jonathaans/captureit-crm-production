<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_document_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('created_by')->index();
            $table->string('source');
            $table->string('status', 20)->default('draft');
            $table->json('payload');
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        foreach (['quotes', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->uuid('historical_batch_id')->nullable()->index();
                if ($name === 'quotes') {
                    $table->date('document_date')->nullable();
                }
            });
            // Legacy documents may exceed the original DECIMAL(12,4) limit (Rp 100m).
            Schema::table($name, function (Blueprint $table) use ($name) {
                foreach (['sub_total', 'grand_total', 'discount_amount', 'tax_amount', 'adjustment_amount'] as $column) {
                    $table->decimal($column, 18, 4)->nullable($name === 'quotes')->default(0)->change();
                }
                if ($name === 'invoices') {
                    $table->decimal('paid_amount', 18, 4)->default(0)->change();
                    $table->decimal('balance_due', 18, 4)->default(0)->change();
                }
            });
        }
        foreach (['quote_items', 'invoice_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                foreach (['price', 'total', 'discount_amount', 'tax_amount'] as $column) {
                    $table->decimal($column, 18, 4)->nullable(in_array($column, ['discount_amount', 'tax_amount'], true))->default(0)->change();
                }
            });
        }
        Schema::table('payments', fn (Blueprint $table) => $table->decimal('amount', 18, 4)->change());
        Schema::create('crm_document_number_locks', function (Blueprint $table) {
            $table->string('type', 10)->primary();
        });
        Schema::create('crm_document_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('number', 100);
            $table->string('number_key', 64);
            $table->unique(['type', 'number_key']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Historical documents and reserved numbers must be preserved. Use a corrective migration.');
    }
};
