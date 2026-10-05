<?php

namespace Webkul\Automation\Services;

use Webkul\Core\Support\SalesLineItem;

/** Telegram HTML summary of Lead products, not an invoice or payment receipt. */
class LeadProductSummary
{
    public function format(iterable $products): string
    {
        $products = collect($products)->values();

        if ($products->isEmpty()) {
            return 'Belum ada produk pada Lead. Lengkapi produk sebelum membuat Invoice.';
        }

        $blocks = [];

        foreach ($products as $index => $product) {
            $line = SalesLineItem::display($product);
            $name = trim((string) ($product['name'] ?? '')) ?: 'Produk tanpa nama';
            $name = preg_replace('/\s+/u', ' ', $name);
            $name = mb_strlen($name) > 100 ? mb_substr($name, 0, 97).'...' : $name;
            $name = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $quantity = rtrim(rtrim(number_format((float) $line['quantity'], 4, ',', '.'), '0'), ',');
            $price = (float) ($line['price'] ?? 0);
            $subtotal = round((float) $line['quantity'] * $price, 4);
            $money = fn (float $amount): string => 'Rp '.number_format($amount, 2, ',', '.');
            $block = ($index + 1).'. <b>'.$name.'</b>&#10;'
                .$quantity.' '.$line['unit'].' × '.$money($price).' = <b>'.$money($subtotal).'</b>';
            $candidate = implode('&#10;&#10;', [...$blocks, $block]);

            // Leave room for the customer, project, owner and action reminder.
            if (mb_strlen(html_entity_decode(strip_tags($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8')) > 2300) {
                $blocks[] = '… '.($products->count() - $index).' produk lainnya. Lihat rincian lengkap di CRM.';

                break;
            }

            $blocks[] = $block;
        }

        return implode('&#10;&#10;', $blocks);
    }
}
