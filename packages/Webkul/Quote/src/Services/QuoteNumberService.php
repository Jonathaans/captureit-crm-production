<?php

namespace Webkul\Quote\Services;

use Webkul\Core\Support\DocumentNumbers;

class QuoteNumberService
{
    public function generate(): string
    {
        return app(DocumentNumbers::class)->next('quote', 'QT '.now()->format('ym').'-');
    }
}
