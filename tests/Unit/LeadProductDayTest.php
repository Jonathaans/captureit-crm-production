<?php

use Webkul\Core\Support\SalesLineItem;

it('preserves historical lead amounts and physical quantities when switching to billing units', function () {
    $source = ['quantity' => 2, 'day' => 3, 'price' => 500000];
    $saved = SalesLineItem::prepare(SalesLineItem::display($source), $source);

    expect($saved['unit'])->toBe('day')
        ->and($saved['quantity'])->toBe(6.0)
        ->and($saved['day'])->toBe(1)
        ->and($saved['amount'])->toBe(3000000.0)
        ->and($saved['equipment_quantity'])->toBe(2.0)
        ->and($source)->toBe(['quantity' => 2, 'day' => 3, 'price' => 500000]);
});

it('copies a default catalog unit but allows a per-line override', function () {
    $default = SalesLineItem::prepare(['quantity' => 3, 'price' => 100], null, 'day');
    $override = SalesLineItem::prepare(['quantity' => 3, 'price' => 100, 'unit' => 'pcs'], null, 'day');

    expect($default['unit'])->toBe('day')
        ->and($default['equipment_quantity'])->toBe(1.0)
        ->and($override['unit'])->toBe('pcs')
        ->and($override['equipment_quantity'])->toBe(3.0)
        ->and($default['amount'])->toBe($override['amount']);
});
