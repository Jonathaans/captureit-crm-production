<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proforma snapshots already accept 2,000 characters. Linked documents must fit them too.
        foreach (['quotes', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('payment_term')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $length = DB::getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        // Check both tables first; never truncate terms that have already been agreed with a customer.
        foreach (['quotes', 'invoices'] as $name) {
            if (DB::table($name)->whereRaw($length.'(payment_term) > 100')->exists()) {
                throw new RuntimeException('Payment terms exceed the old 100-character limit. Use a corrective migration to preserve them.');
            }
        }
        foreach (['quotes', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('payment_term', 100)->nullable()->change();
            });
        }
    }
};
