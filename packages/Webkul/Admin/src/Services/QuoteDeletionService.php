<?php

namespace Webkul\Admin\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Webkul\Quote\Repositories\QuoteRepository;

class QuoteDeletionService
{
    public function __construct(
        protected QuoteRepository $quotes,
        protected CrmReadOnlyArchivePolicyService $archivePolicy,
    ) {}

    public function delete(array $ids, Closure $authorize): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::transaction(function () use ($ids, $authorize): void {
            $quotes = $this->quotes->getModel()->newQuery()
                ->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

            if ($ids === [] || $quotes->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'indices' => 'Quotation tidak ditemukan. Muat ulang daftar dan pilih kembali.',
                ]);
            }

            // Check the entire selection before any events or attribute cleanup.
            foreach ($quotes as $quote) {
                $authorize($quote);

                if ($reason = $this->archivePolicy->archiveReason($quote)) {
                    throw ValidationException::withMessages([
                        'quotes' => 'Quotation #'.$quote->id.' tidak dapat dihapus. '.$reason,
                    ]);
                }
            }

            foreach ($quotes as $quote) {
                Event::dispatch('quote.delete.before', $quote->id);

                if (! $this->quotes->delete($quote->id)) {
                    throw new RuntimeException('Quote deletion was rejected: '.$quote->id);
                }

                Event::dispatch('quote.delete.after', $quote->id);
            }
        });
    }
}
