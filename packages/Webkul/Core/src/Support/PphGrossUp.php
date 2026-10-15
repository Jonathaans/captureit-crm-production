<?php

namespace Webkul\Core\Support;

/** Agreed customer withholding, separate from cash receipts and other taxes. */
class PphGrossUp
{
    public const RATE = 0.5;

    public static function calculate(float $netService, float $otherTax = 0, bool $enabled = true): array
    {
        $net = round(max(0, $netService), 2);
        $gross = round($net / (1 - self::RATE / 100), 2);

        return [
            'pph_gross_up' => $enabled,
            'pph_rate' => $enabled ? self::RATE : 0,
            'pph_amount' => $enabled ? round($gross - $net, 2) : 0.0,
            'grand_total' => $enabled ? round($gross + $otherTax, 2) : round(max(0, $netService + $otherTax), 4),
        ];
    }

    /** Settlement takes the rounding remainder so the documents reconcile exactly. */
    public static function allocate(float $pph, float $total, float $amount, ?float $previousPph = null): float
    {
        return $previousPph !== null ? round(max(0, $pph - $previousPph), 2)
            : ($total > 0 ? round($pph * $amount / $total, 2) : 0.0);
    }

    public static function grossFromCash(float $cash, float $pph, float $total): float
    {
        $net = round($total - $pph, 2);

        return round($cash + ($net > 0 ? round($pph * $cash / $net, 2) : 0), 2);
    }
}
