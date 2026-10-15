<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\HistoricalDocumentDatabase;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;
use Webkul\Admin\Services\CommercialRevisionService;
use Webkul\Admin\Services\FinanceSalesDashboardService;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\ProformaInvoiceService;
use Webkul\Admin\Services\SalesCommissionExportService;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function () {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.revision_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ], 'app.key' => str_repeat('a', 32)]);
    DB::setDefaultConnection('revision_test');
    Schema::clearResolvedInstance('db.schema');
    HistoricalDocumentDatabase::createFinancialSchema();
    DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4));
    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) substr($date, 5, 2));
    $this->travelTo(now()->parse('2026-10-15 10:00:00'));
    DB::table('users')->insert(['id' => 1, 'name' => 'Administrator']);
    DB::table('organizations')->insert([['id' => 1, 'name' => 'PT Awal'], ['id' => 2, 'name' => 'PT Revisi']]);
    DB::table('persons')->insert([['id' => 1, 'name' => 'Customer Awal', 'organization_id' => 1], ['id' => 2, 'name' => 'Customer Baru', 'organization_id' => 2]]);
    DB::table('products')->insert([['id' => 1, 'name' => 'Photobooth', 'sku' => 'PRD-0001'], ['id' => 2, 'name' => 'Print', 'sku' => 'PRD-0002']]);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Administrator', 'status' => 1])->setRelation('role', (new Role)->forceFill(['permission_type' => 'all'])), 'user');
    $this->withoutMiddleware(CanInstall::class);
    $this->quote = Quote::create([
        'quote_number' => 'QT-REV-01', 'project_code' => 'PRJ-REV-01', 'subject' => 'Event Awal',
        'person_id' => 1, 'user_id' => 1, 'bill_to_display_mode' => 'both',
        'bill_to_person_name' => 'Customer Awal', 'bill_to_company_name' => 'PT Awal',
        'event_date' => '2026-10-20', 'location' => 'Jakarta', 'payment_term' => 'Lunas sebelum event',
        'business_unit' => 'capture_it', 'description' => 'Catatan awal',
        'billing_address' => ['address' => 'Alamat awal', 'city' => 'Jakarta'], 'shipping_address' => [],
        'sub_total' => 10000000, 'grand_total' => 10000000, 'adjustment_amount' => 0,
    ]);
    $this->quote->items()->create([
        'product_id' => 1, 'name' => 'Photobooth', 'sku' => 'PRD-0001', 'description' => 'Rental booth',
        'quantity' => 2, 'unit' => 'day', 'day' => 1, 'equipment_quantity' => 1,
        'price' => 5000000, 'total' => 10000000,
    ]);
    $this->service = app(CommercialRevisionService::class);
});

afterEach(function () {
    $this->travelBack();
    DB::purge('revision_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

function revisionProforma(Quote $quote, string $type = 'full_payment', float $dp = 4000000): ProformaInvoice
{
    return app(ProformaInvoiceService::class)->create($quote->fresh(), [
        'billing_type' => $type, 'billing_method' => 'nominal', 'billing_amount' => $dp,
        'issued_at' => '2026-10-15', 'due_at' => '2026-10-20', 'payment_term' => 'Lunas sebelum event',
    ], 1);
}

function revisionInvoice(ProformaInvoice $pf, float $payment = 0): Invoice
{
    $invoice = app(FlexibleQuoteBillingService::class)->approveProforma($pf, 1);
    if ($payment > 0) {
        app(PaymentService::class)->addPayment($invoice, ['amount' => $payment, 'paid_at' => '2026-10-15 09:00:00', 'created_by' => 1]);
    }

    return $invoice->fresh();
}

function revisionPayload(string $type, int $id, float $total = 12000000): array
{
    $preview = app(CommercialRevisionService::class)->preview($type, $id);
    $form = $preview['form'];
    $form['items'][0]['price'] = $total / 2;

    return $form + ['fingerprint' => $preview['fingerprint'], 'reason' => 'Customer merevisi nilai transaksi'];
}

function revisionReport(): array
{
    return app(FinancialReportController::class)->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 10]))->getData();
}

it('updates quote proforma invoice and financial report from any entry point without duplicating receipts', function (string $source) {
    $pf = revisionProforma($this->quote);
    $invoice = revisionInvoice($pf, 3000000);
    DB::table('expenses')->insert(['invoice_id' => $invoice->id, 'amount' => 1500000, 'expense_date' => '2026-10-15']);
    $original = $invoice->getAttributes();
    $payments = Payment::all()->toArray();
    $before = revisionReport();
    expect($before['financialSummary']['invoiced'])->toBe(10000000.0);
    $id = match ($source) {
        'quote' => $this->quote->id, 'proforma' => $pf->id, default => $invoice->id
    };
    $response = $this->put(route('admin.commercial-revisions.update', [$source, $id]), revisionPayload($source, $id));
    $response->assertRedirect()->assertSessionHasNoErrors();
    $invoice->refresh();
    expect((float) $this->quote->fresh()->grand_total)->toBe(12000000.0)
        ->and((float) $pf->fresh()->amount)->toBe(12000000.0)
        ->and((float) $pf->fresh()->quote_snapshot['attributes']['grand_total'])->toBe(12000000.0)
        ->and((float) $invoice->grand_total)->toBe(12000000.0)
        ->and((float) $invoice->balance_due)->toBe(9000000.0)
        ->and($invoice->status)->toBe('partial')->and(Payment::all()->toArray())->toBe($payments);
    foreach (['invoice_number', 'issued_at', 'due_at', 'project_code', 'quote_id'] as $field) {
        expect($invoice->getRawOriginal($field))->toBe($original[$field]);
    }
    $after = revisionReport();
    expect($after['financialSummary']['invoiced'])->toBe(12000000.0)
        ->and($after['financialSummary']['received'])->toBe(3000000.0)
        ->and($after['financialSummary']['outstanding'])->toBe(9000000.0)
        ->and($after['financialSummary']['expense'])->toBe(1500000.0)
        ->and($after['invoicePerformance']->first()['estimated_profit'])->toBe(10500000.0)
        ->and(DB::table('commercial_document_revisions')->count())->toBe(1);
    $export = app(FinancialReportController::class)->export(Request::create('/', 'GET', ['year' => 2026, 'month' => 10]));
    ob_start();
    $export->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain('12000000', '9000000', $invoice->invoice_number);
    $this->service->apply($source, $id, revisionPayload($source, $id, 8000000), 1);
    expect(revisionReport()['financialSummary']['invoiced'])->toBe(8000000.0)
        ->and(revisionReport()['financialSummary']['outstanding'])->toBe(5000000.0);
})->with(['quote', 'proforma', 'invoice']);

it('keeps paid DP intact and revises the existing settlement and both proformas', function () {
    $dpPf = revisionProforma($this->quote, 'down_payment');
    $dp = revisionInvoice($dpPf, 4000000);
    $settlementPf = revisionProforma($this->quote, 'settlement');
    $settlement = revisionInvoice($settlementPf);
    $this->service->apply('invoice', $settlement->id, revisionPayload('invoice', $settlement->id), 1);
    expect((float) $dp->fresh()->grand_total)->toBe(4000000.0)->and($dp->fresh()->status)->toBe('paid')
        ->and((float) $dp->fresh()->quote_total_snapshot)->toBe(12000000.0)
        ->and((float) $dp->fresh()->remaining_amount_snapshot)->toBe(8000000.0)
        ->and((float) $settlement->fresh()->grand_total)->toBe(8000000.0)
        ->and((float) $settlementPf->fresh()->amount)->toBe(8000000.0)
        ->and((float) $dpPf->fresh()->amount)->toBe(4000000.0)
        ->and((float) $settlement->items()->sum('total'))->toBe(8000000.0)
        ->and(revisionReport()['financialSummary']['invoiced'])->toBe(12000000.0)
        ->and(revisionReport()['financialSummary']['received'])->toBe(4000000.0)
        ->and(revisionReport()['financialSummary']['outstanding'])->toBe(8000000.0);
});

it('uses revised commercial data when a new settlement is generated after a DP revision', function () {
    $dp = revisionInvoice(revisionProforma($this->quote, 'down_payment'), 4000000);
    $this->service->apply('invoice', $dp->id, revisionPayload('invoice', $dp->id), 1);
    expect(app(FlexibleQuoteBillingService::class)->sumUnbilledForQuoteIds([$this->quote->id]))->toBe(8000000.0);
    $pf = revisionProforma($this->quote, 'settlement');
    expect((float) $pf->amount)->toBe(8000000.0)->and((float) $pf->quote_snapshot['attributes']['grand_total'])->toBe(12000000.0);
    $settlement = revisionInvoice($pf);
    expect((float) $settlement->grand_total)->toBe(8000000.0)->and(revisionReport()['financialSummary']['invoiced'])->toBe(12000000.0);
});

it('updates an issued settlement proforma before conversion while receipts and issued revenue stay unchanged', function () {
    revisionInvoice(revisionProforma($this->quote, 'down_payment'), 4000000);
    $pf = revisionProforma($this->quote, 'settlement');
    $this->service->apply('proforma', $pf->id, revisionPayload('proforma', $pf->id), 1);
    expect((float) $pf->fresh()->amount)->toBe(8000000.0)
        ->and(revisionReport()['financialSummary']['invoiced'])->toBe(4000000.0);
    $invoice = revisionInvoice($pf->fresh());
    expect((float) $invoice->grand_total)->toBe(8000000.0)->and(revisionReport()['financialSummary']['invoiced'])->toBe(12000000.0);
});

it('changes unpaid DP allocation without changing the total agreement', function () {
    $pf = revisionProforma($this->quote, 'down_payment');
    $dp = revisionInvoice($pf, 2000000);
    $input = revisionPayload('invoice', $dp->id, 10000000);
    $input['dp_amount'] = 5000000;
    $this->service->apply('invoice', $dp->id, $input, 1);
    expect((float) $this->quote->fresh()->grand_total)->toBe(10000000.0)
        ->and((float) $dp->fresh()->grand_total)->toBe(5000000.0)
        ->and((float) $pf->fresh()->amount)->toBe(5000000.0)
        ->and((float) $dp->fresh()->balance_due)->toBe(3000000.0)
        ->and(revisionReport()['financialSummary']['invoiced'])->toBe(5000000.0);
});

it('reopens a paid full invoice as partial when its amount increases without modifying cash', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote), 10000000);
    $this->service->apply('invoice', $invoice->id, revisionPayload('invoice', $invoice->id), 1);
    expect($invoice->fresh()->status)->toBe('partial')->and((float) $invoice->fresh()->balance_due)->toBe(2000000.0)
        ->and(revisionReport()['financialSummary']['received'])->toBe(10000000.0);
    app(PaymentService::class)->addPayment($invoice->fresh(), ['amount' => 2000000]);
    expect($invoice->fresh()->status)->toBe('paid')->and(Payment::count())->toBe(2);
});

it('updates unconverted proformas without adding revenue and converts the revised amount', function () {
    $pf = revisionProforma($this->quote);
    $this->service->apply('proforma', $pf->id, revisionPayload('proforma', $pf->id, 8000000), 1);
    expect(revisionReport()['financialSummary']['invoiced'])->toBe(0.0);
    $invoice = revisionInvoice($pf->fresh());
    expect((float) $invoice->grand_total)->toBe(8000000.0)->and(revisionReport()['financialSummary']['invoiced'])->toBe(8000000.0);
});

it('synchronizes contact event addresses products discounts and taxes into all regenerated PDFs', function () {
    $pf = revisionProforma($this->quote);
    $invoice = revisionInvoice($pf);
    $oldItemId = $invoice->items->first()->id;
    DB::table('invoice_items')->where('id', $oldItemId)->update(['quote_item_id' => null]); // Legacy document.
    $input = revisionPayload('invoice', $invoice->id);
    $input = array_replace($input, ['subject' => 'Event Revisi', 'person_id' => 2, 'event_date' => '2026-10-24', 'location' => 'Bandung', 'payment_term' => 'Termin direvisi', 'description' => 'Catatan revisi', 'billing_address' => ['address' => 'Alamat revisi', 'city' => 'Bandung']]);
    $input['items'][0]['name'] = 'Paket Revisi';
    $input['items'][0]['discount_amount'] = 1000000;
    $input['items'][0]['tax_amount'] = 1100000;
    $input['items'][] = ['product_id' => 2, 'name' => 'Cetak Tambahan', 'description' => 'Extra print', 'quantity' => 100, 'unit' => 'pcs', 'price' => 10000, 'discount_amount' => 0, 'tax_amount' => 0];
    $this->service->apply('invoice', $invoice->id, $input, 1);
    $q = $this->quote->fresh('items');
    $i = $invoice->fresh(['items', 'person.organization']);
    $p = $pf->fresh();
    expect($q->subject)->toBe('Event Revisi')->and($i->subject)->toBe('Event Revisi')
        ->and($p->snapshotQuote()->event_date->format('Y-m-d'))->toBe('2026-10-24')
        ->and($i->billing_address['address'])->toBe('Alamat revisi')
        ->and($p->billToIdentity()['company_name'])->toBe('PT Revisi')
        ->and($i->items->first()->id)->toBe($oldItemId)
        ->and($i->items)->toHaveCount(2)->and((float) $i->grand_total)->toBe(13100000.0)
        ->and((float) $q->grand_total)->toBe(13100000.0)->and((float) $p->amount)->toBe(13100000.0);
    foreach ([view('admin::quotes.pdf', ['quote' => $q]), view('admin::invoices.pdf', ['invoice' => $i])] as $pdf) {
        expect($pdf->render())->toContain('PT Revisi', 'Paket Revisi', 'Cetak Tambahan');
    }
    expect($p->snapshotQuote()->items->pluck('name')->all())->toBe(['Paket Revisi', 'Cetak Tambahan'])
        ->and(view('admin::invoices.proformas.print', ['proforma' => $p])->render())->toContain('PT Revisi', '13.100.000,00', 'Termin direvisi');
    $preview = $this->service->preview('invoice', $i->id);
    expect($preview['form']['document_discount_amount'])->toBe(0.0)->and($preview['form']['document_tax_amount'])->toBe(0.0);
    $audit = DB::table('commercial_document_revisions')->first();
    expect(json_decode($audit->before, true)['quote']['subject'])->toBe('Event Awal')
        ->and(json_decode($audit->after, true)['quote']['subject'])->toBe('Event Revisi');
});

it('rejects stale forms after a payment or another revision with no partial writes', function (string $change) {
    $invoice = revisionInvoice(revisionProforma($this->quote));
    $input = revisionPayload('invoice', $invoice->id);
    if ($change === 'payment') {
        app(PaymentService::class)->addPayment($invoice, ['amount' => 1000000]);
    } else {
        $this->service->apply('invoice', $invoice->id, revisionPayload('invoice', $invoice->id, 11000000), 1);
    }
    $before = $this->service->preview('invoice', $invoice->id)['state'];
    $this->put(route('admin.commercial-revisions.update', ['invoice', $invoice->id]), $input)->assertSessionHasErrors('fingerprint');
    expect($this->service->preview('invoice', $invoice->id)['state'])->toBe($before);
})->with(['payment', 'revision']);

it('keeps the original version after validation errors and reloads fresh data after a stale retry', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote));
    $url = route('admin.commercial-revisions.edit', ['invoice', $invoice->id]);
    $input = revisionPayload('invoice', $invoice->id);
    $this->from($url)->put(route('admin.commercial-revisions.update', ['invoice', $invoice->id]), array_replace($input, ['reason' => '']))
        ->assertRedirect($url)->assertSessionHasErrors('reason');
    $this->service->apply('invoice', $invoice->id, revisionPayload('invoice', $invoice->id, 11000000), 1);
    $this->get($url)->assertOk()->assertSee('value="'.$input['fingerprint'].'"', false);
    $this->from($url)->put(route('admin.commercial-revisions.update', ['invoice', $invoice->id]), $input)
        ->assertRedirect($url)->assertSessionHasErrors('fingerprint');
    $latest = $this->service->preview('invoice', $invoice->id);
    $this->get($url)->assertOk()->assertSee('value="'.$latest['fingerprint'].'"', false)
        ->assertSee('value="5500000"', false);
    expect((float) $invoice->fresh()->grand_total)->toBe(11000000.0)
        ->and(DB::table('commercial_document_revisions')->count())->toBe(1);
});

it('rejects overpayments paid DP changes closed periods and foreign quote items atomically', function (string $case) {
    $pf = revisionProforma($this->quote, $case === 'paid_dp' ? 'down_payment' : 'full_payment');
    $invoice = revisionInvoice($pf, $case === 'paid_dp' ? 4000000 : 3000000);
    $input = revisionPayload('invoice', $invoice->id);
    if ($case === 'overpaid') {
        $input['items'][0]['price'] = 1000000;
    }
    if ($case === 'paid_dp') {
        $input['dp_amount'] = 5000000;
    }
    if ($case === 'foreign_item') {
        $input['items'][0]['id'] = 9999;
    }
    if ($case === 'closed') {
        DB::table('financial_period_locks')->insert(['period' => '2026-10', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-31', 'locked_at' => now()]);
    }
    $before = $this->service->preview('invoice', $invoice->id)['state'];
    expect(fn () => $this->service->apply('invoice', $invoice->id, $input, 1))->toThrow(ValidationException::class)
        ->and($this->service->preview('invoice', $invoice->id)['state'])->toBe($before)
        ->and(DB::table('commercial_document_revisions')->count())->toBe(0);
})->with(['overpaid', 'paid_dp', 'foreign_item', 'closed']);

it('preserves PO item links and rolls back revisions that remove a referenced invoice line', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote));
    $lineId = $invoice->items()->first()->id;
    Schema::create('purchase_order_items', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_item_id');
    });
    DB::table('purchase_order_items')->insert(['invoice_item_id' => $lineId]);
    $this->service->apply('invoice', $invoice->id, revisionPayload('invoice', $invoice->id), 1);
    expect($invoice->items()->first()->id)->toBe($lineId);
    $input = revisionPayload('invoice', $invoice->id);
    unset($input['items'][0]['id']);
    $input['items'][0]['product_id'] = 2;
    $before = $this->service->preview('invoice', $invoice->id)['state'];
    expect(fn () => $this->service->apply('invoice', $invoice->id, $input, 1))->toThrow(ValidationException::class)
        ->and($this->service->preview('invoice', $invoice->id)['state'])->toBe($before)
        ->and(DB::table('purchase_order_items')->value('invoice_item_id'))->toBe($lineId);
});

it('rolls back every document when the audit insert fails', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote));
    $input = revisionPayload('invoice', $invoice->id);
    $before = $this->service->preview('invoice', $invoice->id)['state'];
    DB::statement("CREATE TRIGGER reject_revision BEFORE INSERT ON commercial_document_revisions BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
    expect(fn () => $this->service->apply('invoice', $invoice->id, $input, 1))->toThrow(QueryException::class)
        ->and($this->service->preview('invoice', $invoice->id)['state'])->toBe($before);
});

it('preserves void cancelled and unrelated document history', function () {
    $oldPf = revisionProforma($this->quote);
    $oldInvoice = revisionInvoice($oldPf);
    DB::table('invoices')->where('id', $oldInvoice->id)->update(['status' => 'void']);
    $newPf = revisionProforma($this->quote);
    $newInvoice = revisionInvoice($newPf);
    $old = $oldInvoice->fresh()->getAttributes();
    $oldSnapshot = $oldPf->fresh()->getAttributes();
    $this->service->apply('invoice', $newInvoice->id, revisionPayload('invoice', $newInvoice->id), 1);
    expect($oldInvoice->fresh()->getAttributes())->toBe($old)->and($oldPf->fresh()->getAttributes())->toBe($oldSnapshot)
        ->and(fn () => $this->service->preview('invoice', $oldInvoice->id))->toThrow(ValidationException::class)
        ->and(revisionReport()['financialSummary']['invoiced'])->toBe(12000000.0);
});

it('renders the revision form and history and redirects legacy editors into the shared flow', function () {
    $pf = revisionProforma($this->quote);
    $invoice = revisionInvoice($pf);
    $url = route('admin.commercial-revisions.edit', ['invoice', $invoice->id]);
    $this->get($url)->assertOk()->assertSee('Revisi Transaksi')->assertSee('Periksa perubahan dokumen');
    $this->get(route('admin.invoices.edit', $invoice->id))->assertRedirect($url);
    $this->get(route('admin.quotes.edit', $this->quote->id))->assertRedirect(route('admin.commercial-revisions.edit', ['quote', $this->quote->id]));
    $this->put(route('admin.invoices.update', $invoice->id), ['subject' => 'Unsynced'])->assertRedirect($url);
    expect($invoice->fresh()->subject)->toBe('Event Awal');
    $this->service->apply('invoice', $invoice->id, revisionPayload('invoice', $invoice->id), 1);
    $this->get($url)->assertOk()->assertSee('Customer merevisi nilai transaksi')->assertSee('Revisi 1');
});

it('requires the explicit transaction revision permission on GET PUT and legacy invoice writes', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote));
    $payload = revisionPayload('invoice', $invoice->id);
    auth()->guard('user')->user()->setRelation('role', (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices', 'invoices.view', 'invoices.payment', 'quotes.edit']]));
    $this->getJson(route('admin.commercial-revisions.edit', ['invoice', $invoice->id]))->assertUnauthorized();
    $this->putJson(route('admin.commercial-revisions.update', ['invoice', $invoice->id]), $payload)->assertUnauthorized();
    $this->putJson(route('admin.invoices.update', $invoice->id), ['subject' => 'Forbidden'])->assertForbidden();
    expect((float) $invoice->fresh()->grand_total)->toBe(10000000.0)->and(DB::table('commercial_document_revisions')->count())->toBe(0);
});

it('grosses up the real paid DP pair in place without altering receipts dates project or operational history', function () {
    $this->quote->update(['grand_total' => 7500000, 'sub_total' => 7500000]);
    $this->quote->items()->first()->update(['price' => 3750000, 'total' => 7500000]);
    $dpPf = revisionProforma($this->quote, 'down_payment', 3750000);
    $dp = revisionInvoice($dpPf);
    app(PaymentService::class)->addPayment($dp, ['amount' => 3750000, 'paid_at' => '2026-09-30 16:22:00', 'reference_number' => 'BANK-DP']);
    $finalPf = revisionProforma($this->quote, 'settlement');
    $final = revisionInvoice($finalPf);
    app(PaymentService::class)->addPayment($final, ['amount' => 3750000, 'paid_at' => '2026-10-12 13:23:00', 'reference_number' => 'BANK-FINAL']);
    $payments = Payment::orderBy('id')->get()->toArray();
    $numbers = Invoice::orderBy('id')->pluck('invoice_number')->all();
    $input = revisionPayload('invoice', $dp->id, 7500000);
    $input['pph_gross_up'] = 1;
    $this->put(route('admin.commercial-revisions.update', ['invoice', $dp->id]), $input)->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $this->quote->fresh()->grand_total)->toBe(7537688.44)
        ->and((float) $dp->fresh()->grand_total)->toBe(3768844.22)->and((float) $final->fresh()->grand_total)->toBe(3768844.22)
        ->and((float) $dp->fresh()->pph_amount)->toBe(18844.22)->and((float) $final->fresh()->pph_amount)->toBe(18844.22)
        ->and($dp->fresh()->status)->toBe('paid')->and($final->fresh()->status)->toBe('paid')
        ->and(Invoice::count())->toBe(2)->and(Invoice::orderBy('id')->pluck('invoice_number')->all())->toBe($numbers)
        ->and(Payment::orderBy('id')->get()->toArray())->toBe($payments)
        ->and($dp->fresh()->project_code)->toBe('PRJ-REV-01')->and($final->fresh()->dp_invoice_id)->toBe($dp->id)
        ->and((float) $dpPf->fresh()->pph_amount)->toBe(18844.22)->and((float) $finalPf->fresh()->amount)->toBe(3768844.22)
        ->and(DB::table('commercial_document_revisions')->count())->toBe(1);
    $data = revisionReport();
    expect($data['financialSummary']['invoiced'])->toBe(7537688.44)->and($data['financialSummary']['received'])->toBe(3750000.0)
        ->and($data['financialSummary']['outstanding'])->toBe(0.0)->and($data['invoicePerformance']->sum('pph_amount'))->toBe(37688.44)
        ->and($data['invoicePerformance']->sum('net_margin'))->toBe(7500000.0);
    $september = app(FinancialReportController::class)->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 9]))->getData();
    expect($september['financialSummary']['received'])->toBe(3750000.0);
    $commission = app(SalesCommissionExportService::class)->rows(['paid_from' => '2026-01-01', 'paid_to' => '2026-12-31']);
    expect($commission)->toHaveCount(1)->and($commission->first()['commission_base'])->toBe(7500000.0)
        ->and($commission->first()['paid_completion_date'])->toBe('2026-10-12')
        ->and(app(FinanceSalesDashboardService::class)->exportRows([]))->toBeEmpty();
    $this->get(route('admin.commercial-revisions.edit', ['invoice', $dp->id]))->assertOk()->assertSee('Gross-up PPh 0,5%')->assertSee('Transfer netto DP');
    expect(view('admin::invoices.pdf', ['invoice' => $dp->fresh(['items', 'person.organization'])])->render())->toContain('TRANSFER NETTO', '3.768.844,22', '3.750.000,00');
    expect(view('admin::invoices.proformas.print', ['proforma' => $dpPf->fresh()])->render())->toContain('Transfer netto', '18.844,22');
    expect(view('admin::quotes.pdf', ['quote' => $this->quote->fresh(['items', 'person.organization'])])->render())->toContain('TRANSFER NETTO', '37.688,44');
    // Revisiting/saving the same choice never applies gross-up twice.
    $same = revisionPayload('invoice', $dp->id, 7500000);
    $this->service->apply('invoice', $dp->id, $same, 1);
    expect((float) $dp->fresh()->grand_total)->toBe(3768844.22)->and(Payment::all()->toArray())->toBe($payments);
});

it('carries gross-up revisions into later settlement creation and keeps proformas outside revenue', function () {
    $dp = revisionInvoice(revisionProforma($this->quote, 'down_payment'), 4000000);
    $input = revisionPayload('invoice', $dp->id, 10000000);
    $input['pph_gross_up'] = 1;
    $this->service->apply('invoice', $dp->id, $input, 1);
    expect($dp->fresh()->status)->toBe('paid')->and($dp->fresh()->cashPayable())->toBe(4000000.0);
    $pf = revisionProforma($this->quote, 'settlement');
    expect($pf->cashPayable())->toBe(6000000.0)->and(Invoice::count())->toBe(1);
    $final = app(FlexibleQuoteBillingService::class)->confirmProformaPayment($pf, ['amount' => 6000000, 'paid_at' => '2026-10-15 09:30:00', 'payment_method' => 'Transfer'], 1);
    expect($final->status)->toBe('paid')->and(round((float) Invoice::sum('pph_amount'), 2))->toBe(50251.26)
        ->and(revisionReport()['financialSummary']['received'])->toBe(10000000.0)->and(revisionReport()['financialSummary']['outstanding'])->toBe(0.0);
});

it('keeps full gross-up cash balances correct when enabling disabling and changing price', function () {
    $invoice = revisionInvoice(revisionProforma($this->quote), 3000000);
    $input = revisionPayload('invoice', $invoice->id, 10000000);
    $input['pph_gross_up'] = 1;
    $this->service->apply('invoice', $invoice->id, $input, 1);
    expect((float) $invoice->fresh()->balance_due)->toBe(7000000.0)->and($invoice->fresh()->status)->toBe('partial');
    $off = revisionPayload('invoice', $invoice->id, 10000000);
    $off['pph_gross_up'] = 0;
    $this->service->apply('invoice', $invoice->id, $off, 1);
    expect((float) $invoice->fresh()->grand_total)->toBe(10000000.0)->and((float) $invoice->fresh()->pph_amount)->toBe(0.0);
    $tooLow = revisionPayload('invoice', $invoice->id, 2990000);
    $tooLow['pph_gross_up'] = 1; // Gross > receipt, but net < receipt: must reject.
    expect(fn () => $this->service->apply('invoice', $invoice->id, $tooLow, 1))->toThrow(ValidationException::class);
    expect((float) $invoice->fresh()->grand_total)->toBe(10000000.0);
});
