<?php

namespace Webkul\Core\Support;

/**
 * Billing units are separate from the physical equipment required for an event.
 * A null unit identifies an untouched historical Day x Qty line.
 */
final class SalesLineItem
{
    public const UNITS = ['pcs', 'day'];

    public static function display(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $legacyDays = max(1, (int) ($item['day'] ?? 1));

        if (! in_array($item['unit'] ?? null, self::UNITS, true)) {
            $item['quantity'] = $quantity * $legacyDays;
            $item['unit'] = $legacyDays > 1 ? 'day' : 'pcs';
        }

        $item['day'] = 1;

        return $item;
    }

    public static function physicalQuantity(array $item): float
    {
        if (is_numeric($item['equipment_quantity'] ?? null)) {
            return max(0, (float) $item['equipment_quantity']);
        }

        return ($item['unit'] ?? null) === 'day'
            ? 1.0
            : max(0, (float) ($item['quantity'] ?? 1));
    }

    public static function invoiceBase(array $item): float
    {
        $line = self::display($item);
        $calculated = round((float) $line['quantity'] * (float) ($line['price'] ?? 0), 4);
        $stored = (float) ($item['total'] ?? $calculated);
        $net = $calculated - (float) ($item['discount_amount'] ?? 0) + (float) ($item['tax_amount'] ?? 0);

        // The former invoice editor saved a net total, while generation saved a
        // subtotal. Recognize that legacy shape before applying tax/discount.
        if (empty($item['unit']) && abs($stored - $net) < 0.0001 && abs($stored - $calculated) >= 0.0001) {
            return $calculated;
        }

        return $stored;
    }

    public static function invoiceDisplay(array $item): array
    {
        $line = self::display($item);
        $base = self::invoiceBase($item);

        // Allocated DP/settlement invoices store an allocated line amount.
        if ((float) $line['quantity'] > 0) {
            $line['price'] = $base / (float) $line['quantity'];
        }

        $line['discount_percent'] = $base > 0
            ? (float) ($item['discount_amount'] ?? 0) / $base * 100 : 0;
        $taxable = $base - (float) ($item['discount_amount'] ?? 0);
        $line['tax_percent'] = $taxable > 0
            ? (float) ($item['tax_amount'] ?? 0) / $taxable * 100 : 0;

        return $line;
    }

    public static function invoiceAmounts(array $line, array $source): array
    {
        $original = self::invoiceDisplay($source);
        $unchangedBase = (float) $line['quantity'] === (float) $original['quantity']
            && abs((float) $line['price'] - (float) $original['price']) < 0.00000001;
        $base = $unchangedBase ? self::invoiceBase($source) : $line['amount'];
        $discount = $unchangedBase && abs((float) $line['discount_percent'] - $original['discount_percent']) < 0.00000001
            ? (float) ($source['discount_amount'] ?? 0)
            : round($base * (float) $line['discount_percent'] / 100, 4);
        $taxable = max(0, $base - $discount);
        $tax = $unchangedBase && $discount === (float) ($source['discount_amount'] ?? 0)
            && abs((float) $line['tax_percent'] - $original['tax_percent']) < 0.00000001
            ? (float) ($source['tax_amount'] ?? 0)
            : round($taxable * (float) $line['tax_percent'] / 100, 4);

        return ['base' => $base, 'discount' => $discount, 'tax' => $tax];
    }

    /** Normalize a submitted line, using only a trusted stored source for equipment. */
    public static function prepare(array $item, ?array $source = null, string $defaultUnit = 'pcs'): array
    {
        // Older integrations may still submit Day x Qty without a unit.
        if (! isset($item['unit'])) {
            $legacy = $item;
            unset($legacy['equipment_quantity']);
            $item = self::display($item);
            $source ??= $legacy;

            if (max(1, (int) ($legacy['day'] ?? 1)) === 1) {
                $item['unit'] = in_array($defaultUnit, self::UNITS, true) ? $defaultUnit : 'pcs';
            }
        }

        if (! in_array($item['unit'], self::UNITS, true)) {
            throw new \InvalidArgumentException('Sales unit must be pcs or day.');
        }

        $item['day'] = 1;
        $item['quantity'] = (float) ($item['quantity'] ?? 0);
        $item['price'] = (float) ($item['price'] ?? 0);
        $item['equipment_quantity'] = $item['unit'] === 'pcs' ? $item['quantity'] : 1.0;

        if ($source && $item['unit'] === 'day' && self::display($source)['unit'] === 'day') {
            $item['equipment_quantity'] = self::physicalQuantity($source);
        }

        $item['amount'] = round($item['quantity'] * $item['price'], 4);

        return $item;
    }
}
