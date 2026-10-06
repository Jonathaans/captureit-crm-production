<?php

/** Read-only preflight; run from the project root, including via php stdin. */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli' || ! is_file(getcwd().'/artisan')) {
    fwrite(STDERR, "Jalankan dari folder root CRM melalui terminal.\n");
    exit(1);
}
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$review = [
    'products' => DB::table('products')->orderBy('id')->get(['id', 'sku', 'name', 'category']),
    'inventory' => DB::table('inventory_items')->orderBy('id')->get(['id', 'code', 'name', 'tracking_type', 'unit', 'is_active', 'quantity_on_hand']),
    'asset_counts' => DB::table('inventory_assets')->selectRaw('inventory_item_id, COUNT(*) as asset_count')->groupBy('inventory_item_id')->get(),
    'warehouses' => DB::table('warehouses')->orderBy('id')->get(['id', 'name']),
    'templates' => DB::table('product_equipment_templates')->orderBy('id')->get(['id', 'product_id', 'name', 'is_active']),
];
$invoice = DB::table('invoices')->where('invoice_number', 'INV 2610-0010')->first([
    'id', 'invoice_number', 'quote_id', 'project_code', 'grand_total', 'paid_amount', 'status', 'event_status', 'billing_type',
]);
$review['invoice'] = $invoice;
if ($invoice) {
    $review['quote'] = DB::table('quotes')->where('id', $invoice->quote_id)->first(['id', 'quote_number', 'grand_total']);
    $review['payments'] = DB::table('payments')->where('invoice_id', $invoice->id)->get(['id', 'amount', 'paid_at']);
    foreach (['expenses', 'work_orders', 'delivery_orders', 'purchase_orders'] as $table) {
        $review['invoice_dependencies'][$table] = DB::table($table)->where('invoice_id', $invoice->id)->count();
    }
    $review['other_invoices_for_quote'] = DB::table('invoices')->where('quote_id', $invoice->quote_id)->where('id', '<>', $invoice->id)
        ->get(['id', 'invoice_number', 'billing_type', 'status', 'event_status', 'dp_invoice_id']);
}
echo json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
