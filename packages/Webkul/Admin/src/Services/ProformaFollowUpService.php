<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Webkul\Invoice\Models\ProformaInvoice;

/** Operational follow-up only. Never a source for financial report totals. */
class ProformaFollowUpService
{
    public function pending(array $filters = []): Collection
    {
        return ProformaInvoice::query()
            ->with('creator')
            ->where('status', 'issued')
            ->whereNull('invoice_id')
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get()
            ->filter(function (ProformaInvoice $proforma) use ($filters): bool {
                $snapshot = $proforma->quote_snapshot;

                if (! empty($filters['business_unit'])
                    && ($snapshot['attributes']['business_unit'] ?? null) !== $filters['business_unit']) {
                    return false;
                }

                if (! empty($filters['product'])) {
                    return collect($snapshot['items'] ?? [])->contains(
                        fn (array $item): bool => trim((string) ($item['name'] ?? '')) === $filters['product']
                    );
                }

                return true;
            })
            ->values();
    }

    public function summary(array $filters = []): array
    {
        // No financial period/event-status filter: old pending documents need follow-up too.
        $rows = $this->pending($filters);

        return [
            'count' => $rows->count(),
            'amount' => (float) $rows->sum('amount'),
            'overdue_count' => $rows->filter(fn (ProformaInvoice $row): bool => $row->due_at?->lt(today()) ?? false)->count(),
            'rows' => $rows->take(10),
        ];
    }
}
