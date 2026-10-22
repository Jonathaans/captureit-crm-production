<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\InvoiceReplacementService;

class InvoiceReplacementController extends Controller
{
    public function preview(int $id, InvoiceReplacementService $service)
    {
        $this->authorizeReplacement();

        return view('admin::invoices.replacements.preview', ['preview' => $service->preview($id)]);
    }

    public function store(Request $request, int $id, InvoiceReplacementService $service)
    {
        $this->authorizeReplacement();
        $request->validate(['acknowledge' => ['required', 'accepted']]);
        try {
            $invoice = $service->replace($id, $request->all(), (int) auth()->guard('user')->id());
        } catch (ValidationException $e) {
            if (isset($e->errors()['expected'])) {
                return redirect()->route('admin.invoices.replacements.preview', $id)->withErrors($e->errors())->withInput([]);
            }

            throw $e;
        }

        return redirect()->route('admin.invoices.show', $invoice->id)
            ->with('success', 'Invoice pengganti: '.$invoice->invoice_number.'. Status saat ini: '.strtoupper($invoice->status).'. Aksi penggantian tidak mencatat pembayaran.');
    }

    private function authorizeReplacement(): void
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission('invoices.view')
            && bouncer()->hasPermission('invoices.revise') && bouncer()->hasPermission('invoices.generate'), 403);
    }
}
