<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\HistoricalDocumentDatabase;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;
use Webkul\Admin\Models\HistoricalDocumentBatch;
use Webkul\Admin\Services\HistoricalDocumentService;
use Webkul\Admin\Services\HistoricalWorkbook;
use Webkul\Core\Support\DocumentNumbers;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Invoice\Models\WorkOrder;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Services\QuoteNumberService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.historical_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('historical_test');
    Schema::clearResolvedInstance('db.schema');
    $this->travelTo(now()->parse('2026-10-12 12:00:00'));
    DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4));
    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) substr($date, 5, 2));

    HistoricalDocumentDatabase::createFinancialSchema();
    DB::table('organizations')->insert(['id' => 1, 'name' => 'PT Pelanggan Lama']);
    DB::table('persons')->insert(['id' => 1, 'name' => 'Person Lama', 'organization_id' => 1]);
    DB::table('users')->insert(['id' => 1, 'name' => 'Admin Sales']);
    DB::table('products')->insert(['id' => 1, 'name' => 'Photobooth', 'sku' => 'PRD-0001']);
    $this->importer = app(HistoricalDocumentService::class);
    $this->legacy = [
        'type' => 'invoice', 'number' => 'INV-2608-0007', 'document_date' => '2026-08-10',
        'person_id' => 1, 'user_id' => 1, 'subject' => 'Acara Agustus', 'business_unit' => 'capture_it',
        'event_date' => '2026-08-12', 'due_at' => '2026-08-24', 'grand_total' => 10000000,
        'items' => [['product_id' => 1, 'name' => 'Photobooth Lama', 'quantity' => 2, 'day' => 1, 'total' => 10000000]],
        'payments' => [['amount' => 3000000, 'paid_at' => '2026-09-02', 'payment_method' => 'Transfer']],
    ];
    $role = (new Role)->forceFill(['permission_type' => 'all']);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Administrator', 'status' => 1])->setRelation('role', $role), 'user');
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
});

afterEach(function (): void {
    $this->travelBack();
    DB::purge('historical_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

function legacyPreview(array $documents): HistoricalDocumentBatch
{
    return HistoricalDocumentBatch::create([
        'id' => (string) Str::uuid(), 'created_by' => 1, 'source' => 'Test', 'status' => 'preview',
        'payload' => ['documents' => app(HistoricalDocumentService::class)->validate($documents)],
        'expires_at' => now()->addDay(),
    ]);
}

it('previews without writes then imports exact historical dates numbers and receipts idempotently', function () {
    $batch = legacyPreview([$this->legacy]);
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
    $first = $this->importer->commit($batch->id, 1);
    $retry = $this->importer->commit($batch->id, 1);
    $invoice = Invoice::first();
    expect($retry->result)->toBe($first->result)->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1)
        ->and($invoice->invoice_number)->toBe('INV-2608-0007')->and($invoice->issued_at->format('Y-m-d'))->toBe('2026-08-10')
        ->and($invoice->created_at->format('Y-m-d'))->toBe('2026-10-12')->and($invoice->status)->toBe('partial')
        ->and((float) $invoice->balance_due)->toBe(7000000.0)
        ->and(Payment::first()->paid_at->format('Y-m-d'))->toBe('2026-09-02')
        ->and(ProformaInvoice::count())->toBe(0)->and(WorkOrder::count())->toBe(0);
    $report = app(FinancialReportController::class);
    $august = $report->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 8]))->getData();
    $september = $report->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 9]))->getData();
    $october = $report->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 10]))->getData();
    expect($august['financialSummary']['invoiced'])->toBe(10000000.0)
        ->and($august['financialSummary']['received'])->toBe(0.0)
        ->and($september['financialSummary']['invoiced'])->toBe(0.0)
        ->and($september['financialSummary']['received'])->toBe(3000000.0)
        ->and($october['financialSummary']['invoiced'])->toBe(0.0)
        ->and($october['financialSummary']['received'])->toBe(0.0);
});

it('imports quote DP and settlement out of row order without counting the quote as revenue', function () {
    $quote = array_replace($this->legacy, ['type' => 'quote', 'number' => 'QT-2608-0001', 'payments' => [], 'expired_at' => '2026-08-31']);
    $dp = array_replace($this->legacy, ['billing_type' => 'down_payment', 'quote_number' => $quote['number'], 'grand_total' => 3000000,
        'items' => [array_replace($this->legacy['items'][0], ['total' => 3000000])]]);
    $settlement = array_replace($this->legacy, ['number' => 'INV-2608-0008', 'billing_type' => 'settlement', 'quote_number' => $quote['number'],
        'dp_invoice_number' => $dp['number'], 'grand_total' => 7000000, 'payments' => [],
        'items' => [array_replace($this->legacy['items'][0], ['total' => 7000000])]]);
    $batch = legacyPreview([$settlement, $dp, $quote]);
    $this->importer->commit($batch->id, 1);
    $invoices = Invoice::orderBy('id')->get();
    expect(Quote::first()->document_date->format('Y-m-d'))->toBe('2026-08-10')
        ->and(Quote::first()->created_at->format('Y-m-d'))->toBe('2026-10-12')
        ->and((float) Invoice::sum('grand_total'))->toBe(10000000.0)
        ->and($invoices[0]->status)->toBe('paid')->and($invoices[1]->status)->toBe('unpaid')
        ->and((int) $invoices[1]->dp_invoice_id)->toBe($invoices[0]->id)
        ->and($invoices[1]->quote_id)->toBe(Quote::first()->id);
    $pdf = view('admin::quotes.pdf', ['quote' => Quote::with(['items', 'person'])->first()])->render();
    expect($pdf)->toContain('10 Aug 2026');
});

it('rejects duplicate normalized numbers on preview and rechecks after preview', function () {
    $batch1 = legacyPreview([$this->legacy]);
    $batch2 = legacyPreview([$this->legacy]);
    $this->importer->commit($batch1->id, 1);
    expect(fn () => $this->importer->commit($batch2->id, 1))->toThrow(ValidationException::class)
        ->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1);
    expect(fn () => legacyPreview([array_replace($this->legacy, ['number' => 'inv 2608-0007'])]))->toThrow(ValidationException::class);
});

it('validates totals references dates and payment amounts before any writes', function (string $case) {
    $d = $this->legacy;
    match ($case) {
        'total' => $d['grand_total'] = 1,
        'overpaid' => $d['payments'][0]['amount'] = 10000001,
        'future' => $d['document_date'] = '2027-01-01',
        'quote' => $d['quote_number'] = 'MISSING',
        'dp' => $d['billing_type'] = 'settlement',
        'product' => $d['items'][0]['product_id'] = 999,
        'contact' => $d['person_id'] = 999,
    };
    expect(fn () => legacyPreview([$d]))->toThrow(ValidationException::class)
        ->and(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
})->with(['total', 'overpaid', 'future', 'quote', 'dp', 'product', 'contact']);

it('honors closed periods both at preview and after a preview was accepted', function () {
    $batch = legacyPreview([$this->legacy]);
    DB::table('financial_period_locks')->insert(['period' => '2026-09', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-30', 'locked_at' => now()]);
    expect(fn () => legacyPreview([$this->legacy]))->toThrow(ValidationException::class);
    expect(fn () => $this->importer->commit($batch->id, 1))->toThrow(ValidationException::class)->and(Invoice::count())->toBe(0);
});

it('allocates new automatic numbers above imported formats and never reuses reserved numbers', function () {
    $d = array_replace($this->legacy, ['number' => 'INV-2610-0017']);
    $this->importer->commit(legacyPreview([$d])->id, 1);
    $numbers = app(DocumentNumbers::class);
    expect($numbers->next('invoice', 'INV 2610-'))->toBe('INV 2610-0018')
        ->and($numbers->next('invoice', 'INV 2610-'))->toBe('INV 2610-0019');
    $quote = array_replace($this->legacy, ['type' => 'quote', 'number' => 'QT-2610-0099', 'payments' => []]);
    $this->importer->commit(legacyPreview([$quote])->id, 1);
    expect(app(QuoteNumberService::class)->generate())->toBe('QT 2610-0100');
});

it('increments long legacy number sequences without integer overflow or reused numbers', function () {
    $numbers = app(DocumentNumbers::class);
    DB::transaction(fn () => $numbers->reserve('invoice', 'INV-2610-9999999999999999999'));
    expect($numbers->next('invoice', 'INV 2610-'))->toBe('INV 2610-10000000000000000000')
        ->and($numbers->next('invoice', 'INV 2610-'))->toBe('INV 2610-10000000000000000001');
});

it('renders corrected person and company names while preserving the financial snapshot and bill-to mode', function (string $mode) {
    $d = array_replace($this->legacy, ['bill_to_display_mode' => $mode]);
    $this->importer->commit(legacyPreview([$d])->id, 1);
    $before = Invoice::first()->getAttributes();
    DB::table('persons')->where('id', 1)->update(['name' => 'Person Dikoreksi']);
    DB::table('organizations')->where('id', 1)->update(['name' => 'PT Nama Dikoreksi']);
    $invoice = Invoice::with(['items', 'person'])->first();
    expect($invoice->billToIdentity())->toBe(['mode' => $mode, 'person_name' => 'Person Dikoreksi', 'company_name' => 'PT Nama Dikoreksi'])
        ->and($invoice->getAttributes())->toBe($before);
    $pdf = view('admin::invoices.pdf', compact('invoice'))->render();
    expect($pdf)->not->toContain('PT Pelanggan Lama', 'Person Lama');
    if ($mode !== 'company') {
        expect($pdf)->toContain('Person Dikoreksi');
    }
    if ($mode !== 'person') {
        expect($pdf)->toContain('PT Nama Dikoreksi');
    }
})->with(['person', 'company', 'both']);

it('falls back to stored names when the linked contact is unavailable', function () {
    $this->importer->commit(legacyPreview([$this->legacy])->id, 1);
    DB::table('persons')->where('id', 1)->delete();
    expect(Invoice::first()->billToIdentity()['person_name'])->toBe('Person Lama');
});

it('accepts mapped Excel headers multiple items payments and Excel dates without evaluating formulas', function () {
    $sheets = [
        ['name' => 'Old', 'headers' => ['Number', 'Date', 'Client', 'Sales', 'Event', 'Unit', 'Due', 'Value'], 'rows' => [['INV-X', '10/08/2026', 'Person Lama', 'Admin Sales', 'Old Event', 'capture_it', '24/08/2026', 10000000]]],
        ['name' => 'Lines', 'headers' => ['Type', 'Ref', 'SKU', 'Name', 'Total'], 'rows' => [['invoice', 'INV-X', 'PRD-0001', 'Product Old', 10000000]]],
        ['name' => 'Cash', 'headers' => ['Ref', 'Paid', 'Value', 'Method'], 'rows' => [['INV-X', '02/09/2026', 3000000, 'Transfer']]],
    ];
    // Explicit type field on non-template files; unknown columns are never guessed.
    $sheets[0]['headers'][] = 'Kind';
    $sheets[0]['rows'][0][] = 'invoice';
    $mapping = [
        'documents' => ['sheet' => 0, 'columns' => ['number' => 0, 'document_date' => 1, 'person_name' => 2, 'sales_name' => 3, 'subject' => 4, 'business_unit' => 5, 'due_at' => 6, 'grand_total' => 7, 'type' => 8]],
        'items' => ['sheet' => 1, 'columns' => ['type' => 0, 'number' => 1, 'sku' => 2, 'name' => 3, 'total' => 4]],
        'payments' => ['sheet' => 2, 'columns' => ['number' => 0, 'paid_at' => 1, 'amount' => 2, 'payment_method' => 3]],
    ];
    $documents = app(HistoricalWorkbook::class)->documents($sheets, $mapping);
    $this->importer->commit(legacyPreview($documents)->id, 1);
    expect(Invoice::first()->issued_at->format('Y-m-d'))->toBe('2026-08-10')->and(Payment::first()->amount)->toEqual(3000000);
    $book = new Spreadsheet;
    $book->getActiveSheet()->setCellValue('A1', 'number')->setCellValue('A2', '=1+1');
    $file = tempnam(sys_get_temp_dir(), 'legacy-xlsx-');
    try {
        (new Xlsx($book))->save($file);
        expect(fn () => app(HistoricalWorkbook::class)->read(new UploadedFile($file, 'test.xlsx', null, null, true)))->toThrow(ValidationException::class);
    } finally {
        @unlink($file);
    }
});

it('requires import permission and ownership and protects the real preview commit routes', function () {
    $this->get(route('admin.historical-documents.index'))->assertOk()->assertSee('Input Data Lama');
    $this->get(route('admin.historical-documents.create'))->assertOk()->assertSee('Periksa data');
    $response = $this->post(route('admin.historical-documents.preview'), ['documents' => [$this->legacy]]);
    $response->assertRedirect();
    $batch = HistoricalDocumentBatch::first();
    $this->get(route('admin.historical-documents.show', $batch->id))->assertOk()->assertSee('INV-2608-0007')->assertSee('Belum ada quotation');
    $this->post(route('admin.historical-documents.store', $batch->id), ['confirm' => 1])->assertRedirect();
    $this->post(route('admin.historical-documents.store', $batch->id), ['confirm' => 1])->assertRedirect();
    expect(Invoice::count())->toBe(1);
    $other = (new User)->forceFill(['id' => 2, 'name' => 'Other Admin', 'status' => 1])->setRelation('role', (new Role)->forceFill(['permission_type' => 'all']));
    $this->actingAs($other, 'user');
    $this->get(route('admin.historical-documents.show', $batch->id))->assertNotFound();
    $this->post(route('admin.historical-documents.store', $batch->id), ['confirm' => 1])->assertNotFound();
    $other->setRelation('role', (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices']]));
    $this->get(route('admin.historical-documents.index'))->assertUnauthorized();
    $this->postJson(route('admin.historical-documents.preview'), ['documents' => [$this->legacy]])->assertUnauthorized();
});

it('rolls back the whole import including number reservations if a later document cannot be saved', function () {
    $second = array_replace($this->legacy, ['number' => 'INV-FAIL']);
    $batch = legacyPreview([$this->legacy, $second]);
    DB::unprepared("CREATE TRIGGER fail_legacy_insert BEFORE INSERT ON invoices WHEN NEW.invoice_number = 'INV-FAIL' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
    expect(fn () => $this->importer->commit($batch->id, 1))->toThrow(QueryException::class);
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0)->and(DB::table('crm_document_numbers')->count())->toBe(0)
        ->and($batch->fresh()->status)->toBe('preview');
});

it('reports the first and last historical quote dates in their original month and rejects expired previews', function () {
    $quote = array_replace($this->legacy, ['type' => 'quote', 'number' => 'QT-2608-0001', 'document_date' => '2026-08-01', 'payments' => []]);
    $last = array_replace($quote, ['number' => 'QT-2608-0002', 'document_date' => '2026-08-31', 'due_at' => null]);
    $this->importer->commit(legacyPreview([$quote, $last])->id, 1);
    $report = app(Webkul\Admin\Helpers\Reporting\Quote::class);
    expect($report->getTotalQuotes('2026-08-01 00:00:00', '2026-08-31 23:59:59'))->toBe(2)
        ->and($report->getTotalQuotes('2026-10-01 00:00:00', '2026-10-31 23:59:59'))->toBe(0);
    $batch = legacyPreview([$this->legacy]);
    $this->travelTo(now()->addDays(2));
    expect(fn () => $this->importer->commit($batch->id, 1))->toThrow(ValidationException::class)->and(Invoice::count())->toBe(0);
});
