<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Quote\Models\Quote;

class FlexibleQuoteBillingController extends Controller
{
    public function __construct(
        protected FlexibleQuoteBillingService $billingService
    ) {
    }

    public function create(Request $request): View
    {
        $this->authorizeBilling();

        $quotes = Quote::query()
            ->with('person')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'quote_number',
                'project_code',
                'subject',
                'grand_total',
                'person_id',
                'created_at',
            ]);

        $selectedQuote = null;
        $billingSummary = null;
        $selectedId = (int) (
            old('quote_id')
            ?: $request->integer('quote_id')
        );

        if ($selectedId > 0) {
            $selectedQuote = Quote::query()
                ->with(['person', 'items'])
                ->find($selectedId);

            if ($selectedQuote) {
                $billingSummary = $this->billingService
                    ->summarize($selectedQuote);
            }
        }

        return view(
            'admin::invoices.billing-create',
            compact(
                'quotes',
                'selectedQuote',
                'billingSummary'
            )
        );
    }

    public function summary(int $quoteId): JsonResponse
    {
        $this->authorizeBilling();

        $quote = Quote::query()->findOrFail($quoteId);

        return response()->json([
            'data' => $this->billingService->summarize($quote),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeBilling();

        $validated = $request->validate([
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'billing_type' => [
                'required',
                'in:down_payment,full_payment,settlement',
            ],
            'billing_method' => [
                'nullable',
                'required_if:billing_type,down_payment',
                'in:percentage,nominal',
            ],
            'billing_percentage' => [
                'nullable',
                'numeric',
                'gt:0',
                'lt:100',
            ],
            'billing_amount' => [
                'nullable',
                'required_if:billing_type,down_payment',
                'numeric',
                'gt:0',
            ],
            'issued_at' => ['nullable', 'date'],
            'due_at' => [
                'nullable',
                'date',
                'after_or_equal:issued_at',
            ],
        ], [
            'billing_amount.required_if' =>
                'Nominal DP wajib diisi.',
            'billing_method.required_if' =>
                'Pilih metode DP Persentase atau Nominal.',
            'due_at.after_or_equal' =>
                'Due Date tidak boleh sebelum tanggal Invoice.',
        ]);

        $quote = Quote::query()->findOrFail(
            (int) $validated['quote_id']
        );

        $invoice = $this->billingService->createFromQuote(
            $quote,
            $validated,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            $this->successMessage($invoice)
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    public function legacy(int $quoteId): RedirectResponse
    {
        $this->authorizeBilling();

        return redirect()->route(
            'admin.invoices.billing.create',
            ['quote_id' => $quoteId]
        );
    }

    private function authorizeBilling(): void
    {
        abort_unless(auth()->guard('user')->check(), 403);

        abort_unless(
            bouncer()->hasPermission('invoices')
                || bouncer()->hasPermission('invoices.edit')
                || bouncer()->hasPermission('invoices.create'),
            403
        );
    }

    private function successMessage($invoice): string
    {
        $label = match ($invoice->billing_type) {
            FlexibleQuoteBillingService::TYPE_DOWN_PAYMENT =>
                'Down Payment',
            FlexibleQuoteBillingService::TYPE_SETTLEMENT =>
                'Pelunasan',
            default =>
                'Full Payment',
        };

        return 'Invoice '.$label.' berhasil dibuat: '
            .$invoice->invoice_number.'. Nilai komersial sudah dikunci.';
    }
}
