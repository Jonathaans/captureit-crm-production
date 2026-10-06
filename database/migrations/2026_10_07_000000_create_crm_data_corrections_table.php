<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_data_corrections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kind', 40)->index();
            // Deliberately no FK: audit evidence outlives the removed document.
            $table->string('invoice_number')->nullable()->unique();
            $table->unsignedInteger('actor_id');
            $table->text('reason');
            $table->json('snapshot');
            $table->json('result');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // A rollback must never erase correction evidence or reserved numbers.
        if (Schema::hasTable('crm_data_corrections') && Schema::getConnection()->table('crm_data_corrections')->exists()) {
            throw new RuntimeException('Correction history exists; retain crm_data_corrections.');
        }

        Schema::dropIfExists('crm_data_corrections');
    }
};
