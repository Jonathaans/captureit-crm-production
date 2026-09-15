<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->unsignedInteger('released_by')
                ->nullable()
                ->after('created_by');

            $table->string('released_by_name')
                ->nullable()
                ->after('released_by');

            $table->foreign(
                'released_by',
                'delivery_orders_released_by_fk'
            )
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign('delivery_orders_released_by_fk');

            $table->dropColumn([
                'released_by',
                'released_by_name',
            ]);
        });
    }
};
