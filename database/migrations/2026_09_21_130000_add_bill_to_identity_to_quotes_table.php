<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('bill_to_display_mode', 20)
                ->default('person');
            $table->string('bill_to_person_name')
                ->nullable();
            $table->string('bill_to_company_name')
                ->nullable();
            $table->string('client_signer_name')
                ->nullable();
            $table->string('client_signer_company')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'bill_to_display_mode',
                'bill_to_person_name',
                'bill_to_company_name',
                'client_signer_name',
                'client_signer_company',
            ]);
        });
    }
};
