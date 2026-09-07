<?php

use Webkul\Admin\Services\FlexibleQuoteBillingService;

it('keeps nominal DP authoritative and derives percentage plus remainder', function () {
    $position = (new FlexibleQuoteBillingService())
        ->calculateDownPaymentPosition(
            5_000_000,
            2_000_000
        );

    expect($position)->toBe([
        'amount' => 2_000_000.0,
        'percentage' => 40.0,
        'remaining' => 3_000_000.0,
    ]);
});

it('supports a DP that is not fifty percent', function () {
    $position = (new FlexibleQuoteBillingService())
        ->calculateDownPaymentPosition(
            7_500_000,
            2_250_000
        );

    expect($position['percentage'])->toBe(30.0)
        ->and($position['remaining'])->toBe(5_250_000.0);
});

