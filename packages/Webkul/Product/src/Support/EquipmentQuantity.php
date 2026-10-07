<?php

namespace Webkul\Product\Support;

use Webkul\Core\Support\SalesLineItem;

final class EquipmentQuantity
{
    public const BASES = ['equipment', 'sales', 'manual'];

    public static function forLine(array $requirement, array $line): float
    {
        $basis = $requirement['quantity_basis'] ?? 'equipment';
        if ($basis === 'manual') {
            return 0;
        }

        $multiplier = is_numeric($requirement['quantity'] ?? null)
            ? (float) $requirement['quantity'] : 1.0;
        $multiplier = $multiplier > 0 ? $multiplier : 1.0;

        if ($basis === 'sales') {
            $sale = SalesLineItem::display($line);

            // Rental days are not a count of prints. Ask the operator instead.
            return $sale['unit'] === 'pcs'
                ? $multiplier * max(0, (float) $sale['quantity']) : 0;
        }

        return $multiplier * SalesLineItem::physicalQuantity($line);
    }
}
