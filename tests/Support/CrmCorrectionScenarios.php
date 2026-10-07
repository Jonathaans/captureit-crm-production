<?php

namespace Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\InvoiceCorrectionService;
use Webkul\Admin\Services\PhotoboothCatalogService;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Services\InvoiceNumberHistory;
use Webkul\Quote\Models\Quote;

/** In-memory database scenarios shared by Pest and the standalone smoke run. */
class CrmCorrectionScenarios
{
    public static function run(string $scenario): void
    {
        $oldApp = Facade::getFacadeApplication();
        $oldContainer = Container::getInstance();
        $oldResolver = Model::getConnectionResolver();
        $oldDispatcher = Model::getEventDispatcher();
        $container = new Container;
        $capsule = new Manager($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $capsule->bootEloquent();
        Model::unsetEventDispatcher();
        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        try {
            self::check(DB::connection()->getDatabaseName() === ':memory:', 'Tests require an in-memory DB.');
            self::schema();
            self::$scenario();
        } finally {
            $capsule->getConnection()->disconnect();
            if ($oldResolver) {
                Model::setConnectionResolver($oldResolver);
            } else {
                Model::unsetConnectionResolver();
            }
            if ($oldDispatcher) {
                Model::setEventDispatcher($oldDispatcher);
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($oldApp);
            Container::setInstance($oldContainer);
        }
    }

    public static function invoiceCorrection(): void
    {
        self::seedInvoice();
        $service = new InvoiceCorrectionService;
        $preview = $service->preview('INV 2610-0010');
        self::check(DB::table('payments')->count() === 3, 'Preview must not mutate payments.');
        $quote = Quote::findOrFail(1);
        self::check(! (new FlexibleQuoteBillingService)->summarize($quote)['eligibility']['down_payment'], 'Existing DP blocks another DP.');
        $before = (float) Payment::whereYear('paid_at', 2026)->whereMonth('paid_at', 10)->sum('amount');
        $result = $service->apply('INV 2610-0010', $preview['fingerprint'], 'Finance salah input invoice dan payment', 7);
        $after = (float) Payment::whereYear('paid_at', 2026)->whereMonth('paid_at', 10)->sum('amount');
        self::check($before - $after === 3875000.0, 'Cash received must decrease by actual removed payments.');
        self::check($result['payment_count'] === 2 && $result['payment_removed_total'] === 3875000.0, 'Both partial entries must be corrected.');
        self::check(DB::table('invoices')->count() === 1 && DB::table('payments')->count() === 1, 'Unrelated invoice and payment must survive.');
        self::check(DB::table('quotes')->count() === 2, 'Quotes must survive.');
        self::check((new FlexibleQuoteBillingService)->summarize($quote)['eligibility']['down_payment'], 'Quote must become eligible for a new DP.');
        self::check((new InvoiceNumberHistory)->lastSequence('INV 2610-') === 10, 'Removed highest number must remain reserved.');
        $archive = json_decode(DB::table('crm_data_corrections')->value('snapshot'), true, 512, JSON_THROW_ON_ERROR);
        self::check(count($archive['payments']) === 2 && count($archive['items']) === 1, 'Complete financial input must remain in the archive.');
        self::fails(fn () => $service->apply('INV 2610-0010', $preview['fingerprint'], 'Repeat correction', 7));
        self::check(DB::table('crm_data_corrections')->count() === 1, 'A repeat must not remove money again.');
    }

    public static function invoiceGuards(): void
    {
        self::seedInvoice();
        $service = new InvoiceCorrectionService;
        $preview = $service->preview('INV 2610-0010');
        DB::table('payments')->where('id', 1)->update(['amount' => 2000001]);
        self::fails(fn () => $service->apply('INV 2610-0010', $preview['fingerprint'], 'Correction test', 7));
        foreach (['expenses', 'work_orders', 'delivery_orders', 'purchase_orders'] as $table) {
            DB::table($table)->insert(['invoice_id' => 10]);
            $fresh = $service->preview('INV 2610-0010');
            self::fails(fn () => $service->apply('INV 2610-0010', $fresh['fingerprint'], 'Correction test', 7));
            DB::table($table)->delete();
        }
        DB::table('invoices')->where('id', 9)->update(['dp_invoice_id' => 10]);
        $fresh = $service->preview('INV 2610-0010');
        self::fails(fn () => $service->apply('INV 2610-0010', $fresh['fingerprint'], 'Correction test', 7));
        self::check(DB::table('payments')->count() === 3 && DB::table('crm_data_corrections')->count() === 0, 'Rejected corrections must have no side effects.');
    }

    public static function invoiceRollback(): void
    {
        self::seedInvoice();
        $service = new InvoiceCorrectionService;
        $preview = $service->preview('INV 2610-0010');
        DB::unprepared("CREATE TRIGGER fail_invoice_delete BEFORE DELETE ON invoices BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        self::fails(fn () => $service->apply('INV 2610-0010', $preview['fingerprint'], 'Correction test', 7));
        self::check(DB::table('payments')->count() === 3 && DB::table('invoice_items')->count() === 1, 'Payments and items must roll back on a late failure.');
        self::check(DB::table('crm_data_corrections')->count() === 0, 'No successful correction record on a failed delete.');
    }

    public static function catalogCorrection(): void
    {
        self::seedCatalog();
        // Reviewed warehouse names/units must resolve without changing masters.
        foreach (['lighting_stand' => 'TAKARA', 'magic_arm' => 'CLAMP ARM', 'magic_clamp' => 'CLAMP KECIL', 'flash' => 'FLASH YN 560 III'] as $code => $name) {
            DB::table('inventory_items')->where('code', $code)->update(['name' => $name]);
        }
        DB::table('inventory_items')->where('code', 'tl120')->update(['unit' => 'unit']);
        DB::table('inventory_items')->where('code', 'hologram_lens')->update(['name' => 'Lensa Lenticular', 'tracking_type' => 'quantity', 'unit' => 'lembar']);
        DB::table('inventory_items')->insert(['code' => 'LENS FISHEYE', 'name' => 'LENSA FISHEYE CANON', 'tracking_type' => 'serialized', 'unit' => 'unit', 'warehouse_id' => 1, 'is_active' => true, 'quantity_on_hand' => 0]);
        $service = new PhotoboothCatalogService;
        $preview = $service->preview();
        self::check($preview['errors'] === [], 'Seeded catalog should resolve without errors.');
        self::check(DB::table('products')->where('id', 1)->value('sku') === 'PRD-0002', 'Preview is read-only.');
        $result = $service->apply([], $preview['fingerprint'], 7, 'Catalog requested by owner');
        self::check($result['products_updated'] === 10, 'All sale products must be included.');
        self::check(DB::table('products')->where('id', 1)->value('sku') === 'PRD-0001', 'SKU swap must resolve a pre-existing collision.');
        self::check(DB::table('products')->where('category', 'Photobooth')->count() === 10, 'All products receive the requested category.');
        self::check(DB::table('attribute_values')->where('entity_id', 1)->where('attribute_id', 1)->value('text_value') === 'PRD-0001', 'EAV SKU must match products.sku.');
        self::check(DB::table('attribute_values')->where('entity_id', 10)->where('attribute_id', 2)->value('text_value') === 'Photobooth', 'Category EAV must agree too.');
        self::check(DB::table('quote_items')->value('sku') === 'PRD-0002', 'Historical document snapshots must keep their original SKU.');
        self::check(DB::table('delivery_order_items')->value('quantity') === 17, 'Existing delivery requirements must remain untouched.');
        $definition = $service->definition();
        foreach ($definition['templates'] as $key => $template) {
            $templateId = DB::table('product_equipment_templates')->where('product_id', $preview['templates'][$key][0])->value('id');
            $rows = DB::table('product_equipment_template_items')->where('template_id', $templateId)->orderBy('sort_order')->get();
            self::check($rows->count() === count($template['items']), 'No extra template requirements: '.$key);
            foreach ($rows as $row) {
                $inventory = DB::table('inventory_items')->where('id', $row->inventory_item_id)->first();
                self::check($inventory !== null, 'Every requirement must resolve to inventory.');
            }
        }
        $classicId = DB::table('product_equipment_templates')->where('product_id', 1)->value('id');
        $cable = DB::table('product_equipment_template_items')->where('template_id', $classicId)->where('name', 'Kabel Roll')->first();
        self::check((float) $cable->quantity === 3.0, 'Classic needs three serialized cable rolls.');
        self::check(DB::table('inventory_items')->where('id', $cable->inventory_item_id)->value('tracking_type') === 'serialized', 'Cable rolls are reusable serialized assets.');
        self::check(DB::table('product_equipment_template_items')->where('name', 'Lensa Hologram')->count() === 2, 'Only Hologram and the optional add-on require this lens.');
        $aiId = DB::table('product_equipment_templates')->where('product_id', $preview['templates']['ai_generative'][0])->value('id');
        self::check((float) DB::table('product_equipment_template_items')->where('template_id', $aiId)->where('name', 'Stand Lighting')->value('quantity') === 5.0, 'AI Generative requires five lighting stands.');
        $boxId = DB::table('product_equipment_templates')->where('product_id', $preview['templates']['high_angle_box'][0])->value('id');
        self::check(! DB::table('product_equipment_template_items')->where('template_id', $boxId)->where('name', 'Lensa Wide')->exists(), 'High Angle Box does not include an unrequested wide lens.');
        self::check(DB::table('inventory_assets')->count() === 0 && DB::table('inventory_items')->sum('quantity_on_hand') == 0, 'Requirements must not invent physical assets or opening stock.');
        $next = $service->preview();
        $service->apply([], $next['fingerprint'], 7, 'Repeat maintenance test');
        self::check(DB::table('product_equipment_templates')->count() === 9, 'Reapplying must not duplicate templates.');
    }

    public static function catalogGuards(): void
    {
        self::seedCatalog();
        $service = new PhotoboothCatalogService;
        $preview = $service->preview();
        DB::table('inventory_items')->where('name', 'Kabel Roll')->update(['tracking_type' => 'quantity']);
        self::fails(fn () => $service->apply([], $preview['fingerprint'], 7, 'Catalog correction'));
        $fresh = $service->preview();
        self::check(count($fresh['errors']) > 0, 'A serialized cable roll cannot silently become consumable.');
        self::fails(fn () => $service->apply([], $fresh['fingerprint'], 7, 'Catalog correction'));
        self::check(DB::table('products')->where('id', 1)->value('sku') === 'PRD-0002', 'Invalid mappings cannot partially renumber products.');
        DB::table('inventory_items')->where('name', 'Kabel Roll')->update(['tracking_type' => 'serialized']);
        DB::table('inventory_items')->insert(['code' => 'DUPLICATE-CAMERA', 'name' => 'Canon 700D', 'tracking_type' => 'serialized', 'unit' => 'unit', 'warehouse_id' => 1, 'is_active' => true, 'quantity_on_hand' => 0]);
        $ambiguous = $service->preview();
        self::check(count($ambiguous['inventory']['camera_700d']['candidates']) === 2, 'Ambiguous master matches require explicit mapping.');
        self::fails(fn () => $service->apply([], $ambiguous['fingerprint'], 7, 'Ambiguous catalog correction'));
    }

    public static function catalogRollback(): void
    {
        self::seedCatalog();
        $service = new PhotoboothCatalogService;
        $preview = $service->preview();
        DB::unprepared("CREATE TRIGGER fail_template_insert BEFORE INSERT ON product_equipment_template_items WHEN NEW.quantity = 5 BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        self::fails(fn () => $service->apply([], $preview['fingerprint'], 7, 'Catalog correction'));
        self::check(DB::table('products')->where('id', 1)->value('sku') === 'PRD-0002', 'SKU changes must roll back with template failure.');
        self::check(DB::table('attribute_values')->where('entity_id', 1)->value('text_value') === 'PRD-0002', 'EAV changes must roll back too.');
        self::check(DB::table('product_equipment_templates')->count() === 0 && DB::table('crm_data_corrections')->count() === 0, 'Late failure must roll back templates and audit together.');
    }

    public static function catalogNewMaster(): void
    {
        self::seedCatalog();
        DB::table('inventory_items')->where('name', 'Camera 700D')->delete();
        $service = new PhotoboothCatalogService;
        self::check($service->preview()['errors'] !== [], 'Missing master must not be created by guesswork.');
        $mapping = ['inventory' => ['camera_700d' => ['create' => ['code' => 'CAM-700D', 'warehouse_id' => 1]]]];
        $plan = $service->preview($mapping);
        self::check($plan['errors'] === [], 'Explicit new-master mapping should validate.');
        $service->apply($mapping, $plan['fingerprint'], 7, 'Create reviewed missing master');
        $master = DB::table('inventory_items')->where('code', 'CAM-700D')->first();
        self::check($master->tracking_type === 'serialized' && (float) $master->quantity_on_hand === 0.0, 'Create only a serialized master, with no fabricated stock.');
    }

    private static function seedInvoice(): void
    {
        DB::table('quotes')->insert([['id' => 1, 'quote_number' => 'QT 2610-0023', 'grand_total' => 13000000], ['id' => 2, 'quote_number' => 'OTHER', 'grand_total' => 1000000]]);
        DB::table('invoices')->insert([
            ['id' => 10, 'invoice_number' => 'INV 2610-0010', 'quote_id' => 1, 'grand_total' => 3875000, 'paid_amount' => 3875000, 'billing_type' => 'down_payment', 'status' => 'paid', 'event_status' => 'confirm'],
            ['id' => 9, 'invoice_number' => 'INV 2610-0009', 'quote_id' => 2, 'grand_total' => 1000000, 'paid_amount' => 1000000, 'billing_type' => 'full_payment', 'status' => 'paid', 'event_status' => 'confirm'],
        ]);
        DB::table('payments')->insert([
            ['id' => 1, 'invoice_id' => 10, 'amount' => 2000000, 'paid_at' => '2026-10-06 09:00:00'],
            ['id' => 2, 'invoice_id' => 10, 'amount' => 1875000, 'paid_at' => '2026-10-06 10:00:00'],
            ['id' => 3, 'invoice_id' => 9, 'amount' => 1000000, 'paid_at' => '2026-10-06 10:00:00'],
        ]);
        DB::table('invoice_items')->insert(['invoice_id' => 10]);
    }

    private static function seedCatalog(): void
    {
        $definition = (new PhotoboothCatalogService)->definition();
        foreach (array_values($definition['templates']) as $index => $template) {
            $id = $index + 1;
            $sku = match ($id) {
                1 => 'PRD-0002', 2 => 'PRD-0001', default => 'OLD-'.$id
            };
            DB::table('products')->insert(['id' => $id, 'name' => $template['product_names'][0], 'sku' => $sku, 'category' => null]);
            DB::table('attribute_values')->insert(['attribute_id' => 1, 'entity_type' => 'products', 'entity_id' => $id, 'text_value' => $sku]);
        }
        DB::table('products')->insert(['id' => 10, 'name' => 'Videographer - Online', 'sku' => 'VG-02', 'category' => null]);
        foreach ($definition['inventory'] as $key => $item) {
            DB::table('inventory_items')->insert(['name' => $item['name'], 'code' => $key, 'tracking_type' => $item['tracking_type'], 'unit' => $item['unit'], 'is_active' => 1, 'warehouse_id' => 1, 'quantity_on_hand' => 0]);
        }
        DB::table('quote_items')->insert(['product_id' => 1, 'sku' => 'PRD-0002']);
        DB::table('delivery_order_items')->insert(['quantity' => 17]);
    }

    private static function schema(): void
    {
        Schema::create('users', fn (Blueprint $t) => $t->increments('id'));
        DB::table('users')->insert(['id' => 7]);
        Schema::create('quotes', function (Blueprint $t) {
            $t->increments('id');
            $t->string('quote_number');
            $t->string('project_code')->default('TEST');
            $t->string('subject')->default('Test event');
            $t->decimal('grand_total', 12, 4);
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->increments('id');
            $t->string('invoice_number')->unique();
            $t->unsignedInteger('quote_id')->nullable();
            $t->integer('dp_invoice_id')->nullable();
            $t->string('billing_type')->nullable();
            $t->string('status');
            $t->string('event_status')->nullable();
            $t->decimal('grand_total', 12, 4);
            $t->decimal('paid_amount', 12, 4);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id');
            $t->decimal('amount', 12, 4);
            $t->dateTime('paid_at');
            $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
        foreach (['invoice_items', 'expenses', 'work_orders', 'delivery_orders', 'purchase_orders'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('invoice_id');
                $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            });
        }
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('sku')->unique();
            $t->string('category')->nullable();
            $t->timestamps();
        });
        Schema::create('attributes', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code');
            $t->string('type');
            $t->string('entity_type');
        });
        DB::table('attributes')->insert([['id' => 1, 'code' => 'sku', 'type' => 'text', 'entity_type' => 'products'], ['id' => 2, 'code' => 'category', 'type' => 'text', 'entity_type' => 'products']]);
        Schema::create('attribute_values', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('attribute_id');
            $t->string('entity_type');
            $t->integer('entity_id');
            $t->text('text_value');
            $t->unique(['attribute_id', 'entity_type', 'entity_id']);
        });
        Schema::create('warehouses', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
        });
        DB::table('warehouses')->insert(['id' => 1, 'name' => 'Warehouse']);
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code')->unique();
            $t->string('name');
            $t->string('tracking_type');
            $t->string('unit');
            $t->boolean('is_active');
            $t->integer('warehouse_id');
            $t->decimal('quantity_on_hand', 12, 2);
            $t->decimal('minimum_stock', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('inventory_assets', fn (Blueprint $t) => $t->increments('id'));
        Schema::create('quote_items', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('product_id');
            $t->string('sku');
        });
        Schema::create('delivery_order_items', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('quantity');
        });
        foreach (['2026_08_27_133453_create_product_equipment_templates_table.php', '2026_08_27_133504_create_product_equipment_template_items_table.php', '2026_08_28_140100_add_inventory_item_id_to_product_equipment_template_items.php', '2026_10_07_000000_create_crm_data_corrections_table.php'] as $file) {
            (require __DIR__.'/../../database/migrations/'.$file)->up();
        }
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private static function fails(callable $operation): void
    {
        try {
            $operation();
        } catch (\Exception) {
            return;
        }
        throw new RuntimeException('Expected the operation to be rejected.');
    }
}
