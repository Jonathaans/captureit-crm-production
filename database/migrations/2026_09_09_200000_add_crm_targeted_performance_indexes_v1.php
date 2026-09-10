<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1 */
return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $indexes = [
        'internal_messages' => [
            'crm_chat_latest_v1_idx' => ['conversation_id', 'deleted_at', 'id'],
            'crm_chat_unread_v1_idx' => ['conversation_id', 'deleted_at', 'user_id', 'id'],
            'crm_chat_edited_v1_idx' => ['conversation_id', 'deleted_at', 'edited_at', 'id'],
        ],
        'internal_conversation_members' => [
            'crm_chat_member_user_v1_idx' => ['user_id', 'conversation_id'],
        ],
        'invoice_items' => [
            'crm_invoice_item_product_v1_idx' => ['invoice_id', 'name'],
        ],
        'invoices' => [
            'crm_invoice_business_v1_idx' => ['business_unit', 'id'],
            'crm_invoice_owner_v1_idx' => ['user_id', 'id'],
            'crm_invoice_person_v1_idx' => ['person_id', 'id'],
            'crm_invoice_issued_v1_idx' => ['issued_at', 'id'],
            'crm_invoice_event_v1_idx' => ['event_status', 'id'],
        ],
        'quotes' => [
            'crm_quote_business_v1_idx' => ['business_unit', 'id'],
            'crm_quote_person_v1_idx' => ['person_id', 'id'],
            'crm_quote_expired_v1_idx' => ['expired_at', 'id'],
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