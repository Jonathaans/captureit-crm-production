<?php

namespace Webkul\Invoice\Support;

class BillingDescription
{
    public static function make(string $type, float $quoteTotal, ?string $description = null): string
    {
        $label = match ($type) {
            'down_payment' => 'Pembayaran uang muka',
            'settlement' => 'Pelunasan',
            default => null,
        };

        $description = trim((string) $description);

        if ($label === null || $quoteTotal <= 0) {
            return $description;
        }

        return $label.' atas total nilai transaksi Rp '.number_format($quoteTotal, 0, ',', '.')
            .($description !== '' ? ' - '.$description : '');
    }

    /**
     * Refresh only system-generated legacy prefixes when displaying an invoice.
     * Use its contract snapshot, never the partial invoice amount or a live Quote.
     */
    public static function display(array $invoice, ?string $description): ?string
    {
        $type = $invoice['billing_type'] ?? 'full_payment';
        $quoteTotal = (float) ($invoice['quote_total_snapshot'] ?? 0);

        if (! in_array($type, ['down_payment', 'settlement'], true) || $quoteTotal <= 0) {
            return $description;
        }

        $text = trim((string) $description);
        $legacyLabel = $type === 'down_payment' ? 'Down Payment' : 'Pelunasan';
        $pattern = '/\A'.preg_quote($legacyLabel, '/')
            .'(?:\s+\d+(?:[.,]\d+)?%)?(?:\s+-\s+|\z)/u';
        $details = preg_replace($pattern, '', $text, 1, $count);

        if ($count > 0 || $text === '') {
            return self::make($type, $quoteTotal, $details);
        }

        // Older Quotes without items produced a single reference-only line.
        if (preg_match('/\ATagihan '.preg_quote($legacyLabel, '/').' untuk (.+)\.\z/us', $text, $matches)) {
            return self::make($type, $quoteTotal, 'Referensi penawaran '.$matches[1].'.');
        }

        return $description;
    }
}
