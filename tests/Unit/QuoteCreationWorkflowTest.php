<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Admin\Services\CrmReadOnlyArchivePolicyService;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Invoice\Models\WorkOrder;
use Webkul\Lead\Models\Lead;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.quote_creation_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('quote_creation_test');
    Schema::clearResolvedInstance('db.schema');
    (require base_path('database/migrations/2026_10_13_150000_create_payment_voids_table.php'))->up();
    $this->travelTo(now()->parse('2026-10-12 12:00:00'));
    DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4));
    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) substr($date, 5, 2));

    foreach ([
        'packages/Webkul/Attribute/src/Database/Migrations/2021_04_02_080709_create_attributes_table.php',
        'packages/Webkul/Attribute/src/Database/Migrations/2021_04_02_080837_create_attribute_options_table.php',
        'packages/Webkul/Attribute/src/Database/Migrations/2021_04_06_122751_create_attribute_values_table.php',
        'packages/Webkul/Automation/src/Database/Migrations/2021_08_26_133538_create_workflows_table.php',
        'packages/Webkul/Core/src/Database/Migrations/2021_04_12_173232_create_countries_table.php',
        'packages/Webkul/Core/src/Database/Migrations/2021_04_12_173344_create_country_states_table.php',
    ] as $migration) {
        (require base_path($migration))->up();
    }
    foreach (['quotes', 'invoices'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->increments('id');
            $table->string($name === 'quotes' ? 'quote_number' : 'invoice_number')->unique();
            foreach (['subject', 'project_code', 'business_unit', 'location', 'payment_term', 'description', 'bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name', 'client_signer_name', 'client_signer_company'] as $column) {
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
    (require base_path('packages/Webkul/User/src/Database/Migrations/2021_03_12_074597_create_roles_table.php'))->up();
    (require base_path('packages/Webkul/User/src/Database/Migrations/2021_03_12_074857_create_users_table.php'))->up();
    (require base_path('database/migrations/2026_08_27_114333_create_delivery_orders_table.php'))->up();
    (require base_path('database/migrations/2026_08_27_114340_create_delivery_order_items_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_220000_create_internal_notifications_and_chat_tables.php'))->up();
    (require base_path('database/migrations/2026_10_12_030000_create_proforma_invoices_table.php'))->up();
    (require base_path('database/migrations/2026_10_12_133000_add_proforma_conversion_workflow.php'))->up();
    (require base_path('database/migrations/2026_10_15_100000_add_pph_gross_up_to_billing_documents.php'))->up();
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_150000_create_financial_period_locks_table.php'))->up();
    (require base_path('database/migrations/2026_09_01_210000_create_work_orders_and_link_delivery_orders.php'))->up();
    (require base_path('database/migrations/2026_10_15_090000_add_commercial_document_revisions.php'))->up();

    Schema::create('products', function (Blueprint $table) {
        $table->increments('id');
        $table->string('sku');
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('unit')->nullable();
        $table->decimal('price', 16, 4)->default(0);
    });
    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->string('description')->nullable();
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('person_id');
        $table->date('expected_close_date')->nullable();
        $table->timestamps();
    });
    foreach ([
        'packages/Webkul/Lead/src/Database/Migrations/2021_04_22_171805_create_lead_products_table.php',
        'packages/Webkul/Lead/src/Database/Migrations/2021_07_02_201822_create_lead_quotes_table.php',
        'database/migrations/2026_08_27_133453_create_product_equipment_templates_table.php',
        'database/migrations/2026_08_27_133504_create_product_equipment_template_items_table.php',
        'database/migrations/2026_08_28_120500_create_inventory_items_table.php',
        'database/migrations/2026_08_28_140100_add_inventory_item_id_to_product_equipment_template_items.php',
        'database/migrations/2026_08_28_140110_add_inventory_item_id_to_delivery_order_items.php',
        'database/migrations/2026_10_07_010000_add_equipment_requirement_rules.php',
    ] as $migration) {
        (require base_path($migration))->up();
    }
    Schema::table('lead_products', function (Blueprint $table) {
        $table->string('name')->nullable();
        $table->string('unit')->nullable();
        $table->integer('day')->default(1);
        $table->decimal('equipment_quantity', 16, 4)->nullable();
    });
    Schema::table('delivery_orders', fn (Blueprint $table) => $table->string('business_unit')->nullable());
    foreach (['subject' => 'text', 'expired_at' => 'date', 'person_id' => 'lookup', 'user_id' => 'lookup'] as $code => $type) {
        DB::table('attributes')->insert([
            'code' => $code, 'name' => $code, 'type' => $type, 'entity_type' => 'quotes',
            'is_required' => true, 'is_user_defined' => false,
            'lookup_type' => match ($code) {
                'person_id' => 'persons', 'user_id' => 'users', default => null
            },
        ]);
    }
    $role = Role::create(['name' => 'Superadministrator', 'permission_type' => 'all']);
    $this->user = User::create(['name' => 'Admin Sales', 'email' => 'admin@example.test', 'status' => 1, 'role_id' => $role->id]);
    $this->withoutMiddleware(CanInstall::class);
    $this->actingAs($this->user, 'user');
    DB::table('organizations')->insert(['id' => 1, 'name' => 'Company Customer']);
    DB::table('persons')->insert(['id' => 1, 'name' => 'Customer', 'organization_id' => 1]);
    DB::table('products')->insert(['id' => 1, 'sku' => 'CAM', 'name' => 'Camera', 'unit' => 'day', 'price' => 5000000]);
    DB::table('product_equipment_templates')->insert(['id' => 1, 'product_id' => 1, 'name' => 'Camera kit', 'is_active' => true]);
    DB::table('product_equipment_template_items')->insert(['template_id' => 1, 'name' => 'Camera', 'quantity' => 1, 'unit' => 'pcs']);
    $this->lead = Lead::createQuietly([
        'title' => 'Event Customer', 'description' => 'Camera rental',
        'user_id' => $this->user->id, 'person_id' => 1, 'expected_close_date' => '2026-10-01',
    ]);
    DB::table('lead_products')->insert([
        'lead_id' => $this->lead->id, 'product_id' => 1, 'name' => 'Camera',
        'unit' => 'day', 'quantity' => 2, 'equipment_quantity' => 1, 'price' => 5000000, 'amount' => 10000000,
    ]);
    $this->payload = [
        'entity_type' => 'quotes', 'subject' => 'Event Customer', 'description' => 'Camera rental',
        'person_id' => 1, 'user_id' => $this->user->id, 'expired_at' => now()->toDateString(),
        'event_date' => '2026-10-20', 'location' => 'Jakarta', 'business_unit' => 'capture_it',
        'bill_to_display_mode' => 'both', 'billing_address' => [], 'shipping_address' => [],
        'payment_term' => 'DP 30%, pelunasan sebelum event',
        'items' => ['item_0' => [
            'product_id' => 1, 'sku' => 'CAM', 'name' => 'Camera', 'unit' => 'day', 'quantity' => 2,
            'price' => 5000000, 'total' => 10000000, 'discount_amount' => 0, 'tax_amount' => 0, 'final_total' => 10000000,
        ]],
    ];
});

afterEach(function (): void {
    $this->travelBack();
    DB::purge('quote_creation_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('persists a quote and its items from Create or Generate from Lead on a valid date', function (bool $fromLead, bool $today) {
    $payload = array_replace($this->payload, ['expired_at' => $today ? now()->toDateString() : now()->addWeek()->toDateString()]);
    if ($fromLead) {
        $payload['lead_id'] = $this->lead->id;
    }
    $this->post(route('admin.quotes.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $quote = Quote::sole();
    expect($quote->items)->toHaveCount(1)
        ->and((float) $quote->grand_total)->toBe(10000000.0)
        ->and((float) $quote->items->first()->equipment_quantity)->toBe(1.0)
        ->and($quote->quote_number)->toStartWith('QT ')
        ->and($quote->project_code)->toStartWith('PRJ-2026-')
        ->and(DB::table('lead_quotes')->where('quote_id', $quote->id)->count())->toBe($fromLead ? 1 : 0);
})->with([[false, true], [true, true], [false, false], [true, false]]);

it('renders Generate Quote from an old Lead and saves the imported fields and items', function () {
    $response = $this->get(route('admin.quotes.create', ['lead_id' => $this->lead->id, 'from' => 'lead']));
    $response->assertOk()->assertSee('Quote belum tersimpan. Periksa isian berikut:')->assertSee(now()->toDateString());
    $import = $this->getJson(route('admin.quotes.lead_products', $this->lead->id))->assertOk()->json();
    expect($import['quote']['expired_at'])->toBe(now()->toDateString())
        ->and($import['quote']['subject'])->toBe($this->lead->title)
        ->and($import['data'][0]['quantity'])->toEqual(2)
        ->and($import['data'][0]['total'])->toEqual(10000000);
    $item = array_replace($import['data'][0], ['final_total' => $import['data'][0]['total']]);
    $payload = array_replace($this->payload, $import['quote'], [
        'lead_id' => $this->lead->id, 'items' => ['item_0' => $item],
    ]);
    $this->post(route('admin.quotes.store', ['from' => 'lead']), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.leads.view', ['id' => $this->lead->id, 'from' => 'quotes']));
    $quote = Quote::sole();
    expect($this->lead->quotes()->first()->id)->toBe($quote->id)
        ->and((float) $quote->grand_total)->toBe(10000000.0)
        ->and((float) $quote->items->first()->equipment_quantity)->toBe(1.0)
        ->and($this->lead->fresh()->expected_close_date->toDateString())->toBe('2026-10-01');
});

it('keeps the quote and items valid until local midnight while preserving converted quote locks', function () {
    config(['app.timezone' => 'Asia/Jakarta']);
    $this->travelTo(Carbon::parse('2026-10-12 23:59:59', 'Asia/Jakarta'));
    $this->post(route('admin.quotes.store'), $this->payload)->assertSessionHasNoErrors();
    $quote = Quote::sole();
    $policy = app(CrmReadOnlyArchivePolicyService::class);
    expect($policy->archiveReason($quote))->toBeNull()
        ->and($policy->archiveReason($quote->items->first()))->toBeNull();
    $this->travelTo(Carbon::parse('2026-10-13 00:00:00', 'Asia/Jakarta'));
    expect($policy->archiveReason($quote))->toContain('kedaluwarsa')
        ->and($policy->archiveReason($quote->items->first()))->toContain('kedaluwarsa');
    $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'Asia/Jakarta'));
    DB::table('invoices')->insert(['invoice_number' => 'INV-LOCK', 'quote_id' => $quote->id]);
    expect($policy->archiveReason($quote))->toContain('Invoice')
        ->and($policy->archiveReason($quote->items->first()))->toContain('Invoice');
});

it('returns validation errors without persisting a partial quote', function (string $field, mixed $value) {
    $this->post(route('admin.quotes.store'), array_replace($this->payload, [$field => $value]))
        ->assertSessionHasErrors($field);
    expect(Quote::count())->toBe(0)
        ->and(DB::table('quote_items')->count())->toBe(0)
        ->and(DB::table('attribute_values')->count())->toBe(0);
})->with([['expired_at', '2026-10-01'], ['items', []], ['person_id', null], ['user_id', null]]);

it('completes Lead Quote Proforma Invoice SPK and DO through the authenticated routes', function (bool $dp, string $dueAt, bool $grossUp = false) {
    $this->post(route('admin.quotes.store'), array_replace($this->payload, ['lead_id' => $this->lead->id, 'pph_gross_up' => $grossUp ? '1' : '0']))
        ->assertSessionHasNoErrors()->assertRedirect();
    $quote = Quote::sole();
    $top = $dp ? 'DP 30%, pelunasan H-1 event' : 'Lunas sesuai TOP '.$dueAt;
    $this->post(route('admin.invoices.billing.store'), [
        'quote_id' => $quote->id, 'billing_type' => $dp ? 'down_payment' : 'full_payment',
        'billing_method' => 'percentage', 'billing_percentage' => 30,
        'issued_at' => '2026-10-12', 'due_at' => $dueAt, 'payment_term' => $top,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $proforma = ProformaInvoice::sole();
    expect($proforma->status)->toBe('issued')
        ->and(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0)
        ->and(WorkOrder::count())->toBe(0)->and(DeliveryOrder::count())->toBe(0);
    if ($dp) {
        $this->post(route('admin.proformas.payment', $proforma->id), [
            'amount' => 3000000, 'paid_at' => '2026-10-12 10:00:00', 'payment_method' => 'Transfer',
        ])->assertSessionHasNoErrors()->assertRedirect();
    } else {
        $this->post(route('admin.proformas.approve', $proforma->id), ['event_confirmed' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
    }
    $invoice = Invoice::sole();
    expect($proforma->fresh()->invoice_id)->toBe($invoice->id)
        ->and($invoice->status)->toBe($dp ? 'paid' : 'unpaid')
        ->and((float) $invoice->grand_total)->toBe($grossUp ? ($dp ? 3015075.38 : 10050251.26) : ($dp ? 3000000.0 : 10000000.0))
        ->and((float) $invoice->paid_amount)->toBe($dp ? 3000000.0 : 0.0)
        ->and($invoice->due_at->toDateString())->toBe($dueAt)
        ->and($invoice->payment_term)->toBe($top)
        ->and((float) Payment::sum('amount'))->toBe($dp ? 3000000.0 : 0.0);
    $this->post(route('admin.invoices.work-orders.store', $invoice->id))->assertSessionHasNoErrors()->assertRedirect();
    $spk = WorkOrder::sole();
    expect($spk->invoice_id)->toBe($invoice->id)->and($spk->items)->toHaveCount(1);
    $this->put(route('admin.work-orders.release', $spk->id))->assertSessionHasNoErrors()->assertRedirect();
    expect($spk->fresh()->status)->toBe('released');
    $this->post(route('admin.work-orders.delivery-orders.generate', $spk->id))
        ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();
    $delivery = DeliveryOrder::sole();
    expect($delivery->work_order_id)->toBe($spk->id)
        ->and($delivery->quote_id)->toBe($quote->id)
        ->and($delivery->items)->toHaveCount(1)
        ->and((float) $delivery->items->first()->quantity)->toBe(1.0);
    if ($dp) {
        $this->travelTo(now()->parse('2026-10-19 12:00:00'));
        $this->post(route('admin.invoices.billing.store'), [
            'quote_id' => $quote->id, 'billing_type' => 'settlement',
            'issued_at' => '2026-10-19', 'due_at' => '2026-10-19', 'payment_term' => 'Pelunasan H-1 event',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $settlementProforma = ProformaInvoice::where('billing_type', 'settlement')->sole();
        $this->post(route('admin.proformas.approve', $settlementProforma->id), ['event_confirmed' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $settlement = Invoice::findOrFail($settlementProforma->fresh()->invoice_id);
        expect((float) $settlement->grand_total)->toBe($grossUp ? 7035175.88 : 7000000.0)
            ->and($settlement->status)->toBe('unpaid')
            ->and($settlement->dp_invoice_id)->toBe($invoice->id)
            ->and((float) Invoice::sum('grand_total'))->toBe($grossUp ? 10050251.26 : 10000000.0);
        $this->post(route('admin.invoices.work-orders.store', $settlement->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.work-orders.show', $spk->id));
        expect(WorkOrder::count())->toBe(1)->and(DeliveryOrder::count())->toBe(1);
    }
})->with([[true, '2026-10-12'], [false, '2026-10-19'], [false, '2026-11-03'], [true, '2026-10-12', true], [false, '2026-10-19', true]]);

it('calculates gross-up on the server from Lead items and can switch it off without double grossing', function () {
    $payload = array_replace($this->payload, ['lead_id' => $this->lead->id, 'pph_gross_up' => '1', 'pph_amount' => 999999, 'pph_rate' => 99, 'grand_total' => 1]);
    $payload['items']['item_0']['price'] = 3750000;
    $this->post(route('admin.quotes.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $quote = Quote::sole();
    expect((float) $quote->grand_total)->toBe(7537688.44)->and((float) $quote->pph_amount)->toBe(37688.44)->and((float) $quote->pph_rate)->toBe(0.5);
    $this->get(route('admin.quotes.edit', $quote->id))->assertOk()->assertSee('Gross-up PPh 0,5%');
    $payload['items'] = [$quote->items->sole()->id => $payload['items']['item_0']];
    $this->put(route('admin.quotes.update', $quote->id), $payload)->assertSessionHasNoErrors();
    expect((float) $quote->fresh()->grand_total)->toBe(7537688.44);
    $this->put(route('admin.quotes.update', $quote->id), array_replace($payload, ['pph_gross_up' => '0']))->assertSessionHasNoErrors();
    expect((float) $quote->fresh()->grand_total)->toBe(7500000.0)->and((float) $quote->fresh()->pph_amount)->toBe(0.0);
});

it('rejects an invalid gross-up choice without storing a quote', function () {
    $this->post(route('admin.quotes.store'), $this->payload + ['pph_gross_up' => 'yes'])->assertSessionHasErrors('pph_gross_up');
    expect(Quote::count())->toBe(0);
});
