<?php

namespace Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\InvoiceCorrectionService;
use Webkul\Admin\Services\PhotoboothCatalogService;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\InvoiceItem;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\Invoice\Services\DeliveryOrderNumberService;
use Webkul\Invoice\Services\DeliveryOrderService;
use Webkul\Invoice\Services\DeliveryOrderWarehouseReleaseService;
use Webkul\Invoice\Services\InvoiceNumberHistory;
use Webkul\Product\Support\EquipmentQuantity;
use Webkul\Quote\Models\Quote;

/** In-memory database scenarios shared by Pest and the standalone smoke run. */
class CrmCorrectionScenarios
{
    public static function run(string|callable $scenario): void
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
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $container));
        $container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        try {
            self::check(DB::connection()->getDatabaseName() === ':memory:', 'Tests require an in-memory DB.');
            self::schema();
            is_string($scenario) ? self::$scenario() : $scenario();
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

    public static function catalogManualRequirements(): void
    {
        self::seedCatalog();
        DB::table('products')->insert(['id' => 86, 'name' => 'AI Generative', 'sku' => 'AI-NEW', 'category' => null]);
        $manualTemplateId = DB::table('product_equipment_templates')->insertGetId(['product_id' => 86, 'name' => 'Owner custom AI template', 'is_active' => true]);
        DB::table('product_equipment_template_items')->insert(['template_id' => $manualTemplateId, 'name' => 'Owner selected equipment', 'quantity' => 3, 'unit' => 'unit', 'notes' => 'Keep manual choices']);
        $manualRows = DB::table('product_equipment_template_items')->where('template_id', $manualTemplateId)->get()->toJson();
        DB::table('inventory_items')->whereIn('code', ['laptop', 'curtain_pole'])->delete();
        $service = new PhotoboothCatalogService;
        $mapping = [
            'templates' => ['ai_generative' => [8]],
            'inventory' => ['laptop' => ['manual' => true], 'curtain_pole' => ['manual' => true]],
            'requirements' => [6 => ['hologram_lens' => ['quantity' => 100, 'quantity_basis' => 'equipment']]],
        ];
        $before = DB::table('inventory_items')->count();
        $plan = $service->preview($mapping);
        self::check($plan['errors'] === [] && $plan['warnings'] !== [], 'Explicit manual requirements are reviewable without inventing masters.');
        $service->apply($mapping, $plan['fingerprint'], 7, 'Manual laptop choice and reviewed print quantities');
        self::check(DB::table('products')->where('id', 86)->value('sku') === 'PRD-0011', 'A newly added product joins the current sequential SKU list.');
        self::check(DB::table('products')->where('id', 86)->value('category') === 'Photobooth', 'The new product receives the requested category.');
        self::check(DB::table('product_equipment_templates')->where('id', $manualTemplateId)->value('name') === 'Owner custom AI template'
            && DB::table('product_equipment_template_items')->where('template_id', $manualTemplateId)->get()->toJson() === $manualRows, 'An unmapped new AI product retains its manually edited template, even with a matching product name.');
        $laptops = DB::table('product_equipment_template_items')->where('name', 'Device Laptop')->get();
        self::check($laptops->count() === 2, 'Both laptop templates keep their requirement.');
        foreach ($laptops as $row) {
            self::check($row->inventory_item_id === null && $row->requires_inventory == 1 && (float) $row->quantity === 1.0, 'Manual laptop must remain mandatory and unselected.');
        }
        $holoTemplate = DB::table('product_equipment_templates')->where('product_id', 6)->value('id');
        $lens = DB::table('product_equipment_template_items')->where('template_id', $holoTemplate)->where('name', 'Lensa Hologram')->first();
        self::check((float) $lens->quantity === 100.0 && $lens->quantity_basis === 'equipment', 'A 100-print package needs 100 sheets per package.');
        self::check(DB::table('inventory_items')->count() === $before && DB::table('inventory_assets')->count() === 0, 'Manual mapping must not create stock or physical assets.');
        $invalid = $mapping;
        $invalid['requirements'][6]['camera_700d'] = ['quantity_basis' => 'sales'];
        self::check($service->preview($invalid)['errors'] !== [], 'Sold print quantity must not multiply a serialized camera.');
        $invalid = $mapping;
        $invalid['requirements'][999] = ['hologram_lens' => ['quantity' => 100]];
        self::check($service->preview($invalid)['errors'] !== [], 'Overrides may only target mapped products.');
        $invalid = $mapping;
        $invalid['inventory']['laptop'] = ['manual' => true, 'create' => ['code' => 'BAD', 'warehouse_id' => 1]];
        self::check($service->preview($invalid)['errors'] !== [], 'Manual selection cannot silently hide a create action.');
    }

    public static function equipmentQuantityRules(): void
    {
        self::check(EquipmentQuantity::forLine(['quantity' => 100, 'quantity_basis' => 'equipment'], ['unit' => 'pcs', 'quantity' => 2, 'equipment_quantity' => 2]) === 200.0, 'Two 100-print packages need 200 sheets.');
        self::check(EquipmentQuantity::forLine(['quantity' => 1, 'quantity_basis' => 'sales'], ['unit' => 'pcs', 'quantity' => 100, 'equipment_quantity' => 1]) === 100.0, 'An add-on uses sold sheets, not the number of booths.');
        self::check(EquipmentQuantity::forLine(['quantity' => 1, 'quantity_basis' => 'sales'], ['unit' => 'day', 'quantity' => 3]) === 0.0, 'Rental days cannot be guessed as sheet count.');
        self::check(EquipmentQuantity::forLine(['quantity' => 1, 'quantity_basis' => 'manual'], ['unit' => 'pcs', 'quantity' => 1]) === 0.0, 'Custom orders require explicit print quantity.');
        self::check(EquipmentQuantity::forLine(['quantity' => 2], ['unit' => null, 'day' => 3, 'quantity' => 2]) === 4.0, 'Historical physical equipment calculation stays unchanged.');
    }

    public static function deliveryRequirementFlow(): void
    {
        self::seedCatalog();
        DB::table('products')->insert(['id' => 11, 'name' => 'Hologram Custom', 'sku' => 'HOLO-CUSTOM', 'category' => null]);
        $mapping = [
            'templates' => ['hologram' => [6, 11]],
            'inventory' => ['laptop' => ['manual' => true]],
            'requirements' => [6 => ['hologram_lens' => ['quantity' => 100, 'quantity_basis' => 'equipment']]],
        ];
        $service = new PhotoboothCatalogService;
        $plan = $service->preview($mapping);
        $service->apply($mapping, $plan['fingerprint'], 7, 'Test complete warehouse requirement flow');
        $invoice = new Invoice;
        $invoice->setRelation('items', new Collection([
            new InvoiceItem(['product_id' => 6, 'quantity' => 2, 'unit' => 'pcs', 'equipment_quantity' => 2]),
            new InvoiceItem(['product_id' => 9, 'quantity' => 50, 'unit' => 'pcs', 'equipment_quantity' => 1]),
            new InvoiceItem(['product_id' => 7, 'quantity' => 1, 'unit' => 'pcs', 'equipment_quantity' => 1]),
            new InvoiceItem(['product_id' => 11, 'quantity' => 1, 'unit' => 'pcs', 'equipment_quantity' => 1]),
        ]));
        // Concrete relation avoids needing the application's Concord proxy boot.
        $delivery = new class extends DeliveryOrder
        {
            public function items()
            {
                return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id')->orderBy('sort_order');
            }
        };
        $delivery->setTable('delivery_orders')->forceFill(['id' => 700, 'status' => 'draft', 'delivery_order_number' => 'SJ-TEST'])->save();
        $copier = new class(new DeliveryOrderNumberService) extends DeliveryOrderService
        {
            public function copyForTest(Invoice $invoice, DeliveryOrder $delivery): void
            {
                $this->copyEquipmentFromInvoice($invoice, $delivery);
            }
        };
        $copier->copyForTest($invoice, $delivery);
        $rows = DeliveryOrderItem::where('delivery_order_id', 700)->with('inventoryItem')->get();
        $lenses = $rows->where('name', 'Lensa Hologram');
        self::check($lenses->count() === 2 && (float) $lenses->sum('quantity') === 250.0, '200 package sheets plus 50 add-on sheets stay separate from unresolved custom sheets.');
        self::check((float) $rows->where('name', 'Camera 700D')->sum('quantity') === 4.0, 'Print add-ons must not multiply camera requirements.');
        $pending = $lenses->first(fn ($row) => (float) $row->quantity === 0.0);
        self::check($pending !== null && $pending->requires_inventory, 'Custom sheet count must survive copying as mandatory and unresolved.');
        $laptop = $rows->firstWhere('name', 'Device Laptop');
        $allocation = new DeliveryOrderInventoryAllocationService;
        $delivery->setRelation('items', new Collection([$laptop]));
        self::check(! $allocation->isComplete($delivery) && $allocation->incompleteItemNames($delivery) === ['Device Laptop'], 'An unselected laptop must block release.');
        $delivery->setRelation('items', new Collection([$pending]));
        self::check(! $allocation->isComplete($delivery), 'A linked lenticular master with unknown count must also block release.');
        try {
            $allocation->syncQuantity($delivery, $pending, 1);
            throw new RuntimeException('Pending quantity unexpectedly allocated.');
        } catch (ValidationException $exception) {
            self::check(isset($exception->errors()['quantity']), 'Pending quantity gives an actionable validation error.');
        }
        // Keep only the unresolved row so the release guard is tested directly.
        DeliveryOrderItem::where('delivery_order_id', 700)->where('id', '!=', $pending->id)->delete();
        try {
            (new DeliveryOrderWarehouseReleaseService)->releaseOnIssue($delivery);
            throw new RuntimeException('Pending requirement unexpectedly released.');
        } catch (ValidationException $exception) {
            self::check(isset($exception->errors()['inventory']), 'Warehouse release rechecks incomplete manual requirements.');
        }
        $pending->update(['quantity' => 100]);
        DB::table('inventory_items')->where('id', $pending->inventory_item_id)->update(['quantity_on_hand' => 500]);
        $delivery->unsetRelation('items');
        $allocation->syncQuantity($delivery, $pending->fresh(), 100, 7);
        self::check($allocation->isComplete($delivery), 'Filling the ordered sheet count and allocation resolves the requirement.');
        (new DeliveryOrderWarehouseReleaseService)->releaseOnIssue($delivery, 7);
        self::check((float) DB::table('inventory_items')->where('id', $pending->inventory_item_id)->value('quantity_on_hand') === 400.0, 'Releasing 100 lenticular sheets deducts exactly 100 from stock.');
        self::check((float) DB::table('inventory_stock_movements')->where('movement_type', 'out')->sum('quantity') === 100.0, 'The stock movement records the actual sheet quantity.');
        $legacy = new DeliveryOrderItem(['name' => 'Legacy text note', 'quantity' => 1]);
        $legacy->setRelation('inventoryItem', null);
        $delivery->setRelation('items', new Collection([$legacy]));
        self::check($allocation->isComplete($delivery), 'Historical untracked text rows keep their existing behavior.');
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
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->increments('id');
                $t->unsignedInteger('invoice_id')->nullable();
                if ($table === 'delivery_orders') {
                    $t->string('delivery_order_number')->nullable();
                    $t->string('status')->default('draft');
                    $t->text('notes')->nullable();
                    $t->string('recipient_name')->nullable();
                    $t->timestamps();
                }
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
        Schema::create('warehouse_locations', fn (Blueprint $t) => $t->increments('id'));
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
        Schema::create('inventory_assets', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('inventory_item_id');
            $t->unsignedInteger('warehouse_id')->default(1);
            $t->string('asset_code')->unique();
            $t->string('status')->default('available');
            $t->timestamps();
        });
        Schema::create('quote_items', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('product_id');
            $t->string('sku');
        });
        Schema::create('delivery_order_items', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('delivery_order_id')->nullable();
            $t->unsignedInteger('product_id')->nullable();
            $t->unsignedInteger('inventory_item_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->decimal('quantity', 12, 2);
            $t->string('unit')->nullable();
            $t->text('notes')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        foreach (['2026_08_27_133453_create_product_equipment_templates_table.php', '2026_08_27_133504_create_product_equipment_template_items_table.php', '2026_08_28_140100_add_inventory_item_id_to_product_equipment_template_items.php', '2026_08_28_120520_create_inventory_stock_movements_table.php', '2026_08_28_145000_create_delivery_order_inventory_allocations_table.php', '2026_08_28_153500_add_picking_out_to_delivery_order_inventory_allocations.php', '2026_10_07_000000_create_crm_data_corrections_table.php', '2026_10_07_010000_add_equipment_requirement_rules.php'] as $file) {
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
