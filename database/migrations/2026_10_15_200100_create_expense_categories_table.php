<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $code = $table->string('code')->unique();
            $table->string('name');
            $normalizedName = $table->string('normalized_name')->unique();
            // Keep legacy codes distinct and make name normalization consistent across databases.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $code->collation('utf8mb4_bin');
                $normalizedName->collation('utf8mb4_bin');
            }
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $categories = [
            'transport' => 'Transport', 'crew' => 'Crew', 'printing' => 'Printing',
            'equipment' => 'Equipment', 'vendor' => 'Vendor', 'consumption' => 'Consumption',
            'other' => 'Other', 'vendor_outsource' => 'Vendor Outsource',
        ];

        // Preserve custom codes already used by historical transactions.
        if (Schema::hasTable('expenses')) {
            foreach (DB::table('expenses')->whereNotNull('category')->distinct()->pluck('category') as $code) {
                if ($code !== '' && ! array_key_exists($code, $categories)) {
                    $categories[$code] = mb_substr(ucwords(str_replace('_', ' ', $code)), 0, 255);
                }
            }
        }

        $names = [];
        foreach ($categories as $code => $label) {
            $name = trim(preg_replace('/\s+/u', ' ', $label)) ?: 'Kategori lama';
            $base = $name;
            for ($suffix = 2; in_array(mb_strtolower($name), $names, true); $suffix++) {
                $name = mb_substr($base, 0, 240).' ('.$suffix.')';
            }
            $names[] = $normalized = mb_strtolower($name);
            DB::table('expense_categories')->insert([
                'code' => (string) $code, 'name' => $name, 'normalized_name' => $normalized,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
