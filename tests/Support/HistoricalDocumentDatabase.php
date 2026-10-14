<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Isolated schemas for real HTTP and service workflow tests; never uses production data. */
class HistoricalDocumentDatabase
{
    public static function createFinancialSchema(): void
    {
        foreach (glob(base_path('packages/Webkul/Attribute/src/Database/Migrations/*.php')) as $file) {
            if (! str_contains($file, '2025_')) {
                (require $file)->up();
            }
        }
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
        (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
        (require base_path('database/migrations/2026_09_01_150000_create_financial_period_locks_table.php'))->up();
        (require base_path('database/migrations/2026_09_01_210000_create_work_orders_and_link_delivery_orders.php'))->up();

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('sku');
        });
        (require base_path('database/migrations/2026_10_13_150000_create_payment_voids_table.php'))->up();
        (require base_path('database/migrations/2026_10_14_080000_add_historical_document_import.php'))->up();
    }
}
