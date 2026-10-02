<?php

/** Read-only checks; no Laravel bootstrap or database connection required. */
require_once __DIR__.'/../packages/Webkul/Core/src/Support/SalesLineItem.php';

use Webkul\Core\Support\SalesLineItem;

$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$pcs = SalesLineItem::prepare(['quantity' => 2, 'unit' => 'pcs', 'day' => 5, 'price' => 500000]);
$assert($pcs['amount'] === 1000000.0, 'Pcs must use quantity x price only.');
$assert($pcs['day'] === 1, 'A submitted legacy day must not multiply a unit line.');
$assert($pcs['equipment_quantity'] === 2.0, 'Two Pcs require two equipment sets.');

$days = SalesLineItem::prepare(['quantity' => 3, 'unit' => 'day', 'price' => 500000, 'equipment_quantity' => 99]);
$assert($days['amount'] === 1500000.0, 'Three Day must bill three unit prices.');
$assert($days['equipment_quantity'] === 1.0, 'Billing days must not multiply equipment, even with a forged snapshot.');
$default = SalesLineItem::prepare(['quantity' => 3, 'price' => 500000], null, 'day');
$assert($default['unit'] === 'day' && $default['equipment_quantity'] === 1.0, 'Catalog default must initialize a new line.');

$legacy = ['quantity' => 2, 'day' => 3, 'price' => 500000, 'total' => 3000000];
$display = SalesLineItem::display($legacy);
$assert($display['quantity'] === 6.0 && $display['unit'] === 'day', 'Legacy Day x Qty must flatten without losing billable quantity.');
$assert($legacy['quantity'] === 2 && $legacy['day'] === 3 && ! isset($legacy['unit']), 'Reading old documents must not mutate their snapshot.');
$converted = SalesLineItem::prepare($display, $legacy);
$assert($converted['amount'] === 3000000.0, 'Saving an old line must preserve its base amount.');
$assert($converted['equipment_quantity'] === 2.0, 'Saving an old line must preserve its physical sets.');
$longer = SalesLineItem::prepare(array_merge($converted, ['quantity' => 8]), $converted);
$assert($longer['equipment_quantity'] === 2.0 && $longer['amount'] === 4000000.0, 'Changing duration must keep equipment count.');
$switched = SalesLineItem::prepare(array_merge($longer, ['quantity' => 4, 'unit' => 'pcs']), $longer);
$assert($switched['equipment_quantity'] === 4.0, 'Switching to Pcs must use the chosen physical count.');

$invoice = array_merge($legacy, ['discount_amount' => 100000, 'tax_amount' => 319000]);
$edit = SalesLineItem::invoiceDisplay($invoice);
$saved = SalesLineItem::prepare($edit, $invoice);
$amounts = SalesLineItem::invoiceAmounts($saved, $invoice);
$assert($amounts === ['base' => 3000000.0, 'discount' => 100000.0, 'tax' => 319000.0], 'Unchanged legacy invoice amounts must be exact.');
$assert($amounts['base'] - $amounts['discount'] + $amounts['tax'] === 3219000.0, 'Discount and tax must be applied once.');

$previouslyEdited = array_merge($invoice, ['total' => 3219000]);
$edit = SalesLineItem::prepare(SalesLineItem::invoiceDisplay($previouslyEdited), $previouslyEdited);
$amounts = SalesLineItem::invoiceAmounts($edit, $previouslyEdited);
$assert($amounts['base'] === 3000000.0, 'Legacy net line totals must be recognized before applying tax and discount.');
$assert($amounts['base'] - $amounts['discount'] + $amounts['tax'] === 3219000.0, 'Re-saving a previously edited invoice must retain its total.');

$allocated = ['unit' => 'day', 'quantity' => 3, 'day' => 1, 'price' => 33.3333, 'total' => 100, 'discount_amount' => 0, 'tax_amount' => 0];
$edit = SalesLineItem::prepare(SalesLineItem::invoiceDisplay($allocated), $allocated);
$amounts = SalesLineItem::invoiceAmounts($edit, $allocated);
$assert($amounts['base'] === 100.0, 'An unchanged allocated DP invoice must retain rounding residue.');

$invalidRejected = false;
try {
    SalesLineItem::prepare(['quantity' => 1, 'price' => 100, 'unit' => 'week']);
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}
$assert($invalidRejected, 'Unknown billing units must be rejected.');

echo "PASS: {$checks} sales-unit checks; database untouched.\n";
