<?php

use Webkul\Core\Support\SalesLineItem;

it('bills quantity once for either unit and ignores an obsolete day multiplier', function (string $unit) {
    $item = SalesLineItem::prepare(['quantity' => 3, 'unit' => $unit, 'day' => 7, 'price' => 500000]);

    expect($item['amount'])->toBe(1500000.0)
        ->and($item['day'])->toBe(1)
        ->and($item['equipment_quantity'])->toBe($unit === 'day' ? 1.0 : 3.0);
})->with(['pcs', 'day']);

it('preserves fixed discounts tax and allocated rounding when reopening invoices', function () {
    $source = ['unit' => 'day', 'quantity' => 3, 'price' => 33.3333, 'total' => 100, 'discount_amount' => 7, 'tax_amount' => 10.23];
    $saved = SalesLineItem::prepare(SalesLineItem::invoiceDisplay($source), $source);

    expect(SalesLineItem::invoiceAmounts($saved, $source))
        ->toBe(['base' => 100.0, 'discount' => 7.0, 'tax' => 10.23]);
});

it('rejects unknown billing units', function () {
    SalesLineItem::prepare(['quantity' => 1, 'price' => 100, 'unit' => 'week']);
})->throws(InvalidArgumentException::class);
