<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Services\PaymentReceiptService;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, int $id, PaymentReceiptService $service): RedirectResponse
    {
        $this->assertPermission('invoices.payment');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_date' => ['required', 'date_format:Y-m-d'],
            'paid_time' => ['required', 'date_format:H:i'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $invoice = Invoice::findOrFail($id);
        $data['paid_at'] = Carbon::createFromFormat('!Y-m-d H:i', $data['paid_date'].' '.$data['paid_time']);
        $data['created_by'] = auth()->guard('user')->id();
        unset($data['receipt'], $data['paid_date'], $data['paid_time']);

        try {
            $service->addPayment($invoice, $data, $request->file('receipt'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return redirect()->to(route('admin.invoices.show', $id).'#payment-history')
            ->with('success', 'Pembayaran berhasil ditambahkan.');
    }

    public function receipt(int $invoiceId, int $paymentId): StreamedResponse
    {
        $this->assertPermission('invoices.view');
        $payment = Invoice::findOrFail($invoiceId)->payments()->findOrFail($paymentId);
        $disk = Storage::disk('local');
        abort_unless($payment->receipt_path && $disk->exists($payment->receipt_path), 404);

        return $disk->download($payment->receipt_path, $payment->receipt_name ?: 'bon-pembayaran', [
            'Content-Type' => $payment->receipt_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function assertPermission(string $permission): void
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission($permission), 403);
    }
}
