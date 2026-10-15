<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->unsignedInteger('receipt_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_name', 'receipt_mime', 'receipt_size']);
        });
    }
};
