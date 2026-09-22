<?php

declare(strict_types=1);

namespace Webkul\Admin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Webkul\Invoice\Models\Invoice;

/**
 * CRM_TOP_PRODUCT_REPORT_V1
 *
 * A quote is one commercial deal even when it is billed through separate DP
 * and settlement invoices. Product quantities and values therefore come from
 * quote items whenever possible, while payments come from all confirmed
 * invoices belonging to the deal.
 */
class TopProductReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters): Collection
    {
        $eventStatus = trim((string) ($filters['event_status'] ?? ''));

        if ($eventStatus !== '' && $eventStatus !== 'confirm') {
            return collect();
        }

        $year = max(1, (int) ($filters['year'] ?? now()->year));
        $month = ($filters['month'] ?? null) !== null
            ? max(1, min(12, (int) $filters['month']))
            : null;
        $periodStart = CarbonImmutable::create($year, $month ?? 1, 1)->startOfDay();
        $periodEnd = $month !== null
            ? $periodStart->addMonth()
            : $periodStart->addYear();

        /*
         * Select quote deals by their first confirmed invoice date. This keeps
         * the period stable and avoids loading every historical invoice.
         */
        $quoteIds = Invoice::query()
            ->select('quote_id')
            ->whereNotNull('quote_id')
            ->whereNotNull('issued_at')
            ->where('event_status', 'confirm')
            ->groupBy('quote_id')
            ->havingRaw(
                'MIN(issued_at) >= ? AND MIN(issued_at) < ?',
                [
                    $periodStart->toDateTimeString(),
                    $periodEnd->toDateTimeString(),
                ]
            )
            ->pluck('quote_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $invoices = Invoice::query()
            ->with([
                'items',
                'payments',
                'quote.items',
            ])
            ->where('event_status', 'confirm')
            ->whereNotNull('issued_at')
            ->where(function ($query) use ($quoteIds, $periodStart, $periodEnd): void {
                $query->where(function ($standalone) use ($periodStart, $periodEnd): void {
                    $standalone
                        ->whereNull('quote_id')
                        ->where('issued_at', '>=', $periodStart)
                        ->where('issued_at', '<', $periodEnd);
                });

                if ($quoteIds !== []) {
                    $query->orWhereIn('quote_id', $quoteIds);
                }
            })
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        $deals = collect();

        $invoices
            ->whereNotNull('quote_id')
            ->groupBy(fn (Invoice $invoice): int => (int) $invoice->quote_id)
            ->each(function (Collection $dealInvoices) use ($deals): void {
                $deal = $this->quoteDeal($dealInvoices);

                if ($deal !== null) {
                    $deals->push($deal);
                }
            });

        $invoices
            ->whereNull('quote_id')
            ->each(function (Invoice $invoice) use ($deals): void {
                $deal = $this->standaloneDeal($invoice);

                if ($deal !== null) {
                    $deals->push($deal);
                }
            });

        return $this->rankDeals($deals->all(), $filters);
    }

    /**
     * Pure aggregation rule kept public so it can be regression-tested without
     * requiring a database connection.
     *
     * @param  array<int, array<string, mixed>>  $deals
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rankDeals(array $deals, array $filters): Collection
    {
        $year = max(1, (int) ($filters['year'] ?? now()->year));
        $month = ($filters['month'] ?? null) !== null
            ? (int) $filters['month']
            : null;
        $businessUnit = trim((string) ($filters['business_unit'] ?? ''));
        $eventStatus = trim((string) ($filters['event_status'] ?? ''));
        $productFilter = $this->normalizeName((string) ($filters['product'] ?? ''));

        if ($eventStatus !== '' && $eventStatus !== 'confirm') {
            return collect();
        }

        $products = [];

        foreach ($deals as $deal) {
            $dealDate = $this->dateParts($deal['deal_date'] ?? null);

            if ($dealDate === null || $dealDate['year'] !== $year) {
                continue;
            }

            if ($month !== null && $dealDate['month'] !== $month) {
                continue;
            }

            if ($businessUnit !== '' && trim((string) ($deal['business_unit'] ?? '')) !== $businessUnit) {
                continue;
            }

            $dealProducts = $this->groupDealItems((array) ($deal['items'] ?? []));
            $itemValue = (float) collect($dealProducts)->sum('sales_value');
            $dealValue = max(0.0, (float) ($deal['deal_value'] ?? 0));
            $received = max(0.0, (float) ($deal['received'] ?? 0));

            if ($dealValue > 0) {
                $received = min($received, $dealValue);
            }

            foreach ($dealProducts as $item) {
                if ($productFilter !== '' && $this->normalizeName($item['product_name']) !== $productFilter) {
                    continue;
                }

                $key = $item['product_key'];

                if (! isset($products[$key])) {
                    $products[$key] = [
                        'product_key' => $key,
                        'product_id' => $item['product_id'],
                        'sku' => $item['sku'],
                        'product_name' => $item['product_name'],
                        'deal_count' => 0,
                        'quantity' => 0.0,
                        'sales_value' => 0.0,
                        'received_allocated' => 0.0,
                    ];
                }

                /* Keep the latest non-empty commercial snapshot label. */
                if ($item['product_name'] !== '') {
                    $products[$key]['product_name'] = $item['product_name'];
                }

                if ($item['sku'] !== '') {
                    $products[$key]['sku'] = $item['sku'];
                }

                $weight = $itemValue > 0
                    ? $item['sales_value'] / $itemValue
                    : 0.0;
                $allocatedSalesValue = $dealValue > 0
                    ? $dealValue * $weight
                    : $item['sales_value'];

                $products[$key]['deal_count']++;
                $products[$key]['quantity'] += $item['quantity'];
                $products[$key]['sales_value'] += $allocatedSalesValue;

                if ($weight > 0) {
                    $products[$key]['received_allocated'] += $received * $weight;
                }
            }
        }

        return collect(array_values($products))
            ->map(function (array $row): array {
                $row['quantity'] = round((float) $row['quantity'], 2);
                $row['sales_value'] = round((float) $row['sales_value'], 2);
                $row['received_allocated'] = round((float) $row['received_allocated'], 2);
                $row['collection_rate'] = $row['sales_value'] > 0
                    ? round(min(100, ($row['received_allocated'] / $row['sales_value']) * 100), 2)
                    : 0.0;

                return $row;
            })
            ->sort(function (array $left, array $right): int {
                return $right['deal_count'] <=> $left['deal_count']
                    ?: $right['quantity'] <=> $left['quantity']
                    ?: $right['sales_value'] <=> $left['sales_value']
                    ?: strcasecmp($left['product_name'], $right['product_name']);
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function quoteDeal(Collection $invoices): ?array
    {
        /** @var Invoice|null $first */
        $first = $invoices
            ->sortBy(fn (Invoice $invoice): string => $invoice->issued_at?->format('Y-m-d H:i:s.u') ?? '')
            ->first();

        if ($first === null || $first->issued_at === null) {
            return null;
        }

        $quote = $invoices->pluck('quote')->filter()->first();
        $items = $quote?->items;

        if ($items === null || $items->isEmpty()) {
            $sourceInvoice = $this->bestItemSnapshot($invoices);
            $items = $sourceInvoice?->items ?? collect();
        }

        $quoteTotal = max(0.0, (float) ($quote?->grand_total ?? 0));
        $snapshotTotal = max(0.0, (float) $invoices->max('quote_total_snapshot'));
        $dealValue = $quoteTotal > 0
            ? $quoteTotal
            : ($snapshotTotal > 0 ? $snapshotTotal : max(0.0, (float) $invoices->sum('grand_total')));

        return [
            'deal_key' => 'quote:'.(int) $first->quote_id,
            'deal_date' => $first->issued_at->toDateString(),
            'business_unit' => trim((string) ($quote?->business_unit ?? $first->business_unit ?? '')),
            'deal_value' => $dealValue,
            'received' => $this->received($invoices),
            'items' => $this->normalizeItems($items),
        ];
    }

    private function standaloneDeal(Invoice $invoice): ?array
    {
        if ($invoice->issued_at === null) {
            return null;
        }

        return [
            'deal_key' => 'invoice:'.(int) $invoice->id,
            'deal_date' => $invoice->issued_at->toDateString(),
            'business_unit' => trim((string) ($invoice->business_unit ?? '')),
            'deal_value' => max(0.0, (float) $invoice->grand_total),
            'received' => $this->received(collect([$invoice])),
            'items' => $this->normalizeItems($invoice->items),
        ];
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function bestItemSnapshot(Collection $invoices): ?Invoice
    {
        return $invoices->first(
            fn (Invoice $invoice): bool => in_array(
                $this->normalizeBillingType($invoice->billing_type),
                ['full_payment', 'settlement'],
                true
            ) && $invoice->items->isNotEmpty()
        ) ?? $invoices->first(
            fn (Invoice $invoice): bool => $invoice->items->isNotEmpty()
        );
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function received(Collection $invoices): float
    {
        return max(0.0, (float) $invoices->sum(
            fn (Invoice $invoice): float => (float) $invoice->payments->sum('amount')
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeItems(Collection $items): array
    {
        return $items->map(fn ($item): array => [
            'product_id' => $item->product_id ? (int) $item->product_id : null,
            'sku' => trim((string) ($item->sku ?? '')),
            'name' => trim((string) ($item->name ?? '')),
            'quantity' => max(0.0, (float) ($item->quantity ?? 0)),
            'total' => max(0.0, (float) ($item->total ?? 0)),
        ])->all();
    }

    /**
     * Collapse duplicate lines of the same product inside one deal, so the
     * confirmed-project counter is incremented only once per product/deal.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function groupDealItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $productId = max(0, (int) ($item['product_id'] ?? 0));
            $key = $productId > 0
                ? 'product:'.$productId
                : 'name:'.$this->normalizeName($name);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_key' => $key,
                    'product_id' => $productId > 0 ? $productId : null,
                    'sku' => trim((string) ($item['sku'] ?? '')),
                    'product_name' => $name,
                    'quantity' => 0.0,
                    'sales_value' => 0.0,
                ];
            }

            $grouped[$key]['quantity'] += max(0.0, (float) ($item['quantity'] ?? 0));
            $grouped[$key]['sales_value'] += max(0.0, (float) ($item['total'] ?? 0));
        }

        return array_values($grouped);
    }

    /** @return array{year: int, month: int}|null */
    private function dateParts(mixed $value): ?array
    {
        $date = trim((string) $value);

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $matches)) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'month' => (int) $matches[2],
        ];
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeBillingType(mixed $value): string
    {
        $type = mb_strtolower(trim((string) $value));

        return match ($type) {
            '', 'full', 'full payment' => 'full_payment',
            'down payment', 'dp' => 'down_payment',
            'pelunasan' => 'settlement',
            default => str_replace([' ', '-'], '_', $type),
        };
    }
}
