<?php

use Webkul\Core\Support\PphGrossUp;

it('derives gross service value from net and keeps other tax separate', function () {
    expect(PphGrossUp::calculate(7500000))->toBe([
        'pph_gross_up' => true, 'pph_rate' => 0.5, 'pph_amount' => 37688.44, 'grand_total' => 7537688.44,
    ])->and(PphGrossUp::calculate(7500000, 825000)['grand_total'])->toBe(8362688.44)
        ->and(PphGrossUp::calculate(7500000, 825000, false)['grand_total'])->toBe(8325000.0)
        ->and(PphGrossUp::calculate(7500000, 825000, false)['pph_amount'])->toBe(0.0);
});

it('reconciles DP settlement tax and net cash after rounding', function (float $net, float $portion) {
    $total = PphGrossUp::calculate($net);
    $dp = round($total['grand_total'] * $portion, 2);
    $pph = PphGrossUp::allocate($total['pph_amount'], $total['grand_total'], $dp);
    $remaining = round($total['grand_total'] - $dp, 2);
    $finalPph = PphGrossUp::allocate($total['pph_amount'], $total['grand_total'], $remaining, $pph);
    expect(round($pph + $finalPph, 2))->toBe($total['pph_amount'])
        ->and(round($dp - $pph + $remaining - $finalPph, 2))->toBe(round($net, 2));
})->with([[7500000.0, 0.5], [1234567.89, 0.3], [1.0, 0.5], [1000000000.0, 0.333333]]);
