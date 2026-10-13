<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\FlexibleQuoteBillingService;
use Webkul\Admin\Services\ProformaFollowUpService;
use Webkul\Admin\Services\ProformaInvoiceService;
use Webkul\Invoice\Models\ProformaInvoice;

class ProformaInvoiceController extends Controller
{
    public function index(Request $request, ProformaFollowUpService $followUp): View
    {
        $this->assertPermission('invoices');
        $validated = $request->validate(['status' => ['nullable', 'in:issued,converted,cancelled']]);
        $status = $validated['status'] ?? null;

        $query = ProformaInvoice::query()->with('creator')->when($status, fn ($query) => $query->where('status', $status));
        if ($status === 'issued') {
            $query->whereNull('invoice_id')->orderBy('issued_at')->orderBy('id');
        } else {
            $query->latest('id');
        }

        return view('admin::invoices.proformas.index', [
            'proformas' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'proformaFollowUp' => $followUp->summary(),
        ]);
    }

    public function show(int $id): View
    {
        $this->assertPermission('invoices.view');

        return view('admin::invoices.proformas.show', [
            'proforma' => ProformaInvoice::with(['invoice', 'converter'])->findOrFail($id),
        ]);
    }

    public function print(int $id): View
    {
        $this->assertPermission('invoices.print');

        return view('admin::invoices.proformas.print', [
            'proforma' => ProformaInvoice::findOrFail($id),
        ]);
    }

    public function confirmPayment(Request $request, int $id, FlexibleQuoteBillingService $billing): RedirectResponse
    {
        $this->assertPermission('invoices.generate');
        $this->assertPermission('invoices.payment');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $invoice = $billing->confirmProformaPayment(
            ProformaInvoice::findOrFail($id), $validated, auth()->guard('user')->id()
        );

        session()->flash('success', 'Proforma dipindahkan ke Invoice '.$invoice->invoice_number.'. Status pembayaran: '.strtoupper($invoice->status).'.');

        return redirect()->route('admin.proformas.show', $id);
    }

    public function approve(Request $request, int $id, FlexibleQuoteBillingService $billing): RedirectResponse
    {
        $this->assertPermission('invoices.generate');
        $validated = $request->validate([
            'event_confirmed' => ['required', 'accepted'],
            'confirmation_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $invoice = $billing->approveProforma(
            ProformaInvoice::findOrFail($id), auth()->guard('user')->id(), $validated['confirmation_note'] ?? null
        );

        return redirect()->route('admin.proformas.show', $id)->with(
            'success', 'Proforma dipindahkan ke Invoice '.$invoice->invoice_number.'. Status pembayaran: '.strtoupper($invoice->status).'.'
        );
    }

    public function cancel(Request $request, int $id, ProformaInvoiceService $service): RedirectResponse
    {
        $this->assertPermission('invoices.event-status');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel(ProformaInvoice::findOrFail($id), $validated['reason'], auth()->guard('user')->id());

        return redirect()->route('admin.proformas.show', $id)->with('success', 'Proforma dibatalkan.');
    }

    private function assertPermission(string $permission): void
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission($permission), 403);
    }
}
