<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;
use Webkul\Admin\Http\Controllers\Invoice\ProformaInvoiceController;
use Webkul\Admin\Services\FinanceSalesDashboardService;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\InvoiceCorrectionService;
use Webkul\Admin\Services\InvoiceVoidService;
use Webkul\Admin\Services\PaymentVoidService;
use Webkul\Admin\Services\ProformaFollowUpService;
use Webkul\Admin\Services\ProformaInvoiceService;
use Webkul\Admin\Services\SalesCommissionExportService;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Models\PaymentVoidRecord;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Invoice\Models\WorkOrder;
use Webkul\Invoice\Services\InvoiceService;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Invoice\Services\WorkOrderService;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.proforma_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('proforma_test');
    Schema::clearResolvedInstance('db.schema');
    (require base_path('database/migrations/2026_10_13_150000_create_payment_voids_table.php'))->up();
    $this->travelTo(now()->parse('2026-10-12 12:00:00'));
    DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4));
    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) substr($date, 5, 2));

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
    });
    foreach (['quotes', 'invoices'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->increments('id');
            $table->string($name === 'quotes' ? 'quote_number' : 'invoice_number')->unique();
            foreach (['subject', 'project_code', 'business_unit', 'location', 'payment_term', 'description', 'bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name'] as $column) {
                $table->text($column)->nullable();
            }
            foreach (['discount_percent', 'discount_amount', 'tax_amount', 'adjustment_amount', 'sub_total', 'grand_total'] as $column) {
                $table->decimal($column, 16, 4)->default(0);
            }
            $table->unsignedInteger('person_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->date('event_date')->nullable();
            if ($name === 'quotes') {
                $table->timestamp('expired_at')->nullable();
            }
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            if ($name === 'invoices') {
                $table->unsignedInteger('quote_id')->nullable();
                $table->string('status')->default('unpaid');
                $table->string('event_status')->default('confirm');
                $table->decimal('paid_amount', 16, 4)->default(0);
                $table->decimal('balance_due', 16, 4)->default(0);
                $table->dateTime('issued_at')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->string('billing_type')->nullable();
                $table->string('billing_method')->nullable();
                foreach (['billing_percentage', 'quote_total_snapshot', 'billing_amount', 'remaining_amount_snapshot'] as $column) {
                    $table->decimal($column, 16, 4)->nullable();
                }
                $table->unsignedInteger('dp_invoice_id')->nullable();
                $table->unsignedInteger('billing_created_by')->nullable();
                $table->timestamp('billing_locked_at')->nullable();
            }
            $table->timestamps();
        });
    }
    foreach (['quote_items' => 'quote_id', 'invoice_items' => 'invoice_id'] as $name => $parent) {
        Schema::create($name, function (Blueprint $table) use ($parent) {
            $table->increments('id');
            $table->unsignedInteger($parent);
            $table->unsignedInteger('product_id')->nullable();
            foreach (['sku', 'name', 'description', 'unit', 'coupon_code'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['day', 'quantity', 'equipment_quantity', 'price', 'total', 'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount'] as $column) {
                $table->decimal($column, 16, 4)->default(0);
            }
            $table->timestamps();
        });
    }
    Schema::create('payments', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_id');
        $table->decimal('amount', 16, 4);
        foreach (['payment_method', 'reference_number', 'notes'] as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamp('paid_at');
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamps();
    });
    Schema::create('expenses', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_id');
        $table->decimal('amount', 16, 4);
        $table->dateTime('expense_date');
        $table->string('category')->nullable();
        $table->string('description')->nullable();
        $table->string('reference_number')->nullable();
    });
    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->unsignedInteger('organization_id')->nullable();
    });
    Schema::create('organizations', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
    });
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
    });
    (require base_path('database/migrations/2026_10_12_030000_create_proforma_invoices_table.php'))->up();
    (require base_path('database/migrations/2026_10_12_133000_add_proforma_conversion_workflow.php'))->up();
    (require base_path('database/migrations/2026_10_15_100000_add_pph_gross_up_to_billing_documents.php'))->up();
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_150000_create_financial_period_locks_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_210000_create_work_orders_and_link_delivery_orders.php'))->up();
    (require base_path('database/migrations/2026_10_15_090000_add_commercial_document_revisions.php'))->up();

    $this->quote = Quote::create([
        'quote_number' => 'Q-10M', 'project_code' => 'PROJECT-1', 'subject' => 'Photobooth Event',
        'business_unit' => 'capture_it', 'grand_total' => 10000000, 'sub_total' => 10000000,
        'event_date' => '2026-10-20', 'location' => 'Jakarta', 'description' => 'Event',
        'payment_term' => 'DP 30%, pelunasan H-1 event',
        'bill_to_display_mode' => 'both', 'bill_to_person_name' => 'Customer', 'bill_to_company_name' => 'Company Awal',
        'billing_address' => ['address' => 'Jakarta'], 'shipping_address' => [],
    ]);
    $this->quote->items()->create([
        'name' => 'Photobooth', 'sku' => 'PB', 'day' => 1, 'unit' => 'day', 'quantity' => 2,
        'equipment_quantity' => 1, 'price' => 5000000, 'total' => 10000000,
    ]);
    $this->input = [
        'billing_type' => 'down_payment', 'billing_method' => 'nominal', 'billing_amount' => 3000000,
        'issued_at' => '2026-10-10', 'due_at' => '2026-10-12', 'payment_term' => 'DP 30%, pelunasan H-1 event',
    ];
    $this->receipt = ['amount' => 3000000, 'paid_at' => '2026-10-12 10:00:00', 'payment_method' => 'Transfer', 'reference_number' => 'BANK-001'];
    $this->billing = app(FlexibleQuoteBillingService::class);
    $this->proformas = app(ProformaInvoiceService::class);
});

afterEach(function (): void {
    $this->travelBack();
    DB::purge('proforma_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('requests DP without creating an actual invoice payment or SPK and prevents duplicate billing', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    expect($proforma->proforma_number)->toStartWith('PF 2610-')
        ->and(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0)->and(WorkOrder::count())->toBe(0)
        ->and($this->billing->sumUnbilledForQuoteIds([$this->quote->id]))->toBe(10000000.0);
    $summary = $this->billing->summarize($this->quote);
    expect($summary['active_invoiced'])->toBe(0.0)
        ->and($summary['eligibility'])->toBe(['down_payment' => false, 'full_payment' => false, 'settlement' => false]);
    expect(fn () => $this->proformas->create($this->quote, $this->input))->toThrow(ValidationException::class);
    expect(fn () => $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment'])))->toThrow(ValidationException::class);
    expect(fn () => $this->billing->createFromQuote($this->quote, $this->input))->toThrow(ValidationException::class);
});

it('creates paid DP of 3 million once then a separate unpaid settlement of 7 million with one SPK', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $dp = $this->billing->confirmProformaPayment($proforma, $this->receipt);
    $retry = $this->billing->confirmProformaPayment($proforma, $this->receipt);
    expect($retry->id)->toBe($dp->id)->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1)
        ->and((float) $dp->grand_total)->toBe(3000000.0)->and((float) $dp->paid_amount)->toBe(3000000.0)
        ->and((float) $dp->balance_due)->toBe(0.0)->and($dp->status)->toBe('paid')
        ->and($dp->payments->first()->reference_number)->toBe('BANK-001')
        ->and($dp->payments->first()->paid_at->format('Y-m-d H:i:s'))->toBe('2026-10-12 10:00:00')
        ->and($proforma->fresh()->invoice_id)->toBe($dp->id);
    $this->travelTo(now()->parse('2026-10-19 10:00:00'));
    $settlement = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement', 'issued_at' => '2026-10-19', 'due_at' => '2026-10-19'])));
    expect((float) $settlement->grand_total)->toBe(7000000.0)->and($settlement->status)->toBe('unpaid')
        ->and((int) $settlement->dp_invoice_id)->toBe($dp->id)->and((float) Invoice::sum('grand_total'))->toBe(10000000.0)
        ->and((float) Payment::sum('amount'))->toBe(3000000.0)->and($settlement->invoice_number)->not->toBe($dp->invoice_number);
    $workOrders = app(WorkOrderService::class);
    $spk = $workOrders->createFromInvoice($settlement);
    expect($workOrders->createFromInvoice($dp)->id)->toBe($spk->id)
        ->and($workOrders->findForInvoice($settlement)->id)->toBe($spk->id)
        ->and($spk->invoice_id)->toBe($dp->id)->and(WorkOrder::count())->toBe(1)->and($spk->items)->toHaveCount(1)
        ->and($this->billing->sumUnbilledForQuoteIds([$this->quote->id]))->toBe(0.0);
    expect(fn () => $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement'])))->toThrow(ValidationException::class);
    app(PaymentService::class)->addPayment($settlement, ['amount' => 7000000]);
    expect((float) Payment::sum('amount'))->toBe(10000000.0)->and($settlement->fresh()->status)->toBe('paid');
});

it('preserves agreed totals company and equipment even after the quote changes', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->quote->update(['grand_total' => 20000000, 'bill_to_company_name' => 'Changed Company']);
    $this->quote->items()->update(['quantity' => 99, 'equipment_quantity' => 99, 'name' => 'Changed Product']);
    $dp = $this->billing->confirmProformaPayment($proforma, $this->receipt);
    expect($dp->bill_to_company_name)->toBe('Company Awal')->and($dp->items->first()->name)->toBe('Photobooth')
        ->and((float) $dp->items->first()->equipment_quantity)->toBe(1.0);
    $this->travelTo(now()->parse('2026-10-23 10:00:00'));
    $settlement = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, [
        'billing_type' => 'settlement', 'issued_at' => '2026-10-23', 'due_at' => '2026-11-03', 'payment_term' => 'Lunas 14 hari setelah event',
    ])));
    expect((float) $settlement->grand_total)->toBe(7000000.0)->and($settlement->bill_to_company_name)->toBe('Company Awal')
        ->and($settlement->items->first()->name)->toBe('Photobooth')
        ->and($settlement->due_at->format('Y-m-d'))->toBe('2026-11-03');
    expect($this->billing->sumUnbilledForQuoteIds([$this->quote->id]))->toBe(0.0);
});

it('allows no DP with full invoice and SPK before payment for either TOP', function (string $dueAt) {
    $invoice = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, [
        'billing_type' => 'full_payment', 'due_at' => $dueAt,
    ])));
    expect($invoice->status)->toBe('unpaid')->and((float) $invoice->grand_total)->toBe(10000000.0)
        ->and($invoice->due_at->format('Y-m-d'))->toBe($dueAt)
        ->and(ProformaInvoice::count())->toBe(1)->and(Payment::count())->toBe(0)
        ->and(app(WorkOrderService::class)->createFromInvoice($invoice)->invoice_id)->toBe($invoice->id);
})->with(['2026-10-19', '2026-11-03']);

it('rejects invalid or not yet received payments without financial side effects', function (array $overrides) {
    $proforma = $this->proformas->create($this->quote, $this->input);
    expect(fn () => $this->billing->confirmProformaPayment($proforma, array_replace($this->receipt, $overrides)))->toThrow(ValidationException::class);
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0)->and($proforma->fresh()->status)->toBe('issued');
})->with([[['amount' => 0]], [['amount' => 2000000]], [['amount' => 4000000]], [['paid_at' => '2026-10-13 10:00:00']], [['paid_at' => '2026-10-09 10:00:00']], [['payment_method' => '']]]);

it('rolls back the invoice if recording the receipt fails', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    DB::unprepared("CREATE TRIGGER fail_payment BEFORE INSERT ON payments BEGIN SELECT RAISE(ABORT, 'receipt failed'); END");
    expect(fn () => $this->billing->confirmProformaPayment($proforma, $this->receipt))->toThrow(QueryException::class);
    expect(Invoice::count())->toBe(0)->and(DB::table('invoice_items')->count())->toBe(0)
        ->and(Payment::count())->toBe(0)->and($proforma->fresh()->status)->toBe('issued');
});

it('cancels an unpaid proforma with audit reason and permits a revised agreement', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->proformas->cancel($proforma, 'Perjanjian berubah menjadi tanpa DP', 7);
    expect($proforma->fresh()->cancelled_by)->toBe(7)->and($proforma->fresh()->cancellation_reason)->toBe('Perjanjian berubah menjadi tanpa DP');
    expect(fn () => $this->billing->confirmProformaPayment($proforma, $this->receipt))->toThrow(ValidationException::class);
    $invoice = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment'])));
    expect((float) $invoice->grand_total)->toBe(10000000.0)->and(Payment::count())->toBe(0);
});

it('cannot cancel a converted proforma or create settlement without a paid DP', function () {
    expect(fn () => $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement'])))->toThrow(ValidationException::class);
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->billing->confirmProformaPayment($proforma, $this->receipt);
    expect(fn () => $this->proformas->cancel($proforma, 'Already paid'))->toThrow(ValidationException::class);
});

it('renders the printable proforma with frozen company and TOP', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $html = view('admin::invoices.proformas.print', compact('proforma'))->render();
    expect($html)->toContain('PROFORMA INVOICE', 'Company Awal', 'Sub Total', 'Discount', 'GRAND TOTAL', '3.000.000,00', 'DP 30%, pelunasan H-1 event')
        ->not->toContain('7.000.000,00', 'Sisa untuk invoice pelunasan');
});

it('keeps a closed financial period protected during DP conversion', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    DB::table('financial_period_locks')->insert(['period' => '2026-10', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-31']);
    expect(fn () => $this->billing->confirmProformaPayment($proforma, $this->receipt))->toThrow(ValidationException::class);
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0)->and($proforma->fresh()->status)->toBe('issued');
});

it('rejects zero total DP and a due date before issue and requires TOP', function (array $overrides) {
    expect(fn () => $this->proformas->create($this->quote, array_replace($this->input, $overrides)))->toThrow(ValidationException::class);
    expect(ProformaInvoice::count())->toBe(0);
})->with([[['billing_amount' => 0]], [['billing_amount' => 10000000]], [['billing_amount' => 11000000]], [['due_at' => '2026-10-01']], [['payment_term' => '']]]);

it('can roll the new migration down and up without touching existing quotes or invoices', function () {
    $migration = require base_path('database/migrations/2026_10_12_030000_create_proforma_invoices_table.php');
    $migration->down();
    expect(Schema::hasTable('proforma_invoices'))->toBeFalse()->and(Quote::count())->toBe(1);
    $migration->up();
    expect(Schema::hasTable('proforma_invoices'))->toBeTrue()->and(Invoice::count())->toBe(0);
});

it('checks generate and payment permissions on direct requests', function () {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['name' => 'Superadministrator', 'permission_type' => 'custom', 'permissions' => ['invoices', 'invoices.view']]);
    $user = (new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $this->getJson(route('admin.invoices.billing.create', ['quote_id' => $this->quote->id]))->assertUnauthorized();
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, ['quote_id' => $this->quote->id]))->assertUnauthorized();
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertUnauthorized();
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
    $role->permissions = ['invoices.payment', 'invoices.view', 'invoices.generate'];
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertRedirect(route('admin.proformas.show', $proforma->id));
    expect(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1);
});

it('routes every generate form choice to proforma before an invoice exists', function () {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices.generate', 'invoices.view']]);
    $user = (new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $this->postJson(route('admin.invoices.generate', $this->quote->id))->assertRedirect(route('admin.invoices.billing.create', ['quote_id' => $this->quote->id]));
    $page = $this->get(route('admin.invoices.billing.create', ['quote_id' => $this->quote->id]))
        ->assertOk()
        ->assertSee('Buat Proforma dari Penawaran');
    // The web UI middleware injects content and replaces the original View object.
    expect($page->getContent())->toMatch('/<option\b[^>]*value="'.$this->quote->id.'"[^>]*\bselected[^>]*>/s');
    expect(Invoice::count())->toBe(0)->and(ProformaInvoice::count())->toBe(0);
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, [
        'quote_id' => $this->quote->id, 'billing_method' => 'percentage', 'billing_percentage' => 30, 'billing_amount' => null,
    ]))->assertRedirect(route('admin.proformas.show', 1));
    expect(Invoice::count())->toBe(0)->and((float) ProformaInvoice::firstOrFail()->amount)->toBe(3000000.0);
    $this->getJson(route('admin.invoices.billing.summary', $this->quote->id))->assertOk()->assertJsonPath('data.eligibility.down_payment', false);
    $this->proformas->cancel(ProformaInvoice::firstOrFail(), 'Tanpa DP sesuai kesepakatan baru');
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, [
        'quote_id' => $this->quote->id, 'billing_type' => 'full_payment',
    ]))->assertRedirect(route('admin.proformas.show', 2));
    expect(Invoice::count())->toBe(0)->and((float) ProformaInvoice::findOrFail(2)->amount)->toBe(10000000.0)->and(Payment::count())->toBe(0);
});

describe('billing with a persisted browser session', function () {
    beforeEach(function () {
        $this->withoutMiddleware(CanInstall::class);
        // Unlike actingAs/withSession, only the cookie survives into each request.
        // Enable CSRF checks too, as they are normally skipped in unit tests.
        $this->app->instance('env', 'local');
        $this->sessionDirectory = sys_get_temp_dir().'/crm-proforma-session-'.Str::uuid();
        File::makeDirectory($this->sessionDirectory, 0755, true);
        config([
            'app.key' => str_repeat('a', 32), 'app.debug' => true,
            'session.driver' => 'file', 'session.files' => $this->sessionDirectory,
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('permission_type');
            $table->text('permissions')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->boolean('status')->default(true);
            $table->string('view_permission')->default('global');
        });
        DB::table('roles')->insert([
            'id' => 1, 'name' => 'Admin Sales', 'permission_type' => 'custom',
            'permissions' => json_encode(['invoices', 'invoices.view', 'invoices.generate', 'invoices.payment']),
        ]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Sales Tester', 'role_id' => 1, 'status' => true]);

        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $loginKey = auth()->guard('user')->getName();
        $session = app('session')->driver();
        $session->start();
        $session->put($loginKey, 1);
        $this->csrfToken = $session->token();
        $session->save();
        $this->withCookie($session->getName(), $session->getId());

        $this->browserRequest = function (string $method, string $url, array $data = [], array $headers = []) {
            app('auth')->forgetGuards();
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');

            return $method === 'GET' ? $this->get($url, $headers) : $this->post($url, $data, $headers);
        };
    });

    afterEach(function () {
        File::deleteDirectory($this->sessionDirectory);
    });

    it('opens both entry points and converts the saved proforma to one invoice', function (string $type, string $conversion): void {
        ($this->browserRequest)('GET', route('admin.invoices.billing.create'))
            ->assertOk()->assertSee('Buat Proforma dari Penawaran');
        $page = ($this->browserRequest)('GET', route('admin.invoices.billing.create', ['quote_id' => $this->quote->id]))
            ->assertOk()->assertSee('Buat Proforma dari Penawaran');
        expect($page->getContent())->toMatch('/<option\b[^>]*value="'.$this->quote->id.'"[^>]*\bselected[^>]*>/s');
        ($this->browserRequest)('GET', route('admin.invoices.billing.summary', $this->quote->id), [], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.eligibility.full_payment', true);

        ($this->browserRequest)('POST', route('admin.invoices.billing.store'), array_replace($this->input, [
            '_token' => $this->csrfToken, 'quote_id' => $this->quote->id, 'billing_type' => $type,
        ]))->assertRedirect(route('admin.proformas.show', 1));
        $proforma = ProformaInvoice::firstOrFail();
        expect(Invoice::count())->toBe(0)->and($proforma->created_by)->toBe(1);

        ($this->browserRequest)('GET', route('admin.proformas.show', $proforma->id))
            ->assertOk()->assertSee('Event sudah deal')->assertSee('Pembayaran diterima');
        $url = route('admin.proformas.'.$conversion, $proforma->id);
        $payload = $conversion === 'approve'
            ? ['event_confirmed' => 1, 'confirmation_note' => 'Customer sudah menyetujui event']
            : array_replace($this->receipt, ['amount' => $proforma->amount]);
        $payload['_token'] = $this->csrfToken;
        ($this->browserRequest)('POST', $url, $payload)->assertRedirect(route('admin.proformas.show', $proforma->id));

        $invoice = Invoice::firstOrFail();
        expect($proforma->fresh()->status)->toBe('converted')
            ->and($proforma->fresh()->invoice_id)->toBe($invoice->id)
            ->and($proforma->fresh()->converted_by)->toBe(1)
            ->and($invoice->status)->toBe($conversion === 'approve' ? 'unpaid' : 'paid')
            ->and((float) $invoice->grand_total)->toBe((float) $proforma->amount)
            ->and(Payment::count())->toBe($conversion === 'approve' ? 0 : 1);
        ($this->browserRequest)('GET', route('admin.proformas.show', $proforma->id))
            ->assertOk()->assertSee('Buka Invoice')->assertSee($invoice->invoice_number);
        ($this->browserRequest)('POST', $url, $payload)->assertRedirect(route('admin.proformas.show', $proforma->id));
        expect(Invoice::count())->toBe(1)->and(Payment::count())->toBe($conversion === 'approve' ? 0 : 1);
    })->with([
        'DP event confirmed' => ['down_payment', 'approve'],
        'Full event confirmed' => ['full_payment', 'approve'],
        'DP payment received' => ['down_payment', 'payment'],
        'Full payment received' => ['full_payment', 'payment'],
    ]);

    it('rejects a billing submission without the session CSRF token', function () {
        ($this->browserRequest)('POST', route('admin.invoices.billing.store'), array_replace($this->input, [
            'quote_id' => $this->quote->id,
        ]), ['Accept' => 'application/json'])->assertStatus(419);
        expect(ProformaInvoice::count())->toBe(0)->and(Invoice::count())->toBe(0);
    });
});

it('lets Admin Sales move a deal to invoice without fabricating a payment', function (string $type) {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['name' => 'Admin Sales', 'permission_type' => 'custom', 'permissions' => ['invoices.generate', 'invoices.view']]);
    $user = (new User)->forceFill(['id' => 17, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $proforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => $type]));
    expect($proforma)->toBeInstanceOf(ProformaInvoice::class)->and(Invoice::count())->toBe(0)->and(WorkOrder::count())->toBe(0);
    $this->postJson(route('admin.proformas.approve', $proforma->id), [])->assertUnprocessable()->assertJsonValidationErrors('event_confirmed');
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertUnauthorized();
    $this->postJson(route('admin.proformas.approve', $proforma->id), [
        'event_confirmed' => 1, 'confirmation_note' => 'Customer sudah menyetujui event',
        'status' => 'paid', 'amount' => 10000000, 'paid_at' => '2026-10-12 10:00:00',
    ])->assertRedirect(route('admin.proformas.show', $proforma->id));
    $invoice = Invoice::firstOrFail();
    expect($invoice->status)->toBe('unpaid')->and((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) $invoice->balance_due)->toBe((float) $proforma->amount)->and(Payment::count())->toBe(0)
        ->and($proforma->fresh()->converted_by)->toBe(17)->and($proforma->fresh()->conversion_reason)->toBe('event_confirmed')
        ->and($proforma->fresh()->conversion_note)->toBe('Customer sudah menyetujui event')
        ->and(app(WorkOrderService::class)->createFromInvoice($invoice)->invoice_id)->toBe($invoice->id);
    $this->postJson(route('admin.proformas.approve', $proforma->id), ['event_confirmed' => 1])->assertRedirect();
    expect(Invoice::count())->toBe(1)->and(Payment::count())->toBe(0);
    expect(fn () => $this->billing->confirmProformaPayment($proforma, $this->receipt))->toThrow(ValidationException::class);
})->with(['down_payment', 'full_payment']);

it('requires permission to move a proforma even when payment permission is granted', function () {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['name' => 'Admin Sales', 'permission_type' => 'custom', 'permissions' => ['invoices.payment', 'invoices.view']]);
    $this->actingAs((new User)->forceFill(['id' => 17, 'status' => 1])->setRelation('role', $role), 'user');
    $proforma = $this->billing->createFromQuote($this->quote, $this->input);
    $this->postJson(route('admin.proformas.approve', $proforma->id), ['event_confirmed' => 1])->assertUnauthorized();
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertForbidden();
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
});

it('keeps settlement in proforma until Admin Sales moves it and retains the same SPK', function () {
    $dpProforma = $this->billing->createFromQuote($this->quote, $this->input);
    $dp = $this->billing->approveProforma($dpProforma, 17);
    expect(fn () => $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement'])))->toThrow(ValidationException::class);
    app(PaymentService::class)->addPayment($dp, $this->receipt);
    $spk = app(WorkOrderService::class)->createFromInvoice($dp);
    $settlementProforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement']));
    expect((float) $settlementProforma->amount)->toBe(7000000.0)
        ->and($settlementProforma->billing_type)->toBe('settlement')->and(Invoice::count())->toBe(1)
        ->and($this->billing->summarize($this->quote)['eligibility']['settlement'])->toBeFalse();
    expect(fn () => $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement'])))->toThrow(ValidationException::class);
    $settlement = $this->billing->approveProforma($settlementProforma, 17);
    expect($settlement->status)->toBe('unpaid')->and((float) Invoice::sum('grand_total'))->toBe(10000000.0)
        ->and((float) Payment::sum('amount'))->toBe(3000000.0)
        ->and(app(WorkOrderService::class)->createFromInvoice($settlement)->id)->toBe($spk->id);
});

it('also supports a full proforma that has actually been paid', function () {
    $proforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment']));
    $invoice = $this->billing->confirmProformaPayment($proforma, array_replace($this->receipt, ['amount' => 10000000]), 17);
    expect($invoice->status)->toBe('paid')->and((float) Payment::sum('amount'))->toBe(10000000.0)
        ->and($proforma->fresh()->conversion_reason)->toBe('payment_received')->and($proforma->fresh()->converted_by)->toBe(17);
});

it('does not allow the legacy generation service to bypass proforma', function () {
    $proforma = app(InvoiceService::class)->createFromQuote($this->quote);
    expect($proforma)->toBeInstanceOf(ProformaInvoice::class)
        ->and($proforma->billing_type)->toBe('full_payment')->and(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
});

it('preserves full quote snapshots and does not claim a receipt on the printed approved proforma', function () {
    $proforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment']));
    $this->quote->update(['grand_total' => 20000000, 'bill_to_company_name' => 'Changed Company']);
    $invoice = $this->billing->approveProforma($proforma, 17);
    expect((float) $invoice->grand_total)->toBe(10000000.0)->and($invoice->bill_to_company_name)->toBe('Company Awal');
    $html = view('admin::invoices.proformas.print', ['proforma' => $proforma->fresh()])->render();
    expect($html)->toContain('SUDAH DIPINDAHKAN KE INVOICE', 'Penuh / Tanpa DP')->not->toContain('DP TELAH DITERIMA');
});

it('upgrades earlier DP proformas without losing their type or conversion history', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->billing->confirmProformaPayment($proforma, $this->receipt);
    $migration = require base_path('database/migrations/2026_10_12_133000_add_proforma_conversion_workflow.php');
    $migration->down();
    $migration->up();
    expect($proforma->fresh()->billing_type)->toBe('down_payment')
        ->and($proforma->fresh()->conversion_reason)->toBe('payment_received')
        ->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1);
});

function proformaFinancialData(array $filters = []): array
{
    return app(FinancialReportController::class)->index(Request::create('/', 'GET', array_merge([
        'year' => 2026, 'month' => 10,
    ], $filters)))->getData();
}

function proformaFinancialCsv(array $filters = []): string
{
    $response = app(FinancialReportController::class)->export(Request::create('/', 'GET', array_merge([
        'year' => 2026, 'month' => 10,
    ], $filters)));

    return TestResponse::fromBaseResponse($response)->streamedContent();
}

/** Legacy DP entered before the proforma workflow, with a completed event. */
function invoiceVoidFixture($test): Invoice
{
    (require base_path('database/migrations/2026_10_07_000000_create_crm_data_corrections_table.php'))->up();
    Schema::table('expenses', fn (Blueprint $table) => $table->timestamps());
    Schema::create('purchase_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_id');
    });
    Schema::create('delivery_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_id');
        $table->unsignedInteger('work_order_id');
        $table->string('delivery_order_number');
        $table->string('status');
    });
    Schema::create('delivery_order_inventory_allocations', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('delivery_order_id');
        $table->string('status');
    });
    Schema::create('inventory_assets', function (Blueprint $table) {
        $table->increments('id');
        $table->string('status');
    });
    Schema::create('inventory_stock_movements', function (Blueprint $table) {
        $table->increments('id');
        $table->string('type');
    });
    DB::table('users')->insert(['id' => 7, 'name' => 'Finance correction actor']);
    DB::table('quotes')->where('id', $test->quote->id)->update(['grand_total' => 7500000, 'event_date' => '2026-10-08']);
    $invoice = Invoice::create([
        'invoice_number' => 'INV 2610-0019', 'quote_id' => $test->quote->id, 'project_code' => 'PRJ-2026-00042',
        'subject' => 'Completed event', 'business_unit' => 'capture_it', 'event_date' => '2026-10-08',
        'grand_total' => 3750000, 'balance_due' => 3750000, 'paid_amount' => 0,
        'status' => 'unpaid', 'event_status' => 'confirm', 'issued_at' => '2026-10-08',
    ]);
    $invoice->items()->create(['name' => 'Photobooth', 'unit' => 'day', 'quantity' => 1, 'price' => 3750000, 'total' => 3750000]);
    DB::table('payments')->insert(['id' => 6, 'invoice_id' => $invoice->id, 'amount' => 3750000, 'paid_at' => '2026-09-30 16:22:00', 'created_by' => 7]);
    DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'paid', 'paid_amount' => 3750000, 'balance_due' => 0, 'billing_type' => 'down_payment']);
    $invoice->refresh();
    $spk = app(WorkOrderService::class)->createFromInvoice($invoice);
    $spk->update(['status' => 'completed']);
    foreach ([1, 2] as $id) {
        DB::table('delivery_orders')->insert(['id' => $id, 'invoice_id' => $invoice->id, 'work_order_id' => $spk->id, 'delivery_order_number' => 'DO-'.$id, 'status' => 'returned']);
        DB::table('delivery_order_inventory_allocations')->insert(['delivery_order_id' => $id, 'status' => 'returned']);
    }
    DB::table('inventory_assets')->insert(['status' => 'available']);
    DB::table('inventory_stock_movements')->insert([['type' => 'out'], ['type' => 'return']]);

    return $invoice;
}

function invoiceVoidProtectedHistory(): array
{
    return collect(['quotes', 'quote_items', 'invoice_items', 'payments', 'work_orders', 'work_order_items', 'delivery_orders', 'delivery_order_inventory_allocations', 'inventory_assets', 'inventory_stock_movements'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
}

function paymentVoidLogin($test, array $permissions = ['invoices', 'invoices.view', 'invoices.payment', 'invoices.payment-void']): void
{
    $test->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => $permissions]);
    $test->actingAs((new User)->forceFill(['id' => 7, 'name' => 'Finance', 'status' => 1])->setRelation('role', $role), 'user');
}

describe('individual payment void', function () {
    it('reopens the receipt balance and preserves all documents including active operations', function () {
        $invoice = invoiceVoidFixture($this);
        DB::table('work_orders')->update(['status' => 'released']);
        DB::table('delivery_orders')->update(['status' => 'out']);
        DB::table('delivery_order_inventory_allocations')->update(['status' => 'out']);
        $before = invoiceVoidProtectedHistory();
        $service = app(PaymentVoidService::class);
        $preview = $service->preview($invoice->id, 6);
        expect($preview['blockers'])->toBeEmpty()->and(invoiceVoidProtectedHistory())->toBe($before)
            ->and(PaymentVoidRecord::count())->toBe(0)->and($invoice->fresh()->status)->toBe('paid');
        $result = $service->apply($invoice->id, 6, $preview['fingerprint'], 'Pencatatan transfer duplikat', 7, true);
        expect($result['status'])->toBe('unpaid')->and($result['paid_amount'])->toBe(0.0)
            ->and($result['balance_due'])->toBe(3750000.0)->and($invoice->fresh()->status)->toBe('unpaid')
            ->and((float) $invoice->fresh()->grand_total)->toBe(3750000.0)
            ->and($invoice->fresh()->event_status)->toBe('confirm')->and(invoiceVoidProtectedHistory())->toBe($before)
            ->and(Payment::count())->toBe(1)->and(Payment::notVoided()->count())->toBe(0);
        $audit = PaymentVoidRecord::findOrFail($result['audit_id']);
        expect($audit->actor_id)->toBe(7)->and($audit->snapshot['invoice']['status'])->toBe('paid')
            ->and($audit->result['amount_voided'])->toEqual(3750000);
        expect(fn () => $audit->delete())->toThrow(LogicException::class);
        expect(fn () => $audit->save())->toThrow(LogicException::class);
        expect(fn () => Payment::findOrFail(6)->update(['amount' => 1]))->toThrow(ValidationException::class);
        expect(fn () => Payment::findOrFail(6)->delete())->toThrow(ValidationException::class);
        expect(fn () => (require base_path('database/migrations/2026_10_13_150000_create_payment_voids_table.php'))->down())->toThrow(RuntimeException::class);
    });

    it('retains valid partial receipts and supports a replacement payment and multiple corrections on one invoice', function () {
        $invoice = invoiceVoidFixture($this);
        DB::table('payments')->where('id', 6)->update(['amount' => 3000000]);
        DB::table('payments')->insert(['id' => 7, 'invoice_id' => $invoice->id, 'amount' => 750000, 'paid_at' => '2026-10-09']);
        $service = app(PaymentVoidService::class);
        $preview = $service->preview($invoice->id, 6);
        $service->apply($invoice->id, 6, $preview['fingerprint'], 'Nominal pembayaran salah input', 7, true);
        expect($invoice->fresh()->status)->toBe('partial')->and((float) $invoice->fresh()->paid_amount)->toBe(750000.0)
            ->and((float) $invoice->fresh()->balance_due)->toBe(3000000.0);
        app(PaymentService::class)->addPayment($invoice->fresh(), ['amount' => 2000000, 'paid_at' => '2026-10-12']);
        expect((float) $invoice->fresh()->paid_amount)->toBe(2750000.0)->and((float) $invoice->fresh()->balance_due)->toBe(1000000.0);
        $service->apply($invoice->id, 7, $service->preview($invoice->id, 7)['fingerprint'], 'Pembayaran kedua ternyata duplikat', 7, true);
        expect((float) $invoice->fresh()->paid_amount)->toBe(2000000.0)->and(PaymentVoidRecord::count())->toBe(2);
        app(PaymentService::class)->addPayment($invoice->fresh(), ['amount' => 1750000, 'paid_at' => '2026-10-12']);
        expect($invoice->fresh()->status)->toBe('paid')->and((float) $invoice->fresh()->paid_amount)->toBe(3750000.0)
            ->and(Payment::count())->toBe(4)->and(Payment::notVoided()->count())->toBe(2);
        expect(fn () => $service->apply($invoice->id, 6, $preview['fingerprint'], 'Percobaan berulang', 7, true))->toThrow(ValidationException::class);
        $retry = $service->preview($invoice->id, 6);
        expect($retry['blockers'])->not->toBeEmpty();
        expect(fn () => $service->apply($invoice->id, 6, $retry['fingerprint'], 'Percobaan berulang', 7, true))->toThrow(ValidationException::class);
        expect(PaymentVoidRecord::count())->toBe(2);
    });

    it('removes prior month cash from reports while keeping the export limited to issued invoices', function () {
        $invoice = invoiceVoidFixture($this);
        DB::table('payments')->where('id', 6)->update(['reference_number' => 'WRONG-RECEIPT-REF']);
        $other = Invoice::create(['invoice_number' => 'OTHER-INV', 'grand_total' => 1000, 'balance_due' => 1000, 'issued_at' => '2026-10-01', 'event_status' => 'confirm']);
        app(PaymentService::class)->addPayment($other, ['amount' => 500, 'paid_at' => '2026-09-29', 'reference_number' => 'VALID-RECEIPT']);
        $service = app(PaymentVoidService::class);
        $service->apply($invoice->id, 6, $service->preview($invoice->id, 6)['fingerprint'], 'Pembayaran salah catat September', 7, true);
        $september = proformaFinancialData(['month' => 9]);
        expect($september['financialSummary']['received'])->toBe(500.0)
            ->and($september['cashTransactions']->pluck('reference')->all())->toBe(['VALID-RECEIPT'])
            ->and(proformaFinancialCsv(['month' => 9]))->toContain('No Invoice')
            ->not->toContain('OTHER-INV', $invoice->invoice_number, 'VALID-RECEIPT', 'WRONG-RECEIPT-REF');
        $report = proformaFinancialData();
        expect($report['financialSummary']['invoiced'])->toBe(3751000.0)
            ->and($report['financialSummary']['outstanding'])->toBe(3750500.0)
            ->and($report['invoiceStats']['total'])->toBe(2)
            ->and($report['projectPerformance']->firstWhere('project_code', $invoice->project_code)['received'])->toBe(0.0)
            ->and($report['topProducts']->sum('received_allocated'))->toBe(0.0)
            ->and(proformaFinancialCsv())->toContain($invoice->invoice_number)->not->toContain('WRONG-RECEIPT-REF');
        expect(proformaFinancialData(['month' => 'all'])['financialSummary']['received'])->toBe(500.0);
        $dashboard = app(FinanceSalesDashboardService::class)->exportRows([])->firstWhere('id', $invoice->id);
        expect($dashboard['paid'])->toBe(0.0)->and($dashboard['outstanding'])->toBe(3750000.0);
        expect(app(FinanceSalesDashboardService::class)->exportRows(['focus' => 'unpaid'])->pluck('id')->all())->toContain($invoice->id);
        expect(app(FinanceSalesDashboardService::class)->exportRows(['focus' => 'partial'])->pluck('id')->all())->not->toContain($invoice->id);
    });

    it('rejects locked periods and inconsistent balances without changes', function (string $case) {
        $invoice = invoiceVoidFixture($this);
        if ($case === 'mismatch') {
            DB::table('invoices')->where('id', $invoice->id)->update(['paid_amount' => 1]);
        } else {
            DB::table('financial_period_locks')->insert(['period' => '2026-'.$case, 'starts_at' => '2026-'.$case.'-01', 'ends_at' => '2026-'.$case.'-30']);
        }
        $before = invoiceVoidProtectedHistory();
        $service = app(PaymentVoidService::class);
        $preview = $service->preview($invoice->id, 6);
        expect($preview['blockers'])->not->toBeEmpty();
        expect(fn () => $service->apply($invoice->id, 6, $preview['fingerprint'], 'Pembayaran salah input', 7, true))->toThrow(ValidationException::class);
        expect(PaymentVoidRecord::count())->toBe(0)->and(invoiceVoidProtectedHistory())->toBe($before);
    })->with(['09', '10', 'mismatch']);

    it('requires a valid preview reason actor and explicit correction acknowledgement', function (string $case) {
        $invoice = invoiceVoidFixture($this);
        $service = app(PaymentVoidService::class);
        $preview = $service->preview($invoice->id, 6);
        if ($case === 'stale') {
            DB::table('payments')->where('id', 6)->update(['reference_number' => 'CHANGED']);
        }
        expect(fn () => $service->apply($invoice->id, 6,
            $case === 'fingerprint' ? 'wrong' : $preview['fingerprint'],
            $case === 'reason' ? ' ' : 'Pembayaran salah input', $case === 'actor' ? 9999 : 7, $case !== 'acknowledgement'
        ))->toThrow(ValidationException::class);
        expect(PaymentVoidRecord::count())->toBe(0)->and($invoice->fresh()->status)->toBe('paid');
    })->with(['stale', 'fingerprint', 'reason', 'actor', 'acknowledgement']);

    it('rolls back audit if recalculating the invoice fails', function () {
        $invoice = invoiceVoidFixture($this);
        $before = invoiceVoidProtectedHistory();
        DB::unprepared("CREATE TRIGGER fail_receipt_void BEFORE UPDATE ON invoices BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        $service = app(PaymentVoidService::class);
        expect(fn () => $service->apply($invoice->id, 6, $service->preview($invoice->id, 6)['fingerprint'], 'Pembayaran salah input', 7, true))->toThrow(QueryException::class);
        expect(PaymentVoidRecord::count())->toBe(0)->and($invoice->fresh()->status)->toBe('paid')
            ->and(invoiceVoidProtectedHistory())->toBe($before)->and(Payment::notVoided()->count())->toBe(1);
    });

    it('offers a protected preview and writes the authenticated actor instead of a submitted actor', function () {
        $invoice = invoiceVoidFixture($this);
        paymentVoidLogin($this);
        $url = route('admin.invoices.payments.void.preview', [$invoice->id, 6]);
        $this->get(route('admin.invoices.show', $invoice->id))->assertOk()->assertSee($url, false);
        $page = $this->get($url)->assertOk()->assertSee('Void Pembayaran')->assertSee('UNPAID')->assertSee('Total Invoiced tetap');
        preg_match('/name="expected" value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        expect($matches)->toHaveCount(2);
        $preview = ['fingerprint' => $matches[1]];
        expect(PaymentVoidRecord::count())->toBe(0);
        $post = route('admin.invoices.payments.void.store', [$invoice->id, 6]);
        $this->postJson($post, ['expected' => $preview['fingerprint']])->assertUnprocessable()->assertJsonValidationErrors(['reason', 'acknowledge_error']);
        $this->post($post, ['expected' => $preview['fingerprint'], 'reason' => 'Pembayaran tercatat dua kali', 'acknowledge_error' => 1, 'actor_id' => 999])
            ->assertRedirect(route('admin.invoices.show', $invoice->id).'#payment-history');
        expect(PaymentVoidRecord::first()->actor_id)->toBe(7);
        $this->get(route('admin.invoices.show', $invoice->id))->assertOk()->assertSee('VOID · tidak dihitung')
            ->assertSee('Pembayaran tercatat dua kali')->assertDontSee($url, false);
    });

    it('denies an add-payment-only account and a payment from another invoice', function () {
        $invoice = invoiceVoidFixture($this);
        paymentVoidLogin($this, ['invoices', 'invoices.view', 'invoices.payment']);
        $url = route('admin.invoices.payments.void.preview', [$invoice->id, 6]);
        $this->get(route('admin.invoices.show', $invoice->id))->assertOk()->assertDontSee($url, false);
        $this->getJson($url)->assertUnauthorized();
        $this->postJson(route('admin.invoices.payments.void.store', [$invoice->id, 6]))->assertUnauthorized();
        paymentVoidLogin($this);
        $other = Invoice::create(['invoice_number' => 'OTHER-INV', 'status' => 'unpaid']);
        $this->getJson(route('admin.invoices.payments.void.preview', [$other->id, 6]))->assertNotFound();
        $this->postJson(route('admin.invoices.payments.void.store', [$other->id, 6]), [
            'expected' => str_repeat('a', 64), 'reason' => 'Pembayaran salah invoice', 'acknowledge_error' => 1,
        ])->assertNotFound();
        expect(PaymentVoidRecord::count())->toBe(0)->and($invoice->fresh()->status)->toBe('paid');
    });

    it('requires a CSRF token and permits only POST to apply the correction', function () {
        $invoice = invoiceVoidFixture($this);
        paymentVoidLogin($this);
        $this->app->instance('env', 'local');
        $post = route('admin.invoices.payments.void.store', [$invoice->id, 6]);
        $preview = app(PaymentVoidService::class)->preview($invoice->id, 6);
        $data = ['expected' => $preview['fingerprint'], 'reason' => 'Pembayaran salah catat', 'acknowledge_error' => 1];
        $this->postJson($post, $data)->assertStatus(419);
        expect(PaymentVoidRecord::count())->toBe(0)->and($invoice->fresh()->status)->toBe('paid')
            ->and(Payment::notVoided()->count())->toBe(1);
        $this->withSession(['_token' => 'correction-csrf'])->post($post, ['_token' => 'correction-csrf'] + $data)->assertRedirect();
        expect(PaymentVoidRecord::count())->toBe(1)->and($invoice->fresh()->status)->toBe('unpaid');
    });

    it('keeps payment corrections when the invoice is later voided and blocks destructive legacy correction', function () {
        $invoice = invoiceVoidFixture($this);
        $service = app(PaymentVoidService::class);
        $service->apply($invoice->id, 6, $service->preview($invoice->id, 6)['fingerprint'], 'Pembayaran salah input', 7, true);
        expect(app(InvoiceCorrectionService::class)->preview($invoice->invoice_number)['dependencies']['payment_voids'])->toBe(1);
        $whole = app(InvoiceVoidService::class);
        $preview = $whole->preview($invoice->invoice_number);
        expect($preview['blockers'])->toBeEmpty()->and($preview['payment_total_to_void'])->toBe(0.0);
        $whole->apply($invoice->invoice_number, $preview['fingerprint'], 'Nominal quote juga salah, invoice diganti', 7, true);
        expect($invoice->fresh()->status)->toBe('void')->and(PaymentVoidRecord::count())->toBe(1)
            ->and(Payment::count())->toBe(1)->and((float) Payment::notVoided()->sum('amount'))->toBe(0.0);
        expect(fn () => $service->apply($invoice->id, 6, $service->preview($invoice->id, 6)['fingerprint'], 'Koreksi ulang invoice VOID', 7, true))->toThrow(ValidationException::class);
    });

    it('preserves converted proforma and settlement amounts while recalculating DP and paid-deal commission', function () {
        DB::table('users')->insert(['id' => 7, 'name' => 'Finance']);
        $proforma = $this->proformas->create($this->quote, $this->input);
        $dp = $this->billing->confirmProformaPayment($proforma, $this->receipt);
        $settlement = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement'])));
        app(PaymentService::class)->addPayment($settlement, ['amount' => 7000000, 'paid_at' => '2026-10-12']);
        $commission = app(SalesCommissionExportService::class);
        $filters = ['paid_from' => '2026-01-01', 'paid_to' => '2026-12-31'];
        expect($commission->rows($filters))->toHaveCount(1);
        $source = $proforma->fresh()->getAttributes();
        $service = app(PaymentVoidService::class);
        $payment = $dp->payments->first();
        $service->apply($dp->id, $payment->id, $service->preview($dp->id, $payment->id)['fingerprint'], 'DP salah input tanpa uang masuk', 7, true);
        expect($proforma->fresh()->getAttributes())->toBe($source)->and($dp->fresh()->status)->toBe('unpaid')
            ->and($settlement->fresh()->status)->toBe('paid')->and((float) $settlement->fresh()->grand_total)->toBe(7000000.0)
            ->and((float) Invoice::sum('grand_total'))->toBe(10000000.0)->and($commission->rows($filters))->toBeEmpty();
        $this->billing->confirmProformaPayment($proforma, $this->receipt);
        expect(Payment::count())->toBe(2)->and($dp->fresh()->status)->toBe('unpaid');
        app(PaymentService::class)->addPayment($dp->fresh(), ['amount' => 3000000, 'paid_at' => '2026-10-12']);
        expect($dp->fresh()->status)->toBe('paid')->and($commission->rows($filters))->toHaveCount(1)
            ->and($commission->rows($filters)->sum('commission_base'))->toBe(10000000.0);
    });
});

it('voids fictitious payment with one completed SPK and two returned DOs without rewriting history or stock', function () {
    $invoice = invoiceVoidFixture($this);
    $service = app(InvoiceVoidService::class);
    $before = invoiceVoidProtectedHistory();
    $preview = $service->preview($invoice->invoice_number);
    expect($preview['blockers'])->toBeEmpty()->and($preview['work_orders'])->toHaveCount(1)
        ->and($preview['delivery_orders'])->toHaveCount(2)->and($preview['payment_total_to_void'])->toBe(3750000.0)
        ->and(invoiceVoidProtectedHistory())->toBe($before)->and($invoice->fresh()->status)->toBe('paid');
    $result = $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Salah input pembayaran dan nominal quote; uang tidak diterima', 7, true);
    $invoice->refresh();
    expect($result['payment_total_voided'])->toBe(3750000.0)->and($invoice->status)->toBe('void')
        ->and((float) $invoice->paid_amount)->toBe(0.0)->and((float) $invoice->balance_due)->toBe(0.0)
        ->and((float) $invoice->grand_total)->toBe(3750000.0)->and($invoice->event_status)->toBe('confirm')
        ->and(invoiceVoidProtectedHistory())->toBe($before)->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1)
        ->and($invoice->voidCorrection->actor_id)->toBe(7)
        ->and($invoice->voidCorrection->snapshot['payments'][0]['amount'])->toEqual(3750000)
        ->and($invoice->voidCorrection->snapshot['invoice']['status'])->toBe('paid');
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Pengulangan koreksi', 7, true))->toThrow(RuntimeException::class);
    expect(DB::table('crm_data_corrections')->count())->toBe(1);
});

it('preserves and labels the converted source proforma when the invoice is voided through the command', function () {
    $invoice = invoiceVoidFixture($this);
    $proforma = ProformaInvoice::create([
        'proforma_number' => 'PF-ORIGINAL', 'quote_id' => $invoice->quote_id, 'invoice_id' => $invoice->id,
        'amount' => 3750000, 'billing_type' => 'down_payment', 'billing_method' => 'nominal', 'billing_percentage' => 50,
        'status' => 'converted', 'issued_at' => '2026-09-29', 'due_at' => '2026-09-30',
        'quote_snapshot' => ['attributes' => $this->quote->getAttributes()], 'conversion_reason' => 'payment_received',
    ]);
    $source = $proforma->fresh()->getAttributes();
    $service = app(InvoiceVoidService::class);
    $this->artisan('crm:invoice-void', [
        'invoice' => $invoice->invoice_number, '--apply' => true, '--no-money-received' => true,
        '--expect' => $service->preview($invoice->invoice_number)['fingerprint'], '--actor' => 7,
        '--reason' => 'Pembayaran salah input tanpa uang diterima',
    ])->assertSuccessful();
    expect($proforma->fresh()->getAttributes())->toBe($source)
        ->and(view('admin::invoices.proformas.print', ['proforma' => $proforma->fresh()])->render())->toContain('INVOICE HASIL KONVERSI SUDAH VOID');
});

it('excludes void billing from every financial report section and prior month cash while retaining unrelated receipts', function () {
    $invoice = invoiceVoidFixture($this);
    expect(proformaFinancialData(['month' => 9])['financialSummary']['received'])->toBe(3750000.0)
        ->and(proformaFinancialData()['financialSummary']['invoiced'])->toBe(3750000.0);
    $service = app(InvoiceVoidService::class);
    $service->apply($invoice->invoice_number, $service->preview($invoice->invoice_number)['fingerprint'], 'Pembayaran fiktif dan nominal invoice salah', 7, true);
    foreach ([['month' => 9], ['month' => 10], ['month' => 'all'], ['month' => 10, 'event_status' => 'confirm']] as $filters) {
        $report = proformaFinancialData($filters);
        expect($report['financialSummary'])->toBe(['invoiced' => 0.0, 'received' => 0.0, 'outstanding' => 0.0, 'expense' => 0.0, 'cash_surplus' => 0.0])
            ->and($report['invoicePerformance'])->toBeEmpty()->and($report['projectPerformance'])->toBeEmpty()
            ->and($report['cashTransactions'])->toBeEmpty()->and($report['topProducts'])->toBeEmpty()
            ->and($report['invoiceStats']['total'])->toBe(0)
            ->and(proformaFinancialCsv($filters))->not->toContain($invoice->invoice_number);
    }
    $replacementQuote = $this->quote->replicate();
    $replacementQuote->quote_number = 'Q-REVISED';
    $replacementQuote->description = 'Pengganti INV 2610-0019; pekerjaan menggunakan SPK dan DO lama.';
    $replacementQuote->save();
    $replacementQuote->items()->create(['name' => 'Photobooth', 'unit' => 'day', 'quantity' => 1, 'price' => 10000000, 'total' => 10000000]);
    $newInvoice = $this->billing->approveProforma($this->billing->createFromQuote($replacementQuote, array_replace($this->input, ['billing_type' => 'full_payment'])));
    app(PaymentService::class)->addPayment($newInvoice, ['amount' => 2000000, 'paid_at' => '2026-10-12']);
    expect(proformaFinancialData()['financialSummary']['invoiced'])->toBe(10000000.0)
        ->and(proformaFinancialData()['financialSummary']['received'])->toBe(2000000.0)
        ->and(proformaFinancialData()['financialSummary']['outstanding'])->toBe(8000000.0)
        ->and(WorkOrder::count())->toBe(1)->and(DB::table('delivery_orders')->count())->toBe(2)
        ->and(Payment::count())->toBe(2)->and((float) Payment::notVoided()->sum('amount'))->toBe(2000000.0);
});

it('blocks void while operational or financial dependencies remain unresolved', function (string $case) {
    $invoice = invoiceVoidFixture($this);
    match ($case) {
        'spk' => DB::table('work_orders')->update(['status' => 'released']),
        'do' => DB::table('delivery_orders')->where('id', 1)->update(['status' => 'delivered']),
        'allocation' => DB::table('delivery_order_inventory_allocations')->where('id', 1)->update(['status' => 'return_pending']),
        'expense' => DB::table('expenses')->insert(['invoice_id' => $invoice->id, 'amount' => 500, 'expense_date' => '2026-10-08']),
        'po' => DB::table('purchase_orders')->insert(['invoice_id' => $invoice->id]),
        'settlement' => DB::table('invoices')->insert(['invoice_number' => 'SETTLEMENT', 'quote_id' => $invoice->quote_id, 'dp_invoice_id' => $invoice->id]),
        'cash period' => DB::table('financial_period_locks')->insert(['period' => '2026-09', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-30']),
        'invoice period' => DB::table('financial_period_locks')->insert(['period' => '2026-10', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-31']),
        'payment mismatch' => DB::table('payments')->update(['amount' => 3000000]),
        'proforma settlement' => ProformaInvoice::create([
            'proforma_number' => 'PF-SETTLEMENT', 'quote_id' => $invoice->quote_id, 'dp_invoice_id' => $invoice->id,
            'amount' => 3750000, 'billing_type' => 'settlement', 'billing_method' => 'nominal', 'billing_percentage' => 50,
            'status' => 'issued', 'issued_at' => '2026-10-08', 'due_at' => '2026-10-12', 'quote_snapshot' => [],
        ]),
    };
    $before = invoiceVoidProtectedHistory();
    $service = app(InvoiceVoidService::class);
    $preview = $service->preview($invoice->invoice_number);
    expect($preview['blockers'])->not->toBeEmpty();
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Uji pembatasan koreksi', 7, true))->toThrow(RuntimeException::class);
    expect($invoice->fresh()->status)->toBe('paid')->and(invoiceVoidProtectedHistory())->toBe($before)
        ->and(DB::table('crm_data_corrections')->count())->toBe(0);
})->with(['spk', 'do', 'allocation', 'expense', 'po', 'settlement', 'cash period', 'invoice period', 'payment mismatch', 'proforma settlement']);

it('requires a current preview including returned inventory and period state', function (string $case) {
    $invoice = invoiceVoidFixture($this);
    $service = app(InvoiceVoidService::class);
    $preview = $service->preview($invoice->invoice_number);
    match ($case) {
        'payment' => DB::table('payments')->update(['notes' => 'Edited after review']),
        'do' => DB::table('delivery_orders')->where('id', 1)->update(['delivery_order_number' => 'DO-REVIEWED']),
        'lock' => DB::table('financial_period_locks')->insert(['period' => '2026-09', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-30']),
    };
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Koreksi dengan preview lama', 7, true))->toThrow(RuntimeException::class, 'Data berubah');
    expect($invoice->fresh()->status)->toBe('paid')->and(DB::table('crm_data_corrections')->count())->toBe(0);
})->with(['payment', 'do', 'lock']);

it('rolls back audit and billing together on a failed void without touching operational history', function () {
    $invoice = invoiceVoidFixture($this);
    $before = invoiceVoidProtectedHistory();
    $service = app(InvoiceVoidService::class);
    $preview = $service->preview($invoice->invoice_number);
    DB::unprepared("CREATE TRIGGER fail_void BEFORE UPDATE ON invoices WHEN NEW.status = 'void' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Uji rollback pembatalan', 7, true))->toThrow(QueryException::class);
    expect($invoice->fresh()->status)->toBe('paid')->and(invoiceVoidProtectedHistory())->toBe($before)
        ->and(DB::table('crm_data_corrections')->count())->toBe(0);
});

it('keeps invoice void preview read only and requires explicit wrong payment acknowledgement and audit actor', function () {
    $invoice = invoiceVoidFixture($this);
    $service = app(InvoiceVoidService::class);
    $preview = $service->preview($invoice->invoice_number);
    $this->artisan('crm:invoice-void', ['invoice' => $invoice->invoice_number])->assertSuccessful();
    $this->artisan('crm:invoice-void', ['invoice' => $invoice->invoice_number, '--apply' => true])->assertFailed();
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'Uji actor tidak valid', 999, true))->toThrow(RuntimeException::class);
    expect(fn () => $service->apply($invoice->invoice_number, '', 'Alasan valid', 7, true))->toThrow(RuntimeException::class);
    expect(fn () => $service->apply($invoice->invoice_number, $preview['fingerprint'], 'x', 7, true))->toThrow(RuntimeException::class);
    expect($invoice->fresh()->status)->toBe('paid')->and(DB::table('crm_data_corrections')->count())->toBe(0);
});

it('keeps void invoice immutable and labels its PDF without disabling ordinary paid invoice expenses', function () {
    $invoice = invoiceVoidFixture($this);
    $service = app(InvoiceVoidService::class);
    $service->apply($invoice->invoice_number, $service->preview($invoice->invoice_number)['fingerprint'], 'Admin salah input pembayaran, uang tidak masuk', 7, true);
    $invoice->refresh();
    expect(fn () => $invoice->update(['event_status' => 'cancel']))->toThrow(ValidationException::class);
    expect(fn () => app(PaymentService::class)->addPayment($invoice, ['amount' => 1]))->toThrow(ValidationException::class);
    expect(fn () => app(WorkOrderService::class)->createFromInvoice($invoice))->toThrow(ValidationException::class);
    expect(fn () => $invoice->expenses()->create(['amount' => 100, 'expense_date' => '2026-10-12']))->toThrow(ValidationException::class);
    expect(fn () => $invoice->voidCorrection->delete())->toThrow(LogicException::class);
    $invoice->refresh();
    expect(view('admin::invoices.pdf', compact('invoice'))->render())->toContain('VOID / DIBATALKAN', 'BUKAN TAGIHAN AKTIF', 'Admin salah input pembayaran, uang tidak masuk');
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices', 'invoices.view', 'work-orders.view']]);
    $this->actingAs((new User)->forceFill(['id' => 7, 'name' => 'Finance', 'status' => 1])->setRelation('role', $role), 'user');
    $this->get(route('admin.invoices.show', $invoice->id))->assertOk()
        ->assertSee('VOID / DIBATALKAN')->assertSee('Buka SPK')
        ->assertSee('Admin salah input pembayaran, uang tidak masuk')
        ->assertDontSee('action="'.route('admin.invoices.payments.store', $invoice->id).'"', false)
        ->assertDontSee('action="'.route('admin.invoices.event-status.update', $invoice->id).'"', false);
    // Historical link remains available even though new SPK generation is blocked.
    expect(app(WorkOrderService::class)->findForInvoice($invoice)->status)->toBe('completed');
    $other = Invoice::create(['invoice_number' => 'INV-PAID-OTHER', 'grand_total' => 1000, 'status' => 'paid', 'event_status' => 'confirm']);
    $expense = $other->expenses()->create(['amount' => 100, 'expense_date' => '2026-10-12']);
    expect($expense->exists)->toBeTrue();
});

it('shows pending proforma only in follow-up and never in financial values or exports', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $report = proformaFinancialData();
    expect($report['financialSummary'])->toBe([
        'invoiced' => 0.0, 'received' => 0.0, 'outstanding' => 0.0, 'expense' => 0.0, 'cash_surplus' => 0.0,
    ])->and($report['invoicePerformance'])->toBeEmpty()
        ->and($report['projectPerformance'])->toBeEmpty()->and($report['topProducts'])->toBeEmpty()
        ->and($report['cashTransactions'])->toBeEmpty();
    $monitor = app(ProformaFollowUpService::class)->summary();
    expect($monitor['count'])->toBe(1)->and($monitor['amount'])->toBe(3000000.0)
        ->and($monitor['rows']->first()->id)->toBe($proforma->id);
    expect(proformaFinancialCsv())->not->toContain($proforma->proforma_number, 'Unbilled', 'Company Awal');
});

it('reports only the actual 3 million DP then 7 million settlement and groups their project costs once', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $dp = $this->billing->approveProforma($proforma);
    $report = proformaFinancialData();
    expect($report['financialSummary']['invoiced'])->toBe(3000000.0)
        ->and($report['financialSummary']['outstanding'])->toBe(3000000.0)
        ->and($report['financialSummary']['received'])->toBe(0.0)
        ->and($report['topProducts'][0]['sales_value'])->toBe(3000000.0)
        ->and(app(ProformaFollowUpService::class)->summary()['count'])->toBe(0);
    app(PaymentService::class)->addPayment($dp, $this->receipt);
    $settlementProforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement']));
    $report = proformaFinancialData();
    expect($report['financialSummary']['invoiced'])->toBe(3000000.0)
        ->and($report['financialSummary']['received'])->toBe(3000000.0)
        ->and($report['financialSummary']['outstanding'])->toBe(0.0)
        ->and($report['projectPerformance'][0]['invoiced'])->toBe(3000000.0)
        ->and(app(ProformaFollowUpService::class)->summary()['amount'])->toBe(7000000.0);
    $settlement = $this->billing->approveProforma($settlementProforma);
    DB::table('expenses')->insert(['invoice_id' => $dp->id, 'amount' => 6000000, 'expense_date' => '2026-10-12']);
    $report = proformaFinancialData();
    expect($report['financialSummary']['invoiced'])->toBe(10000000.0)
        ->and($report['financialSummary']['outstanding'])->toBe(7000000.0)
        ->and($report['projectPerformance'])->toHaveCount(1)
        ->and($report['projectPerformance'][0]['invoice_count'])->toBe(2)
        ->and($report['projectPerformance'][0]['expense'])->toBe(6000000.0)
        ->and($report['projectPerformance'][0]['margin'])->toBe(4000000.0)
        ->and($report['topProducts'][0]['sales_value'])->toBe(10000000.0)
        ->and($report['topProducts'][0]['deal_count'])->toBe(1)
        ->and($report['topProducts'][0]['quantity_label'])->toBe('2 Day');
    app(PaymentService::class)->addPayment($settlement, ['amount' => 7000000]);
    $this->billing->approveProforma($settlementProforma);
    $report = proformaFinancialData();
    expect($report['financialSummary']['received'])->toBe(10000000.0)
        ->and($report['financialSummary']['outstanding'])->toBe(0.0)
        ->and($report['financialSummary']['cash_surplus'])->toBe(4000000.0)
        ->and($report['invoiceStats']['total'])->toBe(2)
        ->and(app(ProformaFollowUpService::class)->summary()['count'])->toBe(0);
    expect(proformaFinancialCsv())->toContain($dp->invoice_number, $settlement->invoice_number, '3000000', '7000000', 'Company Awal')
        ->not->toContain('PROJECT POSITION - CURRENT', '4000000');
});

it('keeps top products on actual invoice snapshots when a quote is revised before conversion', function () {
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->quote->update(['grand_total' => 20000000, 'business_unit' => 'varbel']);
    $this->quote->items()->update(['name' => 'Changed Product', 'quantity' => 99]);
    $this->billing->approveProforma($proforma);
    $report = proformaFinancialData(['business_unit' => 'capture_it']);
    expect($report['topProducts'])->toHaveCount(1)
        ->and($report['topProducts'][0]['product_name'])->toBe('Photobooth')
        ->and($report['topProducts'][0]['sales_value'])->toBe(3000000.0)
        ->and($report['topProducts'][0]['quantity_label'])->toBe('2 Day')
        ->and($report['projectPerformance'][0]['invoiced'])->toBe(3000000.0);
    expect(proformaFinancialCsv(['section' => 'top-products']))->toContain('Allocated Invoice Value', '3000000')->not->toContain('Changed Product', '20000000');
});

it('keeps older invoice cash movements on the dashboard and outside the invoice period export', function () {
    $proforma = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment']));
    $invoice = $this->billing->approveProforma($proforma);
    $this->travelTo(now()->parse('2026-11-03 12:00:00'));
    app(PaymentService::class)->addPayment($invoice, ['amount' => 10000000, 'paid_at' => '2026-11-03', 'reference_number' => 'NOV-RECEIPT']);
    DB::table('expenses')->insert(['invoice_id' => $invoice->id, 'amount' => 6000000, 'expense_date' => '2026-11-02', 'reference_number' => 'NOV-COST']);
    $report = proformaFinancialData(['month' => 11]);
    expect($report['financialSummary']['invoiced'])->toBe(0.0)
        ->and($report['invoicePerformance'])->toBeEmpty()
        ->and($report['financialSummary']['received'])->toBe(10000000.0)
        ->and($report['financialSummary']['expense'])->toBe(6000000.0)
        ->and($report['cashTransactions'])->toHaveCount(2)
        ->and($report['cashTransactions']->sum('cash_in'))->toBe(10000000.0)
        ->and($report['cashTransactions']->sum('cash_out'))->toBe(6000000.0);
    $csv = proformaFinancialCsv(['month' => 11]);
    expect($report['cashTransactions']->pluck('reference')->all())->toContain('NOV-RECEIPT', 'NOV-COST');
    expect($csv)->toContain('No Invoice', 'Nilai Pembayaran Periode')
        ->not->toContain('CASH TRANSACTIONS IN PERIOD', 'NOV-RECEIPT', 'NOV-COST', $invoice->invoice_number);
    expect(proformaFinancialCsv(['month' => 10]))->toContain($invoice->invoice_number, '10000000');
    $annual = proformaFinancialData(['month' => '']);
    expect($annual['monthlyPerformance'][9]['revenue'])->toBe(10000000.0)
        ->and($annual['monthlyPerformance'][10]['received'])->toBe(10000000.0)
        ->and($annual['monthlyPerformance'][10]['expense'])->toBe(6000000.0);
    expect(proformaFinancialData(['month' => 10])['financialSummary']['outstanding'])->toBe(0.0);
});

it('attributes DP and settlement values to their invoice months while keeping one current project position', function () {
    $dp = $this->billing->confirmProformaPayment($this->proformas->create($this->quote, $this->input), $this->receipt);
    $this->travelTo(now()->parse('2026-11-02 12:00:00'));
    $settlement = $this->billing->approveProforma($this->billing->createFromQuote($this->quote, array_replace($this->input, [
        'billing_type' => 'settlement', 'issued_at' => '2026-11-02', 'due_at' => '2026-11-10',
    ])));
    DB::table('expenses')->insert(['invoice_id' => $dp->id, 'amount' => 6000000, 'expense_date' => '2026-11-02']);
    $october = proformaFinancialData(['month' => 10]);
    $november = proformaFinancialData(['month' => 11]);
    expect($october['topProducts'][0]['sales_value'])->toBe(3000000.0)
        ->and($november['topProducts'][0]['sales_value'])->toBe(7000000.0)
        ->and($november['projectPerformance'][0]['margin'])->toBe(4000000.0)
        ->and($november['projectPerformance'][0]['outstanding'])->toBe(7000000.0)
        ->and($november['projectPerformance'][0]['invoice_numbers'])->toContain($dp->invoice_number, $settlement->invoice_number);
    $annual = proformaFinancialData(['month' => '']);
    expect($annual['topProducts'][0]['sales_value'])->toBe(10000000.0)->and($annual['topProducts'][0]['deal_count'])->toBe(1);
});

it('keeps older pending proformas visible and filters them by frozen unit and product', function () {
    $proforma = $this->proformas->create($this->quote, array_replace($this->input, ['issued_at' => '2026-09-01', 'due_at' => '2026-09-10']));
    $this->quote->update(['business_unit' => 'varbel', 'grand_total' => 20000000]);
    $this->quote->items()->update(['name' => 'Changed Product']);
    $service = app(ProformaFollowUpService::class);
    $monitor = $service->summary(['year' => 2027, 'month' => 1, 'event_status' => 'cancel', 'business_unit' => 'capture_it', 'product' => 'Photobooth']);
    expect($monitor['count'])->toBe(1)->and($monitor['amount'])->toBe(3000000.0)->and($monitor['overdue_count'])->toBe(1);
    expect($service->summary(['business_unit' => 'varbel'])['count'])->toBe(0)
        ->and($service->summary(['product' => 'Changed Product'])['count'])->toBe(0);
    $this->proformas->cancel($proforma, 'Tidak jadi event');
    expect($service->summary()['count'])->toBe(0);
});

it('respects role permissions for follow-up visibility and links without granting conversion rights', function () {
    $this->proformas->create($this->quote, $this->input);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices.financial-report', 'invoices.financial-report.export']]);
    $user = (new User)->forceFill(['id' => 17, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    expect(proformaFinancialData()['proformaFollowUp'])->toBeNull();
    $role->permissions = ['invoices', 'invoices.financial-report'];
    $report = proformaFinancialData();
    expect($report['proformaFollowUp']['count'])->toBe(1);
    $html = view('admin::invoices.proformas.follow-up', $report)->render();
    expect($html)->toContain('Proforma Belum Menjadi Invoice', '3.000.000')->not->toContain('Periksa proforma');
    $role->permissions = ['invoices', 'invoices.view', 'invoices.financial-report'];
    expect(view('admin::invoices.proformas.follow-up', proformaFinancialData())->render())->toContain('Periksa proforma');
    expect(bouncer()->hasPermission('invoices.generate'))->toBeFalse();
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $this->postJson(route('admin.proformas.approve', 1), ['event_confirmed' => 1])->assertUnauthorized();
});

it('lists only pending proformas when following the monitoring link', function () {
    $first = $this->proformas->create($this->quote, $this->input);
    $this->proformas->cancel($first, 'Diganti');
    $second = $this->proformas->create($this->quote, $this->input);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices']]);
    $this->actingAs((new User)->forceFill(['id' => 17, 'status' => 1])->setRelation('role', $role), 'user');
    $data = app(ProformaInvoiceController::class)->index(Request::create('/', 'GET', ['status' => 'issued']), app(ProformaFollowUpService::class))->getData();
    expect($data['proformas']->total())->toBe(1)->and($data['proformas']->first()->id)->toBe($second->id);
});

it('renders the financial report and pending list for an authorized finance user', function () {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices', 'invoices.view', 'invoices.financial-report', 'invoices.financial-report.export']]);
    $this->actingAs((new User)->forceFill(['id' => 17, 'name' => 'Finance', 'status' => 1])->setRelation('role', $role), 'user');
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->get(route('admin.invoices.financial-report', ['year' => 2026, 'month' => 10]))
        ->assertOk()->assertSee('Proforma Belum Menjadi Invoice')->assertSee($proforma->proforma_number)
        ->assertSee('Rincian Uang Masuk dan Keluar')->assertDontSee('Unbilled Remainder');
    $this->get(route('admin.proformas.index', ['status' => 'issued']))
        ->assertOk()->assertSee('Belum menjadi Invoice')->assertSee($proforma->proforma_number);
});

it('refreshes corrected contact names across quote proforma and invoice displays without changing agreed amounts', function () {
    DB::table('organizations')->insert(['id' => 1, 'name' => 'Company Awal']);
    DB::table('persons')->insert(['id' => 1, 'name' => 'Customer', 'organization_id' => 1]);
    $this->quote->update(['person_id' => 1]);
    $proforma = $this->proformas->create($this->quote, $this->input);
    $invoice = $this->billing->approveProforma($proforma);
    $before = $invoice->getAttributes();
    DB::table('persons')->where('id', 1)->update(['name' => 'Nama Person Baru']);
    DB::table('organizations')->where('id', 1)->update(['name' => 'PT Company Baru']);
    $invoice->refresh();
    expect($invoice->billToIdentity()['person_name'])->toBe('Nama Person Baru')
        ->and($invoice->billToIdentity()['company_name'])->toBe('PT Company Baru')
        ->and($invoice->getAttributes())->toBe($before);
    foreach ([
        view('admin::invoices.pdf', ['invoice' => $invoice->load('items')])->render(),
        view('admin::quotes.pdf', ['quote' => $this->quote->fresh('items')])->render(),
        view('admin::invoices.proformas.print', ['proforma' => $proforma->fresh()])->render(),
    ] as $html) {
        expect($html)->toContain('Nama Person Baru', 'PT Company Baru')->not->toContain('Company Awal');
    }
    DB::table('persons')->insert(['id' => 2, 'name' => 'Different Recipient']);
    $this->quote->update(['person_id' => 2]);
    expect($invoice->fresh()->billToIdentity()['person_name'])->toBe('Nama Person Baru');
});
