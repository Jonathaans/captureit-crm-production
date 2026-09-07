<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        /*
         * The original schema allowed only one Invoice for each Quote.
         * Flexible DP + Pelunasan requires one-to-many while application
         * locking below still prevents duplicate active billing types.
         */
        $initialIndexes = collect(Schema::getIndexes('invoices'));

        /*
         * MySQL may use the old unique quote_id index to support its foreign
         * key. Add a non-unique replacement first, otherwise DROP INDEX can
         * fail with "needed in a foreign key constraint".
         */
        if (! $initialIndexes->pluck('name')->contains('crm_invoices_quote_idx')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index('quote_id', 'crm_invoices_quote_idx');
            });
        }

        foreach (Schema::getIndexes('invoices') as $index) {
            $columns = array_values($index['columns'] ?? []);

            if (
                ($index['unique'] ?? false)
                && $columns === ['quote_id']
            ) {
                Schema::table('invoices', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index['name']);
                });
            }
        }

        $missing = [
            'billing_type' => ! Schema::hasColumn('invoices', 'billing_type'),
            'billing_method' => ! Schema::hasColumn('invoices', 'billing_method'),
            'billing_percentage' => ! Schema::hasColumn('invoices', 'billing_percentage'),
            'quote_total_snapshot' => ! Schema::hasColumn('invoices', 'quote_total_snapshot'),
            'billing_amount' => ! Schema::hasColumn('invoices', 'billing_amount'),
            'remaining_amount_snapshot' => ! Schema::hasColumn('invoices', 'remaining_amount_snapshot'),
            'dp_invoice_id' => ! Schema::hasColumn('invoices', 'dp_invoice_id'),
            'billing_created_by' => ! Schema::hasColumn('invoices', 'billing_created_by'),
            'billing_locked_at' => ! Schema::hasColumn('invoices', 'billing_locked_at'),
        ];

        Schema::table('invoices', function (Blueprint $table) use ($missing) {
            if ($missing['billing_type']) {
                $table->string('billing_type', 32)
                    ->default('full_payment')
                    ->after('quote_id');
            }

            if ($missing['billing_method']) {
                $table->string('billing_method', 24)
                    ->nullable()
                    ->after('billing_type');
            }

            if ($missing['billing_percentage']) {
                $table->decimal('billing_percentage', 8, 4)
                    ->nullable()
                    ->after('billing_method');
            }

            if ($missing['quote_total_snapshot']) {
                $table->decimal('quote_total_snapshot', 15, 2)
                    ->nullable()
                    ->after('billing_percentage');
            }

            if ($missing['billing_amount']) {
                $table->decimal('billing_amount', 15, 2)
                    ->nullable()
                    ->after('quote_total_snapshot');
            }

            if ($missing['remaining_amount_snapshot']) {
                $table->decimal('remaining_amount_snapshot', 15, 2)
                    ->nullable()
                    ->after('billing_amount');
            }

            if ($missing['dp_invoice_id']) {
                $table->unsignedBigInteger('dp_invoice_id')
                    ->nullable()
                    ->after('remaining_amount_snapshot');
            }

            if ($missing['billing_created_by']) {
                $table->unsignedBigInteger('billing_created_by')
                    ->nullable()
                    ->after('dp_invoice_id');
            }

            if ($missing['billing_locked_at']) {
                $table->timestamp('billing_locked_at')
                    ->nullable()
                    ->after('billing_created_by');
            }
        });

        $indexNames = collect(Schema::getIndexes('invoices'))
            ->pluck('name');

        Schema::table('invoices', function (Blueprint $table) use ($indexNames) {
            if (! $indexNames->contains('crm_invoices_quote_billing_idx')) {
                $table->index(
                    ['quote_id', 'billing_type', 'event_status'],
                    'crm_invoices_quote_billing_idx'
                );
            }

            if (! $indexNames->contains('crm_invoices_dp_reference_idx')) {
                $table->index(
                    ['dp_invoice_id'],
                    'crm_invoices_dp_reference_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('invoices'))
            ->pluck('name');

        Schema::table('invoices', function (Blueprint $table) use ($indexNames) {
            if ($indexNames->contains('crm_invoices_quote_billing_idx')) {
                $table->dropIndex('crm_invoices_quote_billing_idx');
            }

            if ($indexNames->contains('crm_invoices_dp_reference_idx')) {
                $table->dropIndex('crm_invoices_dp_reference_idx');
            }
        });

        $columns = collect([
            'billing_type',
            'billing_method',
            'billing_percentage',
            'quote_total_snapshot',
            'billing_amount',
            'remaining_amount_snapshot',
            'dp_invoice_id',
            'billing_created_by',
            'billing_locked_at',
        ])->filter(
            fn ($column) => Schema::hasColumn('invoices', $column)
        )->all();

        if ($columns !== []) {
            Schema::table('invoices', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        /*
         * Restore the legacy unique constraint only when no Quote currently
         * has more than one Invoice. Never destroy billing history on rollback.
         */
        $hasDuplicateQuote = DB::table('invoices')
            ->whereNotNull('quote_id')
            ->select('quote_id')
            ->groupBy('quote_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicateQuote) {
            $hasQuoteUnique = collect(Schema::getIndexes('invoices'))
                ->contains(function ($index) {
                    return ($index['unique'] ?? false)
                        && array_values($index['columns'] ?? []) === ['quote_id'];
                });

            if (! $hasQuoteUnique) {
                Schema::table('invoices', function (Blueprint $table) {
                    $table->unique('quote_id');
                });
            }

            $indexNames = collect(Schema::getIndexes('invoices'))
                ->pluck('name');

            if ($indexNames->contains('crm_invoices_quote_idx')) {
                Schema::table('invoices', function (Blueprint $table) {
                    $table->dropIndex('crm_invoices_quote_idx');
                });
            }
        }
    }
};
