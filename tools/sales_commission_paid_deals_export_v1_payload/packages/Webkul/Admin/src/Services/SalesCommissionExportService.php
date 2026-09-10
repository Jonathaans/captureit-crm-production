<?php

declare(strict_types=1);

namespace Webkul\Admin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Invoice\Models\Invoice;

/**
 * CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1
 *
 * Commission is recognized once per deal only after the complete commercial
 * value has been invoiced and paid. A paid DP without a paid settlement is
 * deliberately excluded.
 */
class SalesCommissionExportService
{
    private const TOLERANCE = 0.009;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters): Collection
    {
        $filters = $this->normalizeFilters($filters);

        $invoices = Invoice::query()
            ->with([
                'items',
                'payments',
                'person',
                'user',
                'quote.items',
                'quote.person',
                'quote.user',
            ])
            ->where('event_status', 'confirm')
            ->whereHas('payments')
            ->orderBy('id')
            ->get();

        $rows = collect();

        $invoices
            ->whereNotNull('quote_id')
            ->groupBy(fn (Invoice $invoice): int => (int) $invoice->quote_id)
            ->each(function (Collection $dealInvoices) use ($rows, $filters): void {
                $row = $this->quoteDealRow($dealInvoices);

                if ($row !== null && $this->matchesFilters($row, $filters)) {
                    $rows->push($row);
                }
            });

        $invoices
            ->whereNull('quote_id')
            ->each(function (Invoice $invoice) use ($rows, $filters): void {
                $row = $this->standaloneInvoiceRow($invoice);

                if ($row !== null && $this->matchesFilters($row, $filters)) {
                    $rows->push($row);
                }
            });

        return $rows
            ->unique('deal_key')
            ->sortBy(fn (array $row): string => mb_strtolower(
                (string) $row['sales_name'].'|'.(string) $row['paid_completion_date'].'|'.(string) $row['project_code']
            ))
            ->values();
    }

    /**
     * Pure rule used by runtime code and checker simulations.
     *
     * @param  array<int, array{billing_type?: mixed, invoice_total?: mixed, paid_total?: mixed, status?: mixed}>  $positions
     */
    public function isCommissionEligible(array $positions, float $dealValue): bool
    {
        if ($dealValue <= 0 || $positions === []) {
            return false;
        }

        $types = [];
        $invoicedTotal = 0.0;
        $paidTotal = 0.0;

        foreach ($positions as $position) {
            $type = $this->normalizeBillingType($position['billing_type'] ?? null);

            if (! in_array($type, ['down_payment', 'settlement', 'full_payment'], true)) {
                return false;
            }

            $invoiceTotal = max(0.0, (float) ($position['invoice_total'] ?? 0));
            $invoicePaid = max(0.0, (float) ($position['paid_total'] ?? 0));
            $status = mb_strtolower(trim((string) ($position['status'] ?? '')));

            if ($invoiceTotal <= 0
                || $status !== 'paid'
                || $invoicePaid + self::TOLERANCE < $invoiceTotal) {
                return false;
            }

            $types[] = $type;
            $invoicedTotal += $invoiceTotal;
            $paidTotal += $invoicePaid;
        }

        $hasFullPayment = in_array('full_payment', $types, true);
        $hasDownPayment = in_array('down_payment', $types, true);
        $hasSettlement = in_array('settlement', $types, true);

        $isFullPaymentPath = $hasFullPayment && ! $hasDownPayment && ! $hasSettlement;
        $isStagedPath = ! $hasFullPayment && $hasDownPayment && $hasSettlement;

        return ($isFullPaymentPath || $isStagedPath)
            && $invoicedTotal + self::TOLERANCE >= $dealValue
            && $paidTotal + self::TOLERANCE >= $dealValue;
    }

    /**
     * @param  array<int, array{billing_type?: mixed, invoice_total?: mixed, paid_total?: mixed, status?: mixed}>  $positions
     */
    public function commissionBase(array $positions, float $dealValue): float
    {
        if (! $this->isCommissionEligible($positions, $dealValue)) {
            return 0.0;
        }

        $received = array_reduce(
            $positions,
            fn (float $carry, array $position): float => $carry + max(0.0, (float) ($position['paid_total'] ?? 0)),
            0.0,
        );

        return round(min($dealValue, $received), 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{sales_name: string, deal_count: int, commission_base: float}>
     */
    public function summary(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) ($row['sales_user_id'] ?: 'name:'.$row['sales_name']))
            ->map(function (Collection $salesRows): array {
                return [
                    'sales_name' => (string) $salesRows->first()['sales_name'],
                    'deal_count' => $salesRows->count(),
                    'commission_base' => round((float) $salesRows->sum('commission_base'), 2),
                ];
            })
            ->sortBy(fn (array $row): string => mb_strtolower($row['sales_name']))
            ->values();
    }

    /** @return array{paid_from: string, paid_to: string, sales_user_id: int, business_unit: string} */
    public function normalizeFilters(array $filters): array
    {
        $today = CarbonImmutable::today();
        $from = trim((string) ($filters['paid_from'] ?? $today->startOfYear()->toDateString()));
        $to = trim((string) ($filters['paid_to'] ?? $today->toDateString()));

        $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay();
        $toDate = CarbonImmutable::createFromFormat('Y-m-d', $to)->endOfDay();

        if ($fromDate->greaterThan($toDate)) {
            throw new \InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
        }

        return [
            'paid_from' => $fromDate->toDateString(),
            'paid_to' => $toDate->toDateString(),
            'sales_user_id' => max(0, (int) ($filters['sales_user_id'] ?? 0)),
            'business_unit' => trim((string) ($filters['business_unit'] ?? '')),
        ];
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function quoteDealRow(Collection $invoices): ?array
    {
        /** @var Invoice|null $first */
        $first = $invoices->first();

        if ($first === null) {
            return null;
        }

        $quote = $invoices->pluck('quote')->filter()->first();
        $quoteTotal = max(0.0, (float) ($quote?->grand_total ?? 0));
        $snapshotTotal = max(0.0, (float) $invoices->max('quote_total_snapshot'));
        $dealValue = $quoteTotal > 0
            ? $quoteTotal
            : ($snapshotTotal > 0 ? $snapshotTotal : max(0.0, (float) $invoices->sum('grand_total')));
        $positions = $this->positions($invoices);

        if (! $this->isCommissionEligible($positions, $dealValue)) {
            return null;
        }

        $completion = $this->completionDate($invoices, $dealValue);

        if ($completion === null) {
            return null;
        }

        /** @var Invoice $representative */
        $representative = $invoices->first(
            fn (Invoice $invoice): bool => $this->normalizeBillingType($invoice->billing_type) === 'settlement'
        ) ?? $invoices->first(
            fn (Invoice $invoice): bool => $this->normalizeBillingType($invoice->billing_type) === 'full_payment'
        ) ?? $first;

        $salesUser = $representative->user ?? $quote?->user ?? $invoices->pluck('user')->filter()->first();
        $customer = $representative->person ?? $quote?->person ?? $invoices->pluck('person')->filter()->first();
        $businessUnit = trim((string) ($representative->business_unit ?? $quote?->business_unit ?? ''));
        $products = $this->productNames($quote?->items ?? collect(), $invoices);
        $dpNumbers = $this->invoiceNumbers($invoices, ['down_payment']);
        $terminalNumbers = $this->invoiceNumbers($invoices, ['settlement', 'full_payment']);
        $hasSettlement = $invoices->contains(
            fn (Invoice $invoice): bool => $this->normalizeBillingType($invoice->billing_type) === 'settlement'
        );

        return [
            'deal_key' => 'quote:'.(int) $first->quote_id,
            'sales_user_id' => (int) ($salesUser?->id ?? $representative->user_id ?? 0),
            'sales_name' => trim((string) ($salesUser?->name ?? '-')) ?: '-',
            'business_unit_code' => $businessUnit,
            'business_unit' => $businessUnit !== '' ? BusinessUnit::label($businessUnit) : '-',
            'quote_number' => (string) ($quote?->quote_number ?? '-'),
            'dp_invoice' => $dpNumbers !== '' ? $dpNumbers : '-',
            'terminal_invoice' => $terminalNumbers !== '' ? $terminalNumbers : '-',
            'invoice_numbers' => $this->invoiceNumbers($invoices),
            'project_code' => (string) ($quote?->project_code ?? $representative->project_code ?? '-'),
            'project_name' => (string) ($quote?->subject ?? $representative->subject ?? '-'),
            'customer' => trim((string) ($customer?->name ?? '-')) ?: '-',
            'product' => $products !== '' ? $products : '-',
            'billing_path' => $hasSettlement ? 'DP + PELUNASAN' : 'FULL PAYMENT',
            'deal_value' => round($dealValue, 2),
            'total_received' => round((float) collect($positions)->sum('paid_total'), 2),
            'paid_completion_date' => $completion->toDateString(),
            'status' => 'LUNAS',
            'commission_base' => $this->commissionBase($positions, $dealValue),
        ];
    }

    private function standaloneInvoiceRow(Invoice $invoice): ?array
    {
        if ($this->normalizeBillingType($invoice->billing_type) !== 'full_payment') {
            return null;
        }

        $dealValue = max(0.0, (float) $invoice->grand_total);
        $positions = $this->positions(collect([$invoice]));

        if (! $this->isCommissionEligible($positions, $dealValue)) {
            return null;
        }

        $completion = $this->completionDate(collect([$invoice]), $dealValue);

        if ($completion === null) {
            return null;
        }

        $businessUnit = trim((string) ($invoice->business_unit ?? ''));
        $products = $this->productNames(collect(), collect([$invoice]));

        return [
            'deal_key' => 'invoice:'.(int) $invoice->id,
            'sales_user_id' => (int) ($invoice->user?->id ?? $invoice->user_id ?? 0),
            'sales_name' => trim((string) ($invoice->user?->name ?? '-')) ?: '-',
            'business_unit_code' => $businessUnit,
            'business_unit' => $businessUnit !== '' ? BusinessUnit::label($businessUnit) : '-',
            'quote_number' => '-',
            'dp_invoice' => '-',
            'terminal_invoice' => (string) ($invoice->invoice_number ?? '-'),
            'invoice_numbers' => (string) ($invoice->invoice_number ?? '-'),
            'project_code' => (string) ($invoice->project_code ?? '-'),
            'project_name' => (string) ($invoice->subject ?? '-'),
            'customer' => trim((string) ($invoice->person?->name ?? '-')) ?: '-',
            'product' => $products !== '' ? $products : '-',
            'billing_path' => 'FULL PAYMENT',
            'deal_value' => round($dealValue, 2),
            'total_received' => round((float) collect($positions)->sum('paid_total'), 2),
            'paid_completion_date' => $completion->toDateString(),
            'status' => 'LUNAS',
            'commission_base' => $this->commissionBase($positions, $dealValue),
        ];
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function positions(Collection $invoices): array
    {
        return $invoices->map(function (Invoice $invoice): array {
            return [
                'billing_type' => $invoice->billing_type,
                'invoice_total' => (float) $invoice->grand_total,
                'paid_total' => (float) $invoice->payments->sum('amount'),
                'status' => $invoice->status,
            ];
        })->values()->all();
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function completionDate(Collection $invoices, float $dealValue): ?CarbonImmutable
    {
        $payments = $invoices
            ->flatMap(fn (Invoice $invoice): Collection => $invoice->payments)
            ->filter(fn ($payment): bool => $payment->paid_at !== null)
            ->sortBy(fn ($payment): string => $payment->paid_at->format('Y-m-d H:i:s.u').'-'.str_pad((string) $payment->id, 20, '0', STR_PAD_LEFT));

        $runningTotal = 0.0;

        foreach ($payments as $payment) {
            $runningTotal += max(0.0, (float) $payment->amount);

            if ($runningTotal + self::TOLERANCE >= $dealValue) {
                return CarbonImmutable::instance($payment->paid_at);
            }
        }

        return null;
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function productNames(Collection $quoteItems, Collection $invoices): string
    {
        $names = $quoteItems->pluck('name')->filter();

        if ($names->isEmpty()) {
            $names = $invoices->flatMap(fn (Invoice $invoice): Collection => $invoice->items->pluck('name'));
        }

        return $names
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->implode(' | ');
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function invoiceNumbers(Collection $invoices, ?array $types = null): string
    {
        return $invoices
            ->filter(function (Invoice $invoice) use ($types): bool {
                return $types === null || in_array($this->normalizeBillingType($invoice->billing_type), $types, true);
            })
            ->sortBy('id')
            ->pluck('invoice_number')
            ->filter()
            ->unique()
            ->implode(' | ');
    }

    /** @param  array<string, mixed>  $row */
    private function matchesFilters(array $row, array $filters): bool
    {
        if ((string) $row['paid_completion_date'] < $filters['paid_from']
            || (string) $row['paid_completion_date'] > $filters['paid_to']) {
            return false;
        }

        if ($filters['sales_user_id'] > 0
            && (int) $row['sales_user_id'] !== $filters['sales_user_id']) {
            return false;
        }

        return $filters['business_unit'] === ''
            || (string) $row['business_unit_code'] === $filters['business_unit'];
    }

    private function normalizeBillingType(mixed $type): string
    {
        $type = mb_strtolower(trim((string) $type));

        return match ($type) {
            '', 'full', 'full-payment', 'full payment' => 'full_payment',
            'dp', 'down-payment', 'down payment' => 'down_payment',
            'pelunasan' => 'settlement',
            default => $type,
        };
    }
}
