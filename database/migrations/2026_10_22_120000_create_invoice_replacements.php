<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('replaces_invoice_id')->nullable()->unique();
            $table->unsignedInteger('replaced_by_invoice_id')->nullable()->unique();
            $table->foreign('replaces_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('replaced_by_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });

        Schema::create('invoice_replacements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('source_invoice_id')->unique();
            $table->unsignedInteger('replacement_invoice_id')->unique();
            $table->unsignedBigInteger('proforma_invoice_id')->unique();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->text('reason');
            $table->char('preview_fingerprint', 64);
            $table->json('before');
            $table->timestamp('created_at');
            $table->foreign('source_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('replacement_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('proforma_invoice_id')->references('id')->on('proforma_invoices')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('invoice_replacements')->exists()) {
            throw new RuntimeException('Riwayat penggantian invoice harus dipertahankan. Gunakan migration korektif.');
        }
        Schema::dropIfExists('invoice_replacements');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['replaces_invoice_id']);
            $table->dropForeign(['replaced_by_invoice_id']);
            $table->dropUnique(['replaces_invoice_id']);
            $table->dropUnique(['replaced_by_invoice_id']);
            $table->dropColumn(['replaces_invoice_id', 'replaced_by_invoice_id']);
        });
    }
};
