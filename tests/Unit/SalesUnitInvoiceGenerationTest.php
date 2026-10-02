<?php

use Illuminate\Support\Collection;
use Tests\TestCase;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Invoice\Models\Invoice;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;

uses(TestCase::class);

it('carries billing units and physical quantities into full and allocated invoices', function (): void {
    $quote = new Quote;
    $quote->setRawAttributes(['quote_number' => 'UNIT-TEST']);
    $quote->setRelation('items', new Collection([
        new QuoteItem(['product_id' => 1, 'name' => 'Rental', 'unit' => 'day', 'day' => 1, 'quantity' => 3, 'equipment_quantity' => 1, 'price' => 500000, 'total' => 1500000]),
        new QuoteItem(['product_id' => 2, 'name' => 'Print', 'unit' => 'pcs', 'day' => 1, 'quantity' => 100, 'equipment_quantity' => 100, 'price' => 5000, 'total' => 500000]),
        new QuoteItem(['product_id' => 3, 'name' => 'Legacy', 'day' => 3, 'quantity' => 2, 'price' => 500000, 'total' => 3000000]),
    ]));

    $invoice = new class extends Invoice
    {
        public array $capturedItems = [];

        public function items()
        {
            return new class($this)
            {
                public function __construct(private Invoice $invoice) {}

                public function create(array $attributes): void
                {
                    $this->invoice->capturedItems[] = $attributes;
                }
            };
        }
    };

    $service = new FlexibleQuoteBillingService;
    (new ReflectionMethod($service, 'copyFullItems'))->invoke($service, $invoice, $quote);
    expect($invoice->capturedItems[0]['unit'])->toBe('day')
        ->and((int) $invoice->capturedItems[0]['quantity'])->toBe(3)
        ->and((float) $invoice->capturedItems[0]['equipment_quantity'])->toBe(1.0)
        ->and($invoice->capturedItems[1]['unit'])->toBe('pcs')
        ->and($invoice->capturedItems[2]['unit'])->toBeNull()
        ->and((int) $invoice->capturedItems[2]['day'])->toBe(3);

    foreach ([FlexibleQuoteBillingService::TYPE_DOWN_PAYMENT, FlexibleQuoteBillingService::TYPE_SETTLEMENT] as $type) {
        $invoice->capturedItems = [];
        (new ReflectionMethod($service, 'copyAllocatedItems'))->invoke($service, $invoice, $quote, 100.0, $type, 50.0);
        expect(array_sum(array_column($invoice->capturedItems, 'total')))->toBe(100.0)
            ->and($invoice->capturedItems[0]['unit'])->toBe('day')
            ->and($invoice->capturedItems[0]['quantity'])->toBe(3.0)
            ->and($invoice->capturedItems[0]['equipment_quantity'])->toBe(1.0)
            ->and($invoice->capturedItems[1]['unit'])->toBe('pcs')
            ->and($invoice->capturedItems[1]['quantity'])->toBe(100.0)
            ->and($invoice->capturedItems[2]['unit'])->toBe('day')
            ->and($invoice->capturedItems[2]['quantity'])->toBe(6.0)
            ->and($invoice->capturedItems[2]['equipment_quantity'])->toBe(2.0);
    }
});
