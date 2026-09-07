<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_operational_heartbeats')) {
            return;
        }

        Schema::create('crm_operational_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('status', 20)->default('unknown')->index();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_success_at')->nullable()->index();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_operational_heartbeats');
    }
};
