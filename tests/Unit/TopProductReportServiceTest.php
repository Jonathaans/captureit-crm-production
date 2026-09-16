<?php

declare(strict_types=1);

use Tests\TestCase;
use Webkul\Admin\Services\TopProductReportService;

uses(TestCase::class);

it('ranks products by confirmed deals without duplicating a staged invoice deal', function (): void {
    $rows = (new TopProductReportService())->rankDeals([
        [
            'deal_key' => 'quote:10',
            'deal_date' => '2026-09-05',
            'business_unit' => 'photobooth',
            'deal_value' => 12_000_000,
            'received' => 6_000_000,
            'invoice_count' => 2,
            'items' => [
                [
                    'product_id' => 1,
                    'sku' => 'CLASSIC',
                    'name' => 'Classic Photobooth',
                    'quantity' => 2,
                    'total' => 8_000_000,
                ],
                [
                    'product_id' => 2,
                    'sku' => 'PRINT',
                    'name' => 'Print Add-on',
                    'quantity' => 1,
                    'total' => 4_000_000,
                ],
            ],
        ],
        [
            'deal_key' => 'quote:11',
            'deal_date' => '2026-09-12',
            'business_unit' => 'photobooth',
            'deal_value' => 5_000_000,
            'received' => 5_000_000,
            'invoice_count' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'sku' => 'CLASSIC',
                    'name' => 'Classic Photobooth',
                    'quantity' => 1,
                    'total' => 5_000_000,
                ],
            ],
        ],
    ], [
        'year' => 2026,
        'month' => 9,
        'business_unit' => 'photobooth',
        'event_status' => 'confirm',
        'product' => null,
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['rank'])->toBe(1)
        ->and($rows[0]['product_name'])->toBe('Classic Photobooth')
        ->and($rows[0]['deal_count'])->toBe(2)
        ->and($rows[0]['quantity'])->toBe(3.0)
        ->and($rows[0]['sales_value'])->toBe(13_000_000.0)
        ->and($rows[0]['received_allocated'])->toBe(9_000_000.0)
        ->and($rows[1]['deal_count'])->toBe(1)
        ->and($rows[1]['received_allocated'])->toBe(2_000_000.0);
});

it('allocates deal value and payments proportionally to its product lines', function (): void {
    $rows = (new TopProductReportService())->rankDeals([
        [
            'deal_key' => 'quote:20',
            'deal_date' => '2026-09-16',
            'business_unit' => 'corporate',
            'deal_value' => 11_000_000,
            'received' => 5_500_000,
            'items' => [
                [
                    'product_id' => 10,
                    'sku' => 'A',
                    'name' => 'Product A',
                    'quantity' => 1,
                    'total' => 3_000_000,
                ],
                [
                    'product_id' => 11,
                    'sku' => 'B',
                    'name' => 'Product B',
                    'quantity' => 1,
                    'total' => 1_000_000,
                ],
            ],
        ],
    ], [
        'year' => 2026,
        'month' => 9,
        'business_unit' => 'corporate',
        'event_status' => null,
        'product' => null,
    ]);

    expect($rows[0]['product_name'])->toBe('Product A')
        ->and($rows[0]['sales_value'])->toBe(8_250_000.0)
        ->and($rows[0]['received_allocated'])->toBe(4_125_000.0)
        ->and($rows[0]['collection_rate'])->toBe(50.0)
        ->and($rows[1]['sales_value'])->toBe(2_750_000.0)
        ->and($rows[1]['received_allocated'])->toBe(1_375_000.0);
});

it('follows the financial report period product and confirmed-status filters', function (): void {
    $service = new TopProductReportService();
    $deals = [
        [
            'deal_key' => 'invoice:30',
            'deal_date' => '2026-08-31',
            'business_unit' => 'photobooth',
            'deal_value' => 1_000_000,
            'received' => 1_000_000,
            'items' => [[
                'product_id' => null,
                'sku' => '',
                'name' => 'Legacy Product',
                'quantity' => 1,
                'total' => 1_000_000,
            ]],
        ],
        [
            'deal_key' => 'invoice:31',
            'deal_date' => '2026-09-01',
            'business_unit' => 'photobooth',
            'deal_value' => 2_000_000,
            'received' => 500_000,
            'items' => [[
                'product_id' => null,
                'sku' => '',
                'name' => 'September Product',
                'quantity' => 1,
                'total' => 2_000_000,
            ]],
        ],
    ];

    $filtered = $service->rankDeals($deals, [
        'year' => 2026,
        'month' => 9,
        'business_unit' => 'photobooth',
        'event_status' => 'confirm',
        'product' => 'september product',
    ]);

    $notConfirmed = $service->rankDeals($deals, [
        'year' => 2026,
        'month' => 9,
        'business_unit' => 'photobooth',
        'event_status' => 'prospect',
        'product' => null,
    ]);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['product_name'])->toBe('September Product')
        ->and($notConfirmed)->toBeEmpty();
});
