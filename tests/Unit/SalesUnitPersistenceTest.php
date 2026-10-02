<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Core\Support\SalesLineItem;
use Webkul\Invoice\Models\InvoiceItem;
use Webkul\Lead\Models\Product;
use Webkul\Quote\Models\QuoteItem;

uses(TestCase::class);

it('adds sales units without rewriting history and persists line units on create and edit', function () {
    $previous = DB::getDefaultConnection();
    config(['database.connections.sales_units_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::setDefaultConnection('sales_units_test');
    Schema::clearResolvedInstance('db.schema');

    try {
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('This test requires a temporary in-memory database.');
        }

        foreach (['products', 'lead_products', 'quote_items', 'invoice_items'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->integer('quantity')->default(1);
                $table->integer('day')->default(1);
                $table->decimal('price', 12, 4)->default(0);
                $table->decimal('total', 12, 4)->default(0);
                $table->decimal('amount', 12, 4)->default(0);
                $table->timestamps();
            });
            DB::table($name)->insert(['quantity' => 2, 'day' => 3, 'price' => 500000, 'total' => 3000000]);
        }

        $migration = require base_path('database/migrations/2026_10_01_100000_add_sales_units_to_products_and_document_items.php');
        $migration->up();

        foreach (['lead_products', 'quote_items', 'invoice_items'] as $name) {
            $old = DB::table($name)->first();
            expect($old->unit)->toBeNull()
                ->and((int) $old->day)->toBe(3)
                ->and((int) $old->quantity)->toBe(2)
                ->and((float) $old->total)->toBe(3000000.0);
        }

        foreach ([Product::class, QuoteItem::class, InvoiceItem::class] as $class) {
            $model = new $class;
            $model->fill(SalesLineItem::prepare(['unit' => 'day', 'quantity' => 3, 'price' => 500000]));
            $model->save();
            $model->refresh();

            expect($model->unit)->toBe('day')
                ->and((int) $model->day)->toBe(1)
                ->and((int) $model->quantity)->toBe(3)
                ->and((float) $model->equipment_quantity)->toBe(1.0);

            $model->fill(SalesLineItem::prepare(['unit' => 'pcs', 'quantity' => 4, 'price' => 500000], $model->getAttributes()));
            $model->save();
            $model->refresh();

            expect($model->unit)->toBe('pcs')
                ->and((float) $model->equipment_quantity)->toBe(4.0);
        }
    } finally {
        DB::purge('sales_units_test');
        DB::setDefaultConnection($previous);
        Schema::clearResolvedInstance('db.schema');
    }
});
