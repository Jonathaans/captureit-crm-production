<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\ProformaInvoiceService;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Invoice\Models\WorkOrder;
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
    $this->travelTo(now()->parse('2026-10-12 12:00:00'));

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
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_150000_create_financial_period_locks_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_210000_create_work_orders_and_link_delivery_orders.php'))->up();

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
    $settlement = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'settlement', 'issued_at' => '2026-10-19', 'due_at' => '2026-10-19']));
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
    $settlement = $this->billing->createFromQuote($this->quote, array_replace($this->input, [
        'billing_type' => 'settlement', 'issued_at' => '2026-10-23', 'due_at' => '2026-11-03', 'payment_term' => 'Lunas 14 hari setelah event',
    ]));
    expect((float) $settlement->grand_total)->toBe(7000000.0)->and($settlement->bill_to_company_name)->toBe('Company Awal')
        ->and($settlement->items->first()->name)->toBe('Photobooth')
        ->and($settlement->due_at->format('Y-m-d'))->toBe('2026-11-03');
    expect($this->billing->sumUnbilledForQuoteIds([$this->quote->id]))->toBe(0.0);
});

it('allows no DP with full invoice and SPK before payment for either TOP', function (string $dueAt) {
    $invoice = $this->billing->createFromQuote($this->quote, array_replace($this->input, [
        'billing_type' => 'full_payment', 'due_at' => $dueAt,
    ]));
    expect($invoice->status)->toBe('unpaid')->and((float) $invoice->grand_total)->toBe(10000000.0)
        ->and($invoice->due_at->format('Y-m-d'))->toBe($dueAt)
        ->and(ProformaInvoice::count())->toBe(0)->and(Payment::count())->toBe(0)
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
    $invoice = $this->billing->createFromQuote($this->quote, array_replace($this->input, ['billing_type' => 'full_payment']));
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
    expect($html)->toContain('PROFORMA INVOICE', 'Company Awal', '3.000.000,00', '7.000.000,00', 'DP 30%, pelunasan H-1 event');
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
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, ['quote_id' => $this->quote->id]))->assertUnauthorized();
    $proforma = $this->proformas->create($this->quote, $this->input);
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertUnauthorized();
    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
    $role->permissions = ['invoices.payment', 'invoices.view'];
    $this->postJson(route('admin.proformas.payment', $proforma->id), $this->receipt)->assertRedirect(route('admin.proformas.show', $proforma->id));
    expect(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1);
});

it('routes the existing generate form to proforma for DP and actual invoice for no DP', function () {
    $this->withoutMiddleware(CanInstall::class);
    config(['app.key' => str_repeat('a', 32)]);
    $role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['invoices.generate', 'invoices.view']]);
    $user = (new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, [
        'quote_id' => $this->quote->id, 'billing_method' => 'percentage', 'billing_percentage' => 30, 'billing_amount' => null,
    ]))->assertRedirect(route('admin.proformas.show', 1));
    expect(Invoice::count())->toBe(0)->and((float) ProformaInvoice::firstOrFail()->amount)->toBe(3000000.0);
    $this->getJson(route('admin.invoices.billing.summary', $this->quote->id))->assertOk()->assertJsonPath('data.eligibility.down_payment', false);
    $this->proformas->cancel(ProformaInvoice::firstOrFail(), 'Tanpa DP sesuai kesepakatan baru');
    $this->postJson(route('admin.invoices.billing.store'), array_merge($this->input, [
        'quote_id' => $this->quote->id, 'billing_type' => 'full_payment',
    ]))->assertRedirect(route('admin.invoices.show', 1));
    expect((float) Invoice::firstOrFail()->grand_total)->toBe(10000000.0)->and(Payment::count())->toBe(0);
});
