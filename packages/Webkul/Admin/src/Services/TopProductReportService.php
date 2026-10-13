<?php

declare(strict_types=1);

namespace Webkul\Admin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Webkul\Core\Support\SalesLineItem;
use Webkul\Invoice\Models\Invoice;

/**
 * CRM_TOP_PRODUCT_REPORT_V1
 *
 * Only issued, confirmed invoices and their frozen items contribute value.
 * DP and settlement share one project count, but each contributes only its
 * actual billed value in the selected invoice period. No quote/proforma values.
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

        $invoices = Invoice::query()->notVoided()
            ->with(['items', 'payments'])
            ->where('event_status', 'confirm')
            ->where('issued_at', '>=', $periodStart)
            ->where('issued_at', '<', $periodEnd)
            ->when(! empty($filters['business_unit']), fn ($query) => $query->where('business_unit', $filters['business_unit']))
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        $projectProducts = [];
        foreach ($invoices as $invoice) {
            $projectKey = $invoice->quote_id ? 'quote:'.$invoice->quote_id : 'invoice:'.$invoice->id;
            // Allocate each invoice independently: different billing stages may have different item weights.
            foreach ($this->rankDeals([$this->standaloneDeal($invoice)], $filters) as $row) {
                $key = $projectKey.'|'.$row['product_key'];
                if (! isset($projectProducts[$key])) {
                    $projectProducts[$key] = $row;

                    continue;
                }
                $projectProducts[$key]['sales_value'] += $row['sales_value'];
                $projectProducts[$key]['received_allocated'] += $row['received_allocated'];
                // Billing the same equipment twice does not mean a second project/quantity sold.
                $projectProducts[$key]['quantity'] = max($projectProducts[$key]['quantity'], $row['quantity']);
                foreach (SalesLineItem::UNITS as $unit) {
                    $projectProducts[$key]['quantity_units'][$unit] = max(
                        $projectProducts[$key]['quantity_units'][$unit], $row['quantity_units'][$unit]
                    );
                }
            }
        }

        $products = [];
        foreach ($projectProducts as $row) {
            $key = $row['product_key'];
            if (! isset($products[$key])) {
                $products[$key] = $row;

                continue;
            }
            foreach (['deal_count', 'quantity', 'sales_value', 'received_allocated'] as $metric) {
                $products[$key][$metric] += $row[$metric];
            }
            foreach (SalesLineItem::UNITS as $unit) {
                $products[$key]['quantity_units'][$unit] += $row['quantity_units'][$unit];
            }
        }

        return $this->rankRows($products);
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
                        'quantity_units' => ['pcs' => 0.0, 'day' => 0.0],
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
                foreach (SalesLineItem::UNITS as $unit) {
                    $products[$key]['quantity_units'][$unit] += $item['quantity_units'][$unit];
                }
                $products[$key]['sales_value'] += $allocatedSalesValue;

                if ($weight > 0) {
                    $products[$key]['received_allocated'] += $received * $weight;
                }
            }
        }

        return $this->rankRows($products);
    }

    private function rankRows(array $products): Collection
    {
        return collect(array_values($products))
            ->map(function (array $row): array {
                $row['quantity'] = round((float) $row['quantity'], 2);
                $labels = [];
                foreach ($row['quantity_units'] as $unit => $quantity) {
                    if ($quantity > 0) {
                        $labels[] = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.').' '.ucfirst($unit);
                    }
                }
                $row['quantity_label'] = implode(' / ', $labels) ?: '0';
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
            'quantity' => SalesLineItem::physicalQuantity($item->getAttributes()),
            'billing_quantity' => SalesLineItem::display($item->getAttributes())['quantity'],
            'unit' => SalesLineItem::display($item->getAttributes())['unit'],
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
                    'quantity_units' => ['pcs' => 0.0, 'day' => 0.0],
                    'sales_value' => 0.0,
                ];
            }

            $grouped[$key]['quantity'] += max(0.0, (float) ($item['quantity'] ?? 0));
            $unit = in_array($item['unit'] ?? null, SalesLineItem::UNITS, true) ? $item['unit'] : 'pcs';
            $grouped[$key]['quantity_units'][$unit] += max(0.0, (float) ($item['billing_quantity'] ?? $item['quantity'] ?? 0));
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
}
