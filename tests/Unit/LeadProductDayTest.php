<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('stores day for lead products and carries it into quotes', function (): void {
    $createView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/leads/common/products.blade.php')
    );
    $detailView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/leads/view/products.blade.php')
    );
    $leadForm = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Requests/LeadForm.php')
    );
    $leadController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php')
    );
    $leadRepository = file_get_contents(
        base_path('packages/Webkul/Lead/src/Repositories/LeadRepository.php')
    );
    $leadProductModel = file_get_contents(
        base_path('packages/Webkul/Lead/src/Models/Product.php')
    );
    $quoteController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );
    $migration = file_get_contents(
        base_path('packages/Webkul/Lead/src/Database/Migrations/2026_10_01_000000_add_day_to_lead_products_table.php')
    );

    expect($createView)
        ->toContain('Day')
        ->toContain('`${inputName}[day]`')
        ->toContain('product.price * product.quantity * (product.day || 1)')
        ->toContain('day: 1,');

    expect($detailView)
        ->toContain('::name="\'day\'"')
        ->toContain('handleDayChange')
        ->toContain('const day = parseInt(this.product.day, 10) || 1;')
        ->toContain('price * quantity * day');

    expect($leadForm)->toContain("'products.*.day' => 'nullable|integer|min:1'");

    expect($leadController)
        ->toContain("request()->input('day', 1)")
        ->toContain('$price * $quantity * $day');

    expect($leadRepository)
        ->toContain("(\$product['day'] ?? 1)")
        ->toContain("(\$productInputs['day'] ?? 1)")
        ->toContain("'amount' => \$product['price'] * \$product['quantity'] * \$day");

    expect($leadProductModel)->toContain("'day'");

    expect($quoteController)
        ->toContain("'day' => max(1, (int) (\$product->day ?? 1))")
        ->toContain("'total' => \$price * \$quantity * max(1, (int) (\$product->day ?? 1))");

    expect($migration)
        ->toContain("unsignedInteger('day')")
        ->toContain('->default(1)');
});
