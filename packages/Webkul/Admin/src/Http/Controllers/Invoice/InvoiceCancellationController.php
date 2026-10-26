<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\InvoiceCancellationService;

class InvoiceCancellationController extends Controller
{
    public function preview(int $id, InvoiceCancellationService $service): View
    {
        $this->assertPermission();

        return view('admin::invoices.cancel', ['preview' => $service->preview($id)]);
    }

    public function store(Request $request, int $id, InvoiceCancellationService $service): RedirectResponse
    {
        $this->assertPermission();
        try {
            $result = $service->cancel($id, $request->only(['expected', 'reason', 'acknowledge']), (int) auth()->guard('user')->id());
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return redirect()->route('admin.invoices.cancel.preview', $id)->withErrors($e->errors())->withInput($request->only('reason'));
        }

        return redirect()->route('admin.invoices.show', $id)->with('success',
            'Invoice dibatalkan. Nilai tagihan dan piutangnya tidak lagi dihitung dalam Financial Report. Audit #'.$result['audit_id'].'.');
    }

    private function assertPermission(): void
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission('invoices.view')
            && bouncer()->hasPermission('invoices.cancel'), 403);
    }
}
