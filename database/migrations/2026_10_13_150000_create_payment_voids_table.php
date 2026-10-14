<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_voids', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Audit evidence must outlive its source documents and users.
            $table->unsignedInteger('payment_id')->unique();
            $table->unsignedInteger('invoice_id')->index();
            $table->string('invoice_number');
            $table->unsignedInteger('actor_id');
            $table->text('reason');
            $table->json('snapshot');
            $table->json('result');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_voids') && DB::table('payment_voids')->exists()) {
            throw new RuntimeException('Payment void history exists; retain payment_voids.');
        }

        Schema::dropIfExists('payment_voids');
    }
};
