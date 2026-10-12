<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\Invoice;

class InvoiceDeliveryOrderController extends Controller
{
    /**
     * Semua Surat Jalan milik satu Invoice / Event.
     */
    public function index(
        int $id
    ): View {
        $invoice = Invoice::query()
            ->with([
                'person',
                'user',
            ])
            ->findOrFail($id);

        $deliveryOrders = DeliveryOrder::query()
            ->withCount('items')
            ->whereIn(
                'invoice_id',
                $invoice->projectInvoiceIds()
            )
            ->orderBy('id')
            ->get();

        $statusCounts = $deliveryOrders
            ->groupBy(
                fn ($deliveryOrder) => strtolower(
                    (string) (
                        $deliveryOrder->status
                        ?: 'draft'
                    )
                )
            )
            ->map
            ->count();

        return view(
            'admin::invoices.delivery-orders.index',
            compact(
                'invoice',
                'deliveryOrders',
                'statusCounts'
            )
        );
    }
}
