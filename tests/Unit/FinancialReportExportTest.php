<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;

uses(TestCase::class);

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.financial_export_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::setDefaultConnection('financial_export_test');
    Schema::clearResolvedInstance('db.schema');
    (require base_path('database/migrations/2026_10_13_150000_create_payment_voids_table.php'))->up();

    Schema::create('invoices', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('person_id')->nullable();
        $table->integer('user_id')->nullable();
        $table->date('event_date')->nullable();
        $table->integer('quote_id')->nullable();
        foreach (['invoice_number', 'project_code', 'business_unit', 'subject', 'billing_type', 'event_status', 'status', 'bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name'] as $column) {
            $table->string($column)->default('');
        }
        $table->decimal('grand_total', 16, 4)->default(0);
        $table->decimal('quote_total_snapshot', 16, 4)->nullable();
        $table->decimal('pph_amount', 16, 4)->default(0);
        $table->dateTime('issued_at')->nullable();
    });
    Schema::create('quotes', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('person_id')->nullable();
        $table->integer('user_id')->nullable();
        $table->date('event_date')->nullable();
        $table->decimal('grand_total', 16, 4)->default(0);
        foreach (['bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name'] as $column) {
            $table->string($column)->default('');
        }
    });
    Schema::create('users', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });
    Schema::create('persons', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->integer('organization_id')->nullable();
        $table->json('contact_numbers')->nullable();
    });
    Schema::create('organizations', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });
    Schema::create('attributes', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('code');
    });
    Schema::create('invoice_items', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('invoice_id');
        $table->string('name');
    });
    foreach (['payments' => 'paid_at', 'expenses' => 'expense_date'] as $name => $date) {
        Schema::create($name, function (Blueprint $table) use ($date): void {
            $table->increments('id');
            $table->integer('invoice_id');
            $table->decimal('amount', 16, 4);
            $table->dateTime($date);
        });
    }

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Invoice Sales'],
        ['id' => 2, 'name' => 'Quote Sales'],
    ]);
    DB::table('organizations')->insert([
        ['id' => 1, 'name' => 'Current Customer Company'],
        ['id' => 2, 'name' => 'Quote Customer Company'],
    ]);
    DB::table('persons')->insert([
        ['id' => 1, 'name' => 'Customer', 'organization_id' => 1],
        ['id' => 2, 'name' => 'Quote Customer', 'organization_id' => 2],
    ]);
    DB::table('quotes')->insert(['id' => 1, 'person_id' => 2, 'grand_total' => 1000]);
    DB::table('invoices')->insert([
        'id' => 1, 'invoice_number' => 'INV-001', 'person_id' => 1, 'quote_id' => 1,
        'business_unit' => 'capture_it', 'issued_at' => '2026-10-03',
        'event_status' => 'confirm', 'status' => 'partial', 'grand_total' => 1000,
    ]);
    DB::table('invoice_items')->insert(['invoice_id' => 1, 'name' => 'Photobooth']);
    DB::table('payments')->insert(['invoice_id' => 1, 'amount' => 300, 'paid_at' => '2026-10-05']);
    DB::table('expenses')->insert(['invoice_id' => 1, 'amount' => 100, 'expense_date' => '2026-10-06']);
});

afterEach(function (): void {
    DB::purge('financial_export_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

function financialExportRows(array $filters = []): array
{
    $response = app(FinancialReportController::class)->export(Request::create('/', 'GET', array_merge([
        'year' => 2026, 'month' => 10, 'business_unit' => 'capture_it',
        'event_status' => 'confirm', 'product' => 'Photobooth',
    ], $filters)));
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
    $csv = TestResponse::fromBaseResponse($response)->streamedContent();
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $header = fgetcsv($stream, 0, ';', '"', '');
    expect($header)->toBe([
        'Project Code', 'Business Unit', 'No Invoice', 'Jenis Invoice', 'Tanggal Inv',
        'Nama Customer', 'Company', 'No. Telepon Pemesan', 'Nama Event', 'Tanggal Event', 'Produk',
        'Status Project', 'Status Pembayaran', 'Nilai Invoice', 'Nilai Pembayaran',
        'Nilai Pembayaran Periode', 'Sisa Hutang', 'Sales',
    ]);

    $rows = [];
    while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
        expect($row)->toHaveCount(count($header));
        $rows[] = array_combine($header, $row);
    }
    fclose($stream);

    return $rows;
}

it('exports corrected contact companies with snapshot fallbacks and unchanged financial values', function (array $invoice, array $quote, string $expected): void {
    DB::table('invoices')->where('id', 1)->update($invoice ?: ['subject' => 'Legacy invoice']);
    DB::table('quotes')->where('id', 1)->update($quote ?: ['bill_to_company_name' => '']);

    $rows = financialExportRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Company'])->toBe($expected)
        ->and($rows[0]['Nilai Invoice'])->toBe('1000')
        ->and($rows[0]['Nilai Pembayaran'])->toBe('300')
        ->and($rows[0]['Sisa Hutang'])->toBe('700')
        ->and($rows[0]['Nilai Pembayaran Periode'])->toBe('300');
})->with([
    'corrected invoice contact takes priority' => [['bill_to_company_name' => 'Invoice Company'], ['bill_to_company_name' => 'Quote Company'], 'Current Customer Company'],
    'invoice recipient remains independent of quote' => [[], ['bill_to_company_name' => 'Quote Company'], 'Current Customer Company'],
    'legacy quote recipient when invoice has no contact' => [['person_id' => null], [], 'Quote Customer Company'],
    'standalone invoice contact' => [['quote_id' => null], [], 'Current Customer Company'],
    'missing identity' => [['quote_id' => null, 'person_id' => null], [], '-'],
    'person-only print mode still identifies company in report' => [['bill_to_person_name' => 'Original Customer'], [], 'Current Customer Company'],
    'removed contact falls back to snapshot' => [['person_id' => null, 'bill_to_company_name' => 'Invoice Company'], [], 'Invoice Company'],
    'CSV punctuation and Unicode in fallback' => [['person_id' => null, 'bill_to_company_name' => "PT \"Café\"; Indonesia\nJakarta"], [], "PT \"Café\"; Indonesia\nJakarta"],
]);

it('keeps period business unit status and product filters in the financial export', function (): void {
    foreach ([
        ['issued_at' => '2026-09-30'],
        ['issued_at' => '2025-10-03'],
        ['business_unit' => 'varbel'],
        ['event_status' => 'cancel'],
        ['status' => 'void'],
        ['issued_at' => null],
        ['product' => 'Other product'],
    ] as $index => $override) {
        $id = $index + 2;
        $product = $override['product'] ?? 'Photobooth';
        unset($override['product']);
        DB::table('invoices')->insert(array_merge([
            'id' => $id, 'invoice_number' => 'EXCLUDED-'.$id, 'business_unit' => 'capture_it',
            'issued_at' => '2026-10-03', 'event_status' => 'confirm', 'grand_total' => 2000,
        ], $override));
        DB::table('invoice_items')->insert(['invoice_id' => $id, 'name' => $product]);
    }

    $rows = financialExportRows();
    expect(array_column($rows, 'No Invoice'))->toBe(['INV-001']);
    expect(financialExportRows(['month' => 12]))->toBe([]);
});

it('exports only the requested invoice columns with event date and sales', function (): void {
    DB::table('invoices')->where('id', 1)->update([
        'project_code' => 'PRJ-2026-00001', 'subject' => 'Wedding Event',
        'event_date' => '2026-11-12', 'user_id' => 1, 'billing_type' => 'down_payment',
    ]);
    DB::table('quotes')->where('id', 1)->update(['event_date' => '2026-11-10', 'user_id' => 2]);
    DB::table('invoice_items')->insert([
        ['invoice_id' => 1, 'name' => 'Photobooth'],
        ['invoice_id' => 1, 'name' => 'Print'],
    ]);

    expect(financialExportRows())->toBe([[
        'Project Code' => 'PRJ-2026-00001',
        'Business Unit' => 'Capture It - Photobooth',
        'No Invoice' => 'INV-001',
        'Jenis Invoice' => 'DOWN PAYMENT',
        'Tanggal Inv' => '3 Oct 2026',
        'Nama Customer' => 'Customer',
        'Company' => 'Current Customer Company',
        'No. Telepon Pemesan' => '',
        'Nama Event' => 'Wedding Event',
        'Tanggal Event' => '12 Nov 2026',
        'Produk' => 'Photobooth; Print',
        'Status Project' => 'CONFIRM',
        'Status Pembayaran' => 'PARTIAL',
        'Nilai Invoice' => '1000',
        'Nilai Pembayaran' => '300',
        'Nilai Pembayaran Periode' => '300',
        'Sisa Hutang' => '700',
        'Sales' => 'Invoice Sales',
    ]]);
});

it('removes cancelled invoice values from exports even when all event statuses are selected', function (string $status): void {
    DB::table('invoices')->insert([
        'id' => 2, 'invoice_number' => 'CANCELLED-002', 'business_unit' => 'capture_it',
        'issued_at' => '2026-10-03', 'event_status' => $status, 'status' => 'unpaid', 'grand_total' => 2000,
    ]);
    DB::table('invoice_items')->insert(['invoice_id' => 2, 'name' => 'Photobooth']);

    foreach ([10, 'all'] as $month) {
        $rows = financialExportRows(['month' => $month, 'event_status' => '']);
        expect(array_column($rows, 'No Invoice'))->toBe(['INV-001'])
            ->and(array_sum(array_column($rows, 'Nilai Invoice')))->toEqual(1000)
            ->and(financialExportRows(['month' => $month, 'event_status' => 'cancel']))->toBe([]);
    }

    expect(DB::table('invoices')->where('id', 2)->value('grand_total'))->toEqual(2000);
})->with(['cancel', 'cancelled', 'canceled']);

it('uses quotation details only when event date or sales is missing on the invoice', function (array $invoice, string $date, string $sales): void {
    DB::table('quotes')->where('id', 1)->update(['event_date' => '2026-11-10', 'user_id' => 2]);
    DB::table('invoices')->where('id', 1)->update($invoice);

    $row = financialExportRows()[0];

    expect($row['Tanggal Event'])->toBe($date)
        ->and($row['Sales'])->toBe($sales);
})->with([
    'legacy invoice' => [['user_id' => null], '10 Nov 2026', 'Quote Sales'],
    'invoice date wins' => [['event_date' => '2026-11-12'], '12 Nov 2026', 'Quote Sales'],
    'invoice salesperson wins' => [['user_id' => 1], '10 Nov 2026', 'Invoice Sales'],
    'missing salesperson' => [['user_id' => 999], '10 Nov 2026', 'Quote Sales'],
    'standalone invoice without details' => [['quote_id' => null], '', ''],
]);

it('keeps total receipts separate from period receipts and preserves PPH adjusted receivables', function (): void {
    DB::table('invoices')->where('id', 1)->update(['pph_amount' => 50]);
    DB::table('payments')->insert([
        ['invoice_id' => 1, 'amount' => 100, 'paid_at' => '2026-09-30'],
        ['invoice_id' => 1, 'amount' => 200, 'paid_at' => '2026-11-01'],
    ]);

    $row = financialExportRows()[0];

    expect($row['Nilai Invoice'])->toBe('1000')
        ->and($row['Nilai Pembayaran'])->toBe('600')
        ->and($row['Nilai Pembayaran Periode'])->toBe('300')
        ->and($row['Sisa Hutang'])->toBe('350')
        ->and(financialExportRows(['month' => 'all'])[0]['Nilai Pembayaran Periode'])->toBe('600')
        ->and(financialExportRows(['month' => 11]))->toBe([]);
});

it('excludes voided payments from both payment columns and the outstanding balance', function (): void {
    $paymentId = DB::table('payments')->value('id');
    DB::table('payment_voids')->insert([
        'payment_id' => $paymentId, 'invoice_id' => 1, 'invoice_number' => 'INV-001',
        'actor_id' => 1, 'reason' => 'Duplicate receipt', 'snapshot' => '{}',
        'result' => '{}', 'created_at' => '2026-10-06',
    ]);

    $row = financialExportRows()[0];

    expect($row['Nilai Pembayaran'])->toBe('0')
        ->and($row['Nilai Pembayaran Periode'])->toBe('0')
        ->and($row['Sisa Hutang'])->toBe('1000');
});

it('orders exported invoices by issue date with a stable order on the same date', function (): void {
    foreach ([2 => '2026-10-01', 3 => '2026-10-03'] as $id => $issuedAt) {
        DB::table('invoices')->insert([
            'id' => $id, 'invoice_number' => 'INV-00'.$id, 'issued_at' => $issuedAt,
            'business_unit' => 'capture_it', 'event_status' => 'confirm', 'grand_total' => 2000,
        ]);
        DB::table('invoice_items')->insert(['invoice_id' => $id, 'name' => 'Photobooth']);
    }

    expect(array_column(financialExportRows(), 'No Invoice'))->toBe(['INV-002', 'INV-001', 'INV-003']);
});

it('exports customer phone numbers as text without losing leading zeroes or country codes', function (array $numbers, string $expected): void {
    DB::table('persons')->where('id', 1)->update(['contact_numbers' => json_encode($numbers)]);

    expect(financialExportRows()[0]['No. Telepon Pemesan'])->toBe($expected);
})->with([
    'local number' => [[['value' => '081234567890', 'label' => 'home']], "'081234567890"],
    'country code' => [[['value' => '+6281234567890', 'label' => 'work']], "'+6281234567890"],
    'multiple numbers and duplicate blanks' => [[['value' => ' 081234567890 '], ['value' => ''], ['value' => '081234567890'], ['value' => '+6289876543210']], "'081234567890; +6289876543210"],
    'legacy flat values' => [['081234567890'], "'081234567890"],
    'no phone' => [[], ''],
]);

it('takes the phone from the selected recipient without borrowing another customer phone', function (array $invoice, string $expectedCustomer, string $expectedPhone): void {
    DB::table('persons')->where('id', 2)->update(['contact_numbers' => json_encode([['value' => '081200000002']])]);
    DB::table('invoices')->where('id', 1)->update($invoice);

    $row = financialExportRows()[0];

    expect($row['Nama Customer'])->toBe($expectedCustomer)
        ->and($row['No. Telepon Pemesan'])->toBe($expectedPhone);
})->with([
    'invoice contact with no phone' => [['person_id' => 1], 'Customer', ''],
    'legacy quote contact' => [['person_id' => null], 'Quote Customer', "'081200000002"],
    'standalone invoice' => [['person_id' => null, 'quote_id' => null], '-', ''],
    'independent invoice snapshot' => [['person_id' => null, 'bill_to_person_name' => 'Snapshot Customer'], 'Snapshot Customer', ''],
]);
