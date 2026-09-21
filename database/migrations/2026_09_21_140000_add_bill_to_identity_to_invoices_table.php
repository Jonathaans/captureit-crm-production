<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $missing = [
            'bill_to_display_mode' => ! Schema::hasColumn('invoices', 'bill_to_display_mode'),
            'bill_to_person_name' => ! Schema::hasColumn('invoices', 'bill_to_person_name'),
            'bill_to_company_name' => ! Schema::hasColumn('invoices', 'bill_to_company_name'),
        ];

        Schema::table('invoices', function (Blueprint $table) use ($missing) {
            if ($missing['bill_to_display_mode']) {
                $table->string('bill_to_display_mode', 20)->nullable();
            }

            if ($missing['bill_to_person_name']) {
                $table->string('bill_to_person_name')->nullable();
            }

            if ($missing['bill_to_company_name']) {
                $table->string('bill_to_company_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $columns = collect([
            'bill_to_display_mode',
            'bill_to_person_name',
            'bill_to_company_name',
        ])->filter(
            fn (string $column): bool => Schema::hasColumn('invoices', $column)
        )->all();

        if ($columns === []) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
