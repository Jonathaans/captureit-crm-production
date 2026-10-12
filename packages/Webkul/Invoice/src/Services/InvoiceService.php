<?php

namespace Webkul\Invoice\Services;

use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Quote\Models\Quote;

class InvoiceService
{
    /** Legacy callers must use the same proforma-first workflow as the UI. */
    public function createFromQuote(Quote $quote, array $input = [], ?int $createdBy = null): ProformaInvoice
    {
        return app(FlexibleQuoteBillingService::class)->createFromQuote($quote, array_replace([
            'billing_type' => FlexibleQuoteBillingService::TYPE_FULL_PAYMENT,
            'payment_term' => $quote->payment_term,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(7)->toDateString(),
        ], $input), $createdBy);
    }
}
