<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('lets users edit quote item days and recalculates line and quote totals', function (): void {
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php')
    );
    $createView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );
    $itemModel = file_get_contents(
        base_path('packages/Webkul/Quote/src/Models/QuoteItem.php')
    );
    $invoiceService = file_get_contents(
        base_path('packages/Webkul/Invoice/src/Services/InvoiceService.php')
    );
    $migration = file_get_contents(
        base_path('database/migrations/2026_08_24_153223_add_description_and_day_to_quote_and_invoice_items_tables.php')
    );

    expect($view)
        ->toContain("::name=\"`\${inputName}[day]`\"")
        ->toContain('rules="required|integer|min:1"')
        ->toContain('product.price * product.quantity * (product.day || 1)')
        ->toContain('(this.parseDecimal(product.day) || 1)');

    expect($controller)
        ->toContain("'items.*.day' => 'required|integer|min:1'")
        ->toContain("'day' => max(1, (int) (\$product->day ?? 1)),");

    expect($createView)
        ->toContain('Day')
        ->toContain('rules="required|integer|min:1"');

    expect($itemModel)->toContain("'day'");

    expect($invoiceService)
        ->toContain("'day' =>\n                        \$item->day ?? 1,");

    expect($migration)
        ->toContain("unsignedInteger('day')")
        ->toContain('->default(1)');
});
