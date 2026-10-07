<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmCatalogReviewCommand extends Command
{
    protected $signature = 'crm:catalog-review';

    protected $description = 'Read-only product, inventory and template inventory for catalog reconciliation.';

    public function handle(): int
    {
        $data = [
            'products' => DB::table('products')->orderBy('id')->get(['id', 'sku', 'name', 'category']),
            'inventory' => DB::table('inventory_items')->orderBy('id')->get(['id', 'code', 'name', 'tracking_type', 'unit', 'is_active', 'quantity_on_hand']),
            'asset_counts' => DB::table('inventory_assets')->selectRaw('inventory_item_id, COUNT(*) as asset_count')->groupBy('inventory_item_id')->get(),
            'warehouses' => DB::table('warehouses')->orderBy('id')->get(['id', 'name']),
            'templates' => DB::table('product_equipment_templates')->orderBy('id')->get(['id', 'product_id', 'name', 'is_active']),
            'template_items' => DB::table('product_equipment_template_items')->orderBy('template_id')->orderBy('sort_order')->get(['template_id', 'inventory_item_id', 'name', 'quantity', 'unit']),
        ];
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
