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
        $table->integer('quote_id')->nullable();
        foreach (['invoice_number', 'project_code', 'business_unit', 'subject', 'billing_type', 'event_status', 'status', 'bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name'] as $column) {
            $table->string($column)->default('');
        }
        $table->decimal('grand_total', 16, 4)->default(0);
        $table->decimal('quote_total_snapshot', 16, 4)->nullable();
        $table->dateTime('issued_at');
    });
    Schema::create('quotes', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('person_id')->nullable();
        $table->decimal('grand_total', 16, 4)->default(0);
        foreach (['bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name'] as $column) {
            $table->string($column)->default('');
        }
    });
    Schema::create('persons', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->integer('organization_id')->nullable();
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
    $header = null;
    $rows = [];
    while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
        if ($row[0] === 'Project Code') {
            $header = $row;
        } elseif ($header !== null) {
            expect($row)->toHaveCount(count($header));
            $rows[] = array_combine($header, $row);
        }
    }
    fclose($stream);
    expect($header)->toContain('Company', 'PPH');

    return $rows;
}

it('exports the invoice company snapshot with legacy fallbacks and an empty PPH field', function (array $invoice, array $quote, string $expected): void {
    DB::table('invoices')->where('id', 1)->update($invoice ?: ['subject' => 'Legacy invoice']);
    DB::table('quotes')->where('id', 1)->update($quote ?: ['bill_to_company_name' => '']);

    $rows = financialExportRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Company'])->toBe($expected)
        ->and($rows[0]['PPH'])->toBe('')
        ->and($rows[0]['Invoice Value'])->toBe('1000')
        ->and($rows[0]['Paid To Date'])->toBe('300')
        ->and($rows[0]['Outstanding Current'])->toBe('700')
        ->and($rows[0]['Cash Surplus In Period'])->toBe('200');
})->with([
    'invoice snapshot takes priority' => [['bill_to_company_name' => 'Invoice Company'], ['bill_to_company_name' => 'Quote Company'], 'Invoice Company'],
    'legacy quote snapshot' => [[], ['bill_to_company_name' => 'Quote Company'], 'Quote Company'],
    'legacy quote organization' => [[], [], 'Quote Customer Company'],
    'legacy invoice organization' => [['quote_id' => null], [], 'Current Customer Company'],
    'missing identity' => [['quote_id' => null, 'person_id' => null], [], '-'],
    'person-only snapshot stays immutable' => [['bill_to_person_name' => 'Original Customer'], [], '-'],
    'CSV punctuation and Unicode' => [['bill_to_company_name' => "PT \"Café\"; Indonesia\nJakarta"], [], "PT \"Café\"; Indonesia\nJakarta"],
]);

it('keeps period business unit status and product filters in the financial export', function (): void {
    foreach ([
        ['issued_at' => '2026-09-30'],
        ['issued_at' => '2025-10-03'],
        ['business_unit' => 'varbel'],
        ['event_status' => 'cancel'],
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
    expect(array_column($rows, 'Invoice'))->toBe(['INV-001']);
    expect(financialExportRows(['month' => 12]))->toBe([]);
});
