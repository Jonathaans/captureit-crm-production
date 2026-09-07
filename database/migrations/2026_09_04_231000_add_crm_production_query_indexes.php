<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $indexes = [
        'quotes' => [
            'crm_quotes_document_lookup_idx' => ['quote_number'],
            'crm_quotes_project_event_idx' => ['project_code', 'event_date'],
            'crm_quotes_owner_created_idx' => ['user_id', 'created_at'],
        ],
        'invoices' => [
            'crm_invoices_document_lookup_idx' => ['invoice_number'],
            'crm_invoices_project_event_idx' => ['project_code', 'event_date'],
            'crm_invoices_status_due_idx' => ['status', 'due_at'],
            'crm_invoices_quote_relation_idx' => ['quote_id'],
            'crm_invoices_person_relation_idx' => ['person_id'],
        ],
        'work_orders' => [
            'crm_work_orders_document_lookup_idx' => ['work_order_number'],
            'crm_work_orders_invoice_status_idx' => ['invoice_id', 'status'],
            'crm_work_orders_event_idx' => ['event_date'],
        ],
        'delivery_orders' => [
            'crm_delivery_orders_document_lookup_idx' => ['delivery_order_number'],
            'crm_delivery_orders_invoice_status_idx' => ['invoice_id', 'status'],
            'crm_delivery_orders_project_event_idx' => ['project_code', 'event_date'],
            'crm_delivery_orders_work_order_idx' => ['work_order_id'],
        ],
        'purchase_orders' => [
            'crm_purchase_orders_document_lookup_idx' => ['po_number'],
            'crm_purchase_orders_invoice_status_idx' => ['invoice_id', 'status'],
            'crm_purchase_orders_vendor_status_idx' => ['vendor_id', 'status'],
            'crm_purchase_orders_paid_at_idx' => ['paid_at'],
        ],
        'expenses' => [
            'crm_expenses_invoice_date_idx' => ['invoice_id', 'expense_date'],
            'crm_expenses_created_at_idx' => ['created_at'],
        ],
        'inventory_stock_movements' => [
            'crm_movements_item_created_idx' => ['inventory_item_id', 'created_at'],
            'crm_movements_asset_created_idx' => ['inventory_asset_id', 'created_at'],
            'crm_movements_reference_idx' => ['reference_type', 'reference_id'],
            'crm_movements_type_created_idx' => ['movement_type', 'created_at'],
        ],
        'user_email_messages' => [
            'crm_email_account_received_idx' => ['account_id', 'received_at'],
            'crm_email_user_folder_received_idx' => ['user_id', 'folder', 'received_at'],
        ],
        'crm_notifications' => [
            'crm_notifications_open_user_idx' => ['user_id', 'resolved_at', 'read_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (! $this->allColumnsExist($table, $columns) || $this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if ($this->indexExists($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    private function allColumnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $table, string $name): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
