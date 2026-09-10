<?php

declare(strict_types=1);

namespace CrmPerformanceTest;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class CrmPerformanceDataV1
{
    private const VERSION = 'CRM_PERFORMANCE_TEST_DATA_V1_1';

    private const PROFILES = [
        'smoke' => [
            'quotes' => 100,
            'invoices' => 100,
            'payments' => 200,
            'assets' => 100,
            'movements' => 1000,
            'messages' => 1000,
        ],
        'medium' => [
            'quotes' => 1000,
            'invoices' => 1000,
            'payments' => 2000,
            'assets' => 1000,
            'movements' => 10000,
            'messages' => 10000,
        ],
        'full' => [
            'quotes' => 10000,
            'invoices' => 10000,
            'payments' => 20000,
            'assets' => 10000,
            'movements' => 100000,
            'messages' => 100000,
        ],
    ];

    private static array $columns = [];

    public static function seed(string $root, array $argv): int
    {
        $options = self::options($argv);

        if (isset($options['help'])) {
            self::seedHelp();

            return 0;
        }

        $database = self::requiredOption($options, 'database');
        $profile = strtolower((string) ($options['profile'] ?? 'smoke'));
        $chunk = max(100, min(2000, (int) ($options['chunk'] ?? 500)));

        if (! isset(self::PROFILES[$profile])) {
            throw new RuntimeException('Profile harus smoke, medium, atau full.');
        }

        if (($options['confirm'] ?? '') !== 'SEED') {
            throw new RuntimeException('Seeder memerlukan --confirm=SEED.');
        }

        self::bootTarget($root, $database, false);
        self::assertTables();
        self::ensureTrackingTable();

        $plan = self::PROFILES[$profile];
        $runId = date('YmdHis').'-'.bin2hex(random_bytes(3));

        do {
            // Keep generated business identifiers below common VARCHAR(30)
            // limits while retaining a unique, cleanup-safe run prefix.
            $prefix = 'PERF'.date('His').strtoupper(bin2hex(random_bytes(2)));
        } while (DB::table('crm_performance_test_runs')->where('prefix', $prefix)->exists());
        $trackingId = DB::table('crm_performance_test_runs')->insertGetId([
            'run_id' => $runId,
            'prefix' => $prefix,
            'profile' => $profile,
            'status' => 'running',
            'planned_counts' => json_encode($plan, JSON_THROW_ON_ERROR),
            'actual_counts' => null,
            'error_message' => null,
            'started_at' => self::now(),
            'finished_at' => null,
            'cleaned_at' => null,
            'created_at' => self::now(),
            'updated_at' => self::now(),
        ]);

        echo "\nCRM PERFORMANCE TEST DATA V1.1\n";
        echo "==============================\n";
        echo "Database : {$database}\n";
        echo "Profile  : {$profile}\n";
        echo "Run ID   : {$runId}\n";
        echo "Prefix   : {$prefix}\n\n";

        DB::connection()->disableQueryLog();

        try {
            $actual = self::generate($plan, $prefix, (int) $trackingId, $chunk);

            DB::table('crm_performance_test_runs')
                ->where('id', $trackingId)
                ->update([
                    'status' => 'completed',
                    'actual_counts' => json_encode($actual, JSON_THROW_ON_ERROR),
                    'finished_at' => self::now(),
                    'updated_at' => self::now(),
                ]);

            echo "\n[OK] Data performance selesai dibuat.\n";
            echo "Verifikasi:\n";
            echo "php tools/crm_performance_check_v1.php --database={$database} --run={$runId}\n";
            echo "Bersihkan:\n";
            echo "php tools/crm_performance_cleanup_v1.php --database={$database} --run={$runId} --confirm=CLEAN\n";

            return 0;
        } catch (Throwable $exception) {
            $error = self::conciseError($exception);

            DB::table('crm_performance_test_runs')
                ->where('id', $trackingId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $error,
                    'finished_at' => self::now(),
                    'updated_at' => self::now(),
                ]);

            fwrite(STDERR, "\n[GAGAL] {$error}\n");
            fwrite(STDERR, "Data parsial aman dibersihkan memakai Run ID {$runId}.\n");

            return 1;
        }
    }

    public static function check(string $root, array $argv): int
    {
        $options = self::options($argv);
        $database = self::requiredOption($options, 'database');
        $runId = self::requiredOption($options, 'run');

        self::bootTarget($root, $database, true);

        if (! Schema::hasTable('crm_performance_test_runs')) {
            throw new RuntimeException('Tracking table belum ada. Jalankan seeder terlebih dahulu.');
        }

        $run = DB::table('crm_performance_test_runs')->where('run_id', $runId)->first();

        if (! $run) {
            throw new RuntimeException("Run ID tidak ditemukan: {$runId}");
        }

        $prefix = (string) $run->prefix;
        $planned = json_decode((string) $run->planned_counts, true, 512, JSON_THROW_ON_ERROR);
        $actual = self::counts($prefix);
        $failed = false;

        echo "\nCHECK CRM PERFORMANCE TEST DATA V1.1\n";
        echo "====================================\n";
        echo "Database : {$database}\n";
        echo "Run ID   : {$runId}\n";
        echo "Status   : {$run->status}\n\n";

        foreach ($planned as $key => $expected) {
            $found = (int) ($actual[$key] ?? 0);
            $ok = $run->status === 'cleaned' ? $found === 0 : $found === (int) $expected;
            $failed = $failed || ! $ok;
            printf("[%s] %-10s planned=%d found=%d\n", $ok ? 'OK' : 'FAIL', $key, $expected, $found);
        }

        echo "\nTambahan relasi:\n";
        printf("- quote_items          : %d\n", $actual['quote_items']);
        printf("- invoice_items        : %d\n", $actual['invoice_items']);
        printf("- chat_conversations   : %d\n", $actual['chat_conversations']);
        printf("- chat_members         : %d\n", $actual['chat_members']);

        if ($failed) {
            echo "\n[FAIL] Jumlah data belum sesuai atau data yang dibersihkan masih tersisa.\n";

            return 1;
        }

        echo "\n[OK] Seluruh jumlah utama sesuai.\n";

        return 0;
    }

    public static function cleanup(string $root, array $argv): int
    {
        $options = self::options($argv);
        $database = self::requiredOption($options, 'database');
        $runId = self::requiredOption($options, 'run');

        if (($options['confirm'] ?? '') !== 'CLEAN') {
            throw new RuntimeException('Cleanup memerlukan --confirm=CLEAN.');
        }

        self::bootTarget($root, $database, true);

        if (! Schema::hasTable('crm_performance_test_runs')) {
            throw new RuntimeException('Tracking table tidak ditemukan.');
        }

        $run = DB::table('crm_performance_test_runs')->where('run_id', $runId)->first();

        if (! $run) {
            throw new RuntimeException("Run ID tidak ditemukan: {$runId}");
        }

        $prefix = (string) $run->prefix;

        echo "\nCLEAN CRM PERFORMANCE TEST DATA V1.1\n";
        echo "====================================\n";
        echo "Database : {$database}\n";
        echo "Run ID   : {$runId}\n\n";

        $conversationIds = DB::table('internal_conversations')
            ->where('direct_key', 'like', $prefix.'-CHAT-%')
            ->pluck('id');

        $invoiceIds = DB::table('invoices')
            ->where('invoice_number', 'like', $prefix.'-I-%')
            ->pluck('id');

        $quoteIds = DB::table('quotes')
            ->where('quote_number', 'like', $prefix.'-Q-%')
            ->pluck('id');

        DB::transaction(function () use ($prefix, $conversationIds, $invoiceIds, $quoteIds): void {
            self::deleteWhereIn('internal_messages', 'conversation_id', $conversationIds->all());
            self::deleteWhereIn('internal_conversation_members', 'conversation_id', $conversationIds->all());
            self::deleteWhereIn('internal_conversations', 'id', $conversationIds->all());

            DB::table('inventory_stock_movements')
                ->where('reference_number', 'like', $prefix.'-MOV-%')
                ->delete();
            DB::table('inventory_assets')
                ->where('asset_code', 'like', $prefix.'-A-%')
                ->delete();

            self::deleteWhereIn('payments', 'invoice_id', $invoiceIds->all());
            self::deleteWhereIn('invoice_items', 'invoice_id', $invoiceIds->all());

            if (self::hasColumn('invoices', 'dp_invoice_id')) {
                self::updateWhereIn('invoices', 'id', $invoiceIds->all(), ['dp_invoice_id' => null]);
            }

            self::deleteWhereIn('invoices', 'id', $invoiceIds->all());
            self::deleteWhereIn('quote_items', 'quote_id', $quoteIds->all());
            self::deleteWhereIn('quotes', 'id', $quoteIds->all());
        });

        DB::table('crm_performance_test_runs')
            ->where('id', $run->id)
            ->update([
                'status' => 'cleaned',
                'cleaned_at' => self::now(),
                'updated_at' => self::now(),
            ]);

        $remaining = array_sum(array_intersect_key(self::counts($prefix), self::PROFILES['full']));

        if ($remaining !== 0) {
            fwrite(STDERR, "[FAIL] Masih ada {$remaining} record utama tersisa.\n");

            return 1;
        }

        echo "[OK] Seluruh data untuk Run ID {$runId} telah dibersihkan.\n";

        return 0;
    }

    private static function generate(array $plan, string $prefix, int $trackingId, int $chunk): array
    {
        $quoteSample = self::sample('quotes', 'quote_number');
        $quoteItemSample = self::sample('quote_items');
        $invoiceSample = self::sample('invoices', 'invoice_number');
        $invoiceItemSample = self::sample('invoice_items');
        $assetSample = self::sample('inventory_assets', 'asset_code');
        $paymentSample = self::sample('payments', null, false) ?? [];
        $movementSample = self::sample('inventory_stock_movements', null, false) ?? [];
        $messageSample = self::sample('internal_messages', null, false) ?? [];
        $memberSample = self::sample('internal_conversation_members', null, false) ?? [];

        $users = DB::table('users')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($users) < 2) {
            throw new RuntimeException('Minimal dua user diperlukan untuk data chat. Clone database development terlebih dahulu.');
        }

        $baseQuoteTotal = max(1000000.0, (float) ($quoteSample['grand_total'] ?? 5000000));

        self::insertGenerated('quotes', $plan['quotes'], $chunk, function (int $i) use ($quoteSample, $prefix, $baseQuoteTotal): array {
            $total = round($baseQuoteTotal + (($i % 20) * 250000), 2);
            $created = self::dateFor($i);

            return self::row('quotes', $quoteSample, [
                'quote_number' => sprintf('%s-Q-%06d', $prefix, $i),
                'project_code' => sprintf('%s-P-%06d', $prefix, $i),
                'subject' => "Performance Test Deal {$i}",
                'description' => "[{$prefix}] synthetic quote for load testing",
                'sub_total' => $total,
                'grand_total' => $total,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'adjustment_amount' => 0,
                'event_date' => substr($created, 0, 10),
                'expired_at' => date('Y-m-d H:i:s', strtotime($created.' +30 days')),
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
        echo "[OK] quotes           : {$plan['quotes']}\n";

        $quotes = DB::table('quotes')
            ->where('quote_number', 'like', $prefix.'-Q-%')
            ->orderBy('quote_number')
            ->get(['id', 'quote_number', 'project_code', 'grand_total'])
            ->values();

        self::insertGenerated('quote_items', $quotes->count(), $chunk, function (int $i) use ($quoteItemSample, $quotes, $prefix): array {
            $quote = $quotes[$i - 1];
            $total = (float) $quote->grand_total;

            return self::row('quote_items', $quoteItemSample, [
                'quote_id' => $quote->id,
                'sku' => sprintf('PERF-SKU-%04d', (($i - 1) % 50) + 1),
                'name' => 'Performance Product '.((($i - 1) % 50) + 1),
                'description' => "[{$prefix}] synthetic quote item",
                'day' => 1,
                'quantity' => 1,
                'price' => $total,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $total,
                'created_at' => self::now(),
                'updated_at' => self::now(),
            ]);
        });

        $invoicePlan = self::invoicePlan($quotes, $plan['invoices']);

        self::insertGenerated('invoices', count($invoicePlan), $chunk, function (int $i) use ($invoiceSample, $invoicePlan, $prefix, $users): array {
            $entry = $invoicePlan[$i - 1];
            $created = self::dateFor($i + 37);

            return self::row('invoices', $invoiceSample, [
                'invoice_number' => sprintf('%s-I-%06d', $prefix, $i),
                'project_code' => $entry['quote']->project_code,
                'quote_id' => $entry['quote']->id,
                'subject' => "Performance Invoice {$i}",
                'description' => "[{$prefix}] synthetic invoice for load testing",
                'sub_total' => $entry['amount'],
                'grand_total' => $entry['amount'],
                'paid_amount' => $entry['paid_amount'],
                'balance_due' => round($entry['amount'] - $entry['paid_amount'], 2),
                'status' => $entry['status'],
                'event_status' => 'confirm',
                'billing_type' => $entry['billing_type'],
                'billing_method' => $entry['billing_type'] === 'down_payment' ? 'percentage' : null,
                'billing_percentage' => $entry['billing_type'] === 'down_payment' ? 40 : null,
                'quote_total_snapshot' => $entry['quote_total'],
                'billing_amount' => $entry['amount'],
                'remaining_amount_snapshot' => $entry['remaining'],
                'dp_invoice_id' => null,
                'billing_created_by' => $users[0],
                'billing_locked_at' => $created,
                'issued_at' => $created,
                'due_at' => date('Y-m-d H:i:s', strtotime($created.' +14 days')),
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
        echo "[OK] invoices         : {$plan['invoices']}\n";

        $invoices = DB::table('invoices')
            ->where('invoice_number', 'like', $prefix.'-I-%')
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'quote_id', 'grand_total', 'paid_amount', 'billing_type'])
            ->values();

        $dpByQuote = $invoices
            ->where('billing_type', 'down_payment')
            ->keyBy('quote_id');

        foreach ($invoices->where('billing_type', 'settlement')->chunk(500) as $settlements) {
            foreach ($settlements as $settlement) {
                $dp = $dpByQuote->get($settlement->quote_id);

                if ($dp) {
                    DB::table('invoices')->where('id', $settlement->id)->update(['dp_invoice_id' => $dp->id]);
                }
            }
        }

        self::insertGenerated('invoice_items', $invoices->count(), $chunk, function (int $i) use ($invoiceItemSample, $invoices, $prefix): array {
            $invoice = $invoices[$i - 1];
            $amount = (float) $invoice->grand_total;

            return self::row('invoice_items', $invoiceItemSample, [
                'invoice_id' => $invoice->id,
                'sku' => strtoupper((string) $invoice->billing_type),
                'name' => match ($invoice->billing_type) {
                    'down_payment' => 'Down Payment',
                    'settlement' => 'Pelunasan',
                    default => 'Full Payment',
                },
                'description' => "[{$prefix}] synthetic invoice item",
                'day' => 1,
                'quantity' => 1,
                'price' => $amount,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $amount,
                'created_at' => self::now(),
                'updated_at' => self::now(),
            ]);
        });

        self::generatePayments($paymentSample, $invoices, $plan['payments'], $prefix, $users[0], $chunk);
        echo "[OK] payments         : {$plan['payments']}\n";

        self::insertGenerated('inventory_assets', $plan['assets'], $chunk, function (int $i) use ($assetSample, $prefix): array {
            $created = self::dateFor($i + 73);
            $statuses = ['available', 'available', 'allocated', 'out', 'maintenance', 'missing'];

            return self::row('inventory_assets', $assetSample, [
                'asset_code' => sprintf('%s-A-%06d', $prefix, $i),
                'barcode_value' => sprintf('%s-B-%06d', $prefix, $i),
                'serial_number' => sprintf('PERF-SN-%s-%06d', substr(md5($prefix), 0, 6), $i),
                'status' => $statuses[($i - 1) % count($statuses)],
                'condition' => $i % 10 === 0 ? 'damaged' : 'good',
                'notes' => "[{$prefix}] synthetic asset",
                'purchase_date' => substr($created, 0, 10),
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
        echo "[OK] assets           : {$plan['assets']}\n";

        $assets = DB::table('inventory_assets')
            ->where('asset_code', 'like', $prefix.'-A-%')
            ->orderBy('asset_code')
            ->get(['id', 'inventory_item_id', 'warehouse_id', 'warehouse_location_id'])
            ->values();

        self::insertGenerated('inventory_stock_movements', $plan['movements'], $chunk, function (int $i) use ($movementSample, $assets, $prefix, $trackingId, $users): array {
            $asset = $assets[($i - 1) % $assets->count()];
            $types = ['receive', 'allocate', 'checkout', 'return', 'maintenance', 'missing_recovery'];
            $created = self::dateFor($i + 109);

            return self::row('inventory_stock_movements', $movementSample, [
                'inventory_item_id' => $asset->inventory_item_id,
                'inventory_asset_id' => $asset->id,
                'warehouse_id' => $asset->warehouse_id,
                'warehouse_location_id' => $asset->warehouse_location_id,
                'movement_type' => $types[($i - 1) % count($types)],
                'quantity' => 1,
                'from_status' => 'available',
                'to_status' => 'available',
                'reference_type' => 'crm_performance_test',
                'reference_id' => $trackingId,
                'reference_number' => sprintf('%s-MOV-%06d', $prefix, $i),
                'performed_by' => $users[($i - 1) % count($users)],
                'notes' => "[{$prefix}] synthetic inventory movement",
                'occurred_at' => $created,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
        echo "[OK] movements        : {$plan['movements']}\n";

        $conversationCount = max(1, min(100, (int) ceil($plan['messages'] / 1000)));
        self::insertGenerated('internal_conversations', $conversationCount, $chunk, function (int $i) use ($prefix, $users): array {
            return self::row('internal_conversations', [], [
                'type' => 'direct',
                'direct_key' => sprintf('%s-CHAT-%03d', $prefix, $i),
                'created_by' => $users[0],
                'created_at' => self::now(),
                'updated_at' => self::now(),
            ]);
        });

        $conversations = DB::table('internal_conversations')
            ->where('direct_key', 'like', $prefix.'-CHAT-%')
            ->orderBy('direct_key')
            ->get(['id'])
            ->values();

        self::insertGenerated('internal_conversation_members', $conversationCount * 2, $chunk, function (int $i) use ($memberSample, $conversations, $users): array {
            $conversationIndex = intdiv($i - 1, 2);
            $userIndex = ($i - 1) % 2;

            return self::row('internal_conversation_members', $memberSample, [
                'conversation_id' => $conversations[$conversationIndex]->id,
                'user_id' => $users[$userIndex],
                'joined_at' => self::now(),
                'last_read_at' => $userIndex === 0 ? self::now() : null,
                'last_read_message_id' => null,
                'created_at' => self::now(),
                'updated_at' => self::now(),
            ]);
        });

        self::insertGenerated('internal_messages', $plan['messages'], $chunk, function (int $i) use ($messageSample, $conversations, $users, $prefix): array {
            $created = self::dateFor($i + 151);

            return self::row('internal_messages', $messageSample, [
                'conversation_id' => $conversations[($i - 1) % $conversations->count()]->id,
                'user_id' => $users[($i - 1) % 2],
                'body' => sprintf('[%s] Performance test message %09d', $prefix, $i),
                'reply_to_message_id' => null,
                'edited_at' => null,
                'deleted_at' => null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
        echo "[OK] chat messages    : {$plan['messages']}\n";

        return self::counts($prefix);
    }

    private static function invoicePlan($quotes, int $invoiceCount): array
    {
        $fullCount = (int) floor($invoiceCount * 0.70);
        $dpOnlyCount = (int) floor($invoiceCount * 0.10);
        $pairDeals = intdiv($invoiceCount - $fullCount - $dpOnlyCount, 2);
        $plan = [];
        $quoteIndex = 0;

        for ($i = 0; $i < $fullCount; $i++, $quoteIndex++) {
            $quote = $quotes[$quoteIndex];
            $amount = (float) $quote->grand_total;
            $status = self::statusFor($i);
            $paid = self::paidFor($amount, $status);
            $plan[] = compact('quote', 'amount', 'status') + [
                'paid_amount' => $paid,
                'billing_type' => 'full_payment',
                'quote_total' => $amount,
                'remaining' => 0,
            ];
        }

        for ($i = 0; $i < $dpOnlyCount; $i++, $quoteIndex++) {
            $quote = $quotes[$quoteIndex];
            $total = (float) $quote->grand_total;
            $amount = round($total * 0.40, 2);
            $status = self::statusFor($i + 2);
            $paid = self::paidFor($amount, $status);
            $plan[] = compact('quote', 'amount', 'status') + [
                'paid_amount' => $paid,
                'billing_type' => 'down_payment',
                'quote_total' => $total,
                'remaining' => round($total - $amount, 2),
            ];
        }

        for ($i = 0; $i < $pairDeals; $i++, $quoteIndex++) {
            $quote = $quotes[$quoteIndex];
            $total = (float) $quote->grand_total;
            $dpAmount = round($total * 0.40, 2);
            $settlementAmount = round($total - $dpAmount, 2);
            $plan[] = [
                'quote' => $quote,
                'amount' => $dpAmount,
                'status' => 'paid',
                'paid_amount' => $dpAmount,
                'billing_type' => 'down_payment',
                'quote_total' => $total,
                'remaining' => $settlementAmount,
            ];
            $status = self::statusFor($i + 4);
            $plan[] = [
                'quote' => $quote,
                'amount' => $settlementAmount,
                'status' => $status,
                'paid_amount' => self::paidFor($settlementAmount, $status),
                'billing_type' => 'settlement',
                'quote_total' => $total,
                'remaining' => 0,
            ];
        }

        while (count($plan) < $invoiceCount) {
            $quote = $quotes[min($quoteIndex, $quotes->count() - 1)];
            $amount = (float) $quote->grand_total;
            $plan[] = [
                'quote' => $quote,
                'amount' => $amount,
                'status' => 'unpaid',
                'paid_amount' => 0,
                'billing_type' => 'full_payment',
                'quote_total' => $amount,
                'remaining' => 0,
            ];
            $quoteIndex++;
        }

        return $plan;
    }

    private static function generatePayments(array $sample, $invoices, int $count, string $prefix, int $userId, int $chunk): void
    {
        $eligible = $invoices->filter(fn ($invoice) => (float) $invoice->paid_amount > 0)->values();

        if ($eligible->isEmpty()) {
            throw new RuntimeException('Tidak ada invoice dengan paid_amount untuk membuat payment.');
        }

        $assignments = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (int) $eligible[$i % $eligible->count()]->id;
            $assignments[$id] = ($assignments[$id] ?? 0) + 1;
        }

        $seen = [];
        $allocated = [];

        self::insertGenerated('payments', $count, $chunk, function (int $i) use ($sample, $eligible, $prefix, $userId, $assignments, &$seen, &$allocated): array {
            $invoice = $eligible[($i - 1) % $eligible->count()];
            $id = (int) $invoice->id;
            $seen[$id] = ($seen[$id] ?? 0) + 1;
            $allocated[$id] = $allocated[$id] ?? 0.0;
            $target = (float) $invoice->paid_amount;
            $isLast = $seen[$id] === $assignments[$id];
            $amount = $isLast
                ? round($target - $allocated[$id], 2)
                : round($target / $assignments[$id], 2);
            $allocated[$id] = round($allocated[$id] + $amount, 2);
            $created = self::dateFor($i + 193);

            return self::row('payments', $sample, [
                'invoice_id' => $id,
                'amount' => max(0.01, $amount),
                'payment_method' => ['bank_transfer', 'cash', 'qris'][($i - 1) % 3],
                'reference_number' => sprintf('%s-PAY-%06d', $prefix, $i),
                'notes' => "[{$prefix}] synthetic payment",
                'paid_at' => $created,
                'created_by' => $userId,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        });
    }

    private static function statusFor(int $i): string
    {
        return match ($i % 10) {
            0, 1, 2, 3, 4 => 'paid',
            5, 6, 7 => 'partial',
            default => 'unpaid',
        };
    }

    private static function paidFor(float $amount, string $status): float
    {
        return match ($status) {
            'paid' => round($amount, 2),
            'partial' => round($amount * 0.45, 2),
            default => 0.0,
        };
    }

    private static function counts(string $prefix): array
    {
        $invoiceIds = DB::table('invoices')->where('invoice_number', 'like', $prefix.'-I-%')->pluck('id');
        $quoteIds = DB::table('quotes')->where('quote_number', 'like', $prefix.'-Q-%')->pluck('id');
        $conversationIds = DB::table('internal_conversations')->where('direct_key', 'like', $prefix.'-CHAT-%')->pluck('id');

        return [
            'quotes' => $quoteIds->count(),
            'invoices' => $invoiceIds->count(),
            'payments' => self::countWhereIn('payments', 'invoice_id', $invoiceIds->all()),
            'assets' => DB::table('inventory_assets')->where('asset_code', 'like', $prefix.'-A-%')->count(),
            'movements' => DB::table('inventory_stock_movements')->where('reference_number', 'like', $prefix.'-MOV-%')->count(),
            'messages' => self::countWhereIn('internal_messages', 'conversation_id', $conversationIds->all()),
            'quote_items' => self::countWhereIn('quote_items', 'quote_id', $quoteIds->all()),
            'invoice_items' => self::countWhereIn('invoice_items', 'invoice_id', $invoiceIds->all()),
            'chat_conversations' => $conversationIds->count(),
            'chat_members' => self::countWhereIn('internal_conversation_members', 'conversation_id', $conversationIds->all()),
        ];
    }

    private static function insertGenerated(string $table, int $count, int $chunk, callable $factory): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $factory($i);

            if (count($rows) >= $chunk) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table($table)->insert($rows);
        }
    }

    private static function row(string $table, array $sample, array $overrides): array
    {
        $available = array_flip(self::columns($table));
        $row = [];

        foreach ($sample as $column => $value) {
            if ($column !== 'id' && isset($available[$column])) {
                $row[$column] = $value;
            }
        }

        foreach ($overrides as $column => $value) {
            if ($column !== 'id' && isset($available[$column])) {
                $row[$column] = $value;
            }
        }

        unset($row['id']);

        return $row;
    }

    private static function sample(string $table, ?string $prefixColumn = null, bool $required = true): ?array
    {
        $query = DB::table($table);

        if ($prefixColumn && self::hasColumn($table, $prefixColumn)) {
            $query->where($prefixColumn, 'not like', 'PERF-%');
        }

        $row = $query->orderBy('id')->first();

        if (! $row && $required) {
            throw new RuntimeException("Tabel {$table} memerlukan minimal satu template. Clone database development ke database performance.");
        }

        return $row ? (array) $row : null;
    }

    private static function columns(string $table): array
    {
        return self::$columns[$table] ??= Schema::getColumnListing($table);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return in_array($column, self::columns($table), true);
    }

    private static function ensureTrackingTable(): void
    {
        if (Schema::hasTable('crm_performance_test_runs')) {
            return;
        }

        Schema::create('crm_performance_test_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_id', 64)->unique();
            $table->string('prefix', 80)->index();
            $table->string('profile', 20);
            $table->string('status', 20)->index();
            $table->json('planned_counts');
            $table->json('actual_counts')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cleaned_at')->nullable();
            $table->timestamps();
        });
    }

    private static function assertTables(): void
    {
        $required = [
            'users', 'quotes', 'quote_items', 'invoices', 'invoice_items', 'payments',
            'inventory_assets', 'inventory_stock_movements', 'internal_conversations',
            'internal_conversation_members', 'internal_messages',
        ];

        $missing = array_values(array_filter($required, fn ($table) => ! Schema::hasTable($table)));

        if ($missing !== []) {
            throw new RuntimeException('Tabel belum lengkap: '.implode(', ', $missing).'. Import salinan database development terlebih dahulu.');
        }

        foreach ([
            ['quotes', 'quote_number'],
            ['invoices', 'invoice_number'],
            ['inventory_assets', 'asset_code'],
            ['inventory_stock_movements', 'reference_number'],
            ['internal_conversations', 'direct_key'],
        ] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                throw new RuntimeException("Kolom wajib tidak tersedia: {$table}.{$column}");
            }
        }
    }

    private static function bootTarget(string $root, string $target, bool $allowAlreadyActive): void
    {
        self::assertSafeDatabaseName($target);

        if (! is_file($root.'/artisan') || ! is_file($root.'/bootstrap/app.php')) {
            throw new RuntimeException('Jalankan script dari struktur root project Laravel yang benar.');
        }

        require_once $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $connection = (string) Config::get('database.default');
        $driver = (string) Config::get("database.connections.{$connection}.driver");
        $source = (string) Config::get("database.connections.{$connection}.database");

        if ($driver !== 'mysql') {
            throw new RuntimeException('Toolkit V1 mendukung koneksi MySQL/MariaDB.');
        }

        if (! $allowAlreadyActive && strcasecmp($source, $target) === 0) {
            throw new RuntimeException('Database target sama dengan database .env aktif. Operasi ditolak.');
        }

        $exists = DB::connection($connection)->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$target]
        );

        if (! $exists) {
            throw new RuntimeException("Database {$target} belum ada. Buat dan import salinan development terlebih dahulu.");
        }

        Config::set("database.connections.{$connection}.database", $target);
        DB::purge($connection);
        DB::reconnect($connection);

        $connected = (string) DB::connection($connection)->getDatabaseName();

        if (strcasecmp($connected, $target) !== 0) {
            throw new RuntimeException("Koneksi safety check gagal; tersambung ke {$connected}, bukan {$target}.");
        }
    }

    private static function assertSafeDatabaseName(string $database): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $database)) {
            throw new RuntimeException('Nama database hanya boleh berisi huruf, angka, underscore, atau dash.');
        }

        if (! preg_match('/(?:performance|perf|testing|test|staging)/i', $database)) {
            throw new RuntimeException('Nama database wajib mengandung performance, perf, testing, test, atau staging.');
        }
    }

    private static function options(array $argv): array
    {
        $options = [];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--help') {
                $options['help'] = true;
                continue;
            }

            if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $matches)) {
                $options[$matches[1]] = $matches[2];
            }
        }

        return $options;
    }

    private static function requiredOption(array $options, string $name): string
    {
        $value = trim((string) ($options[$name] ?? ''));

        if ($value === '') {
            throw new RuntimeException("Option --{$name}=... wajib diisi.");
        }

        return $value;
    }

    private static function countWhereIn(string $table, string $column, array $ids): int
    {
        $total = 0;

        foreach (array_chunk($ids, 1000) as $chunk) {
            $total += DB::table($table)->whereIn($column, $chunk)->count();
        }

        return $total;
    }

    private static function deleteWhereIn(string $table, string $column, array $ids): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->delete();
        }
    }

    private static function updateWhereIn(string $table, string $column, array $ids, array $values): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->update($values);
        }
    }

    private static function dateFor(int $sequence): string
    {
        $seconds = ($sequence % 540) * 86400 + ($sequence % 86400);

        return date('Y-m-d H:i:s', time() - $seconds);
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function conciseError(Throwable $exception): string
    {
        $message = preg_replace('/\s+\(Connection:.*$/s', '', $exception->getMessage())
            ?? $exception->getMessage();

        return mb_substr($message, 0, 2000);
    }

    private static function seedHelp(): void
    {
        echo "CRM Performance Test Data V1.1\n\n";
        echo "Seed:\n";
        echo "  php tools/crm_performance_seed_v1.php --database=crm_performance --profile=smoke --confirm=SEED\n\n";
        echo "Profiles: smoke, medium, full\n";
    }
}
