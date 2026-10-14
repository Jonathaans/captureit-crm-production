<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\PaymentVoidService;

class PaymentVoidController extends Controller
{
    public function preview(int $invoiceId, int $paymentId, PaymentVoidService $service): View
    {
        $this->assertPermission();

        return view('admin::invoices.payments.void', ['preview' => $service->preview($invoiceId, $paymentId)]);
    }

    public function store(Request $request, int $invoiceId, int $paymentId, PaymentVoidService $service): RedirectResponse
    {
        $this->assertPermission();
        $validated = $request->validate([
            'expected' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'acknowledge_error' => ['required', 'accepted'],
        ]);
        $result = $service->apply($invoiceId, $paymentId, $validated['expected'], $validated['reason'],
            (int) auth()->guard('user')->id(), true);

        return redirect()->to(route('admin.invoices.show', $invoiceId).'#payment-history')->with(
            'success', 'Pembayaran ditandai VOID. Status invoice: '.strtoupper($result['status']).'. Audit #'.$result['audit_id'].'.'
        );
    }

    private function assertPermission(): void
    {
        abort_unless(auth()->guard('user')->check()
            && bouncer()->hasPermission('invoices.view')
            && bouncer()->hasPermission('invoices.payment-void'), 403);
    }
}
