<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_document_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('quote_id')->index();
            $table->unsignedInteger('version');
            $table->unsignedInteger('actor_id');
            $table->string('actor_name');
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
            $table->unique(['quote_id', 'version']);
        });

        // No FK: removed quote lines must not remove issued invoice history.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quote_item_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Document revision history must be preserved. Use a corrective migration.');
    }
};
