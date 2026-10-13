<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\WorkOrder;

class WorkOrderService
{
    public function __construct(
        protected WorkOrderNumberService $numberService
    ) {}

    /**
     * One DP/full invoice and its settlement share one SPK.
     *
     * Idempotent:
     * if SPK already exists, the existing SPK is returned.
     */
    public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null
    ): WorkOrder {
        return DB::transaction(
            function () use (
                $invoice,
                $createdBy
            ) {
                $source = $invoice;
                $invoice =
                    Invoice::query()
                        ->whereKey(
                            $invoice->operationalInvoiceId()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($invoice->status === 'void' || $source->status === 'void') {
                    throw ValidationException::withMessages(['invoice' => 'Invoice VOID tidak dapat membuat SPK baru.']);
                }

                if ($source->id !== $invoice->id && (
                    (int) $source->quote_id !== (int) $invoice->quote_id
                    || $invoice->billing_type !== 'down_payment'
                )) {
                    throw ValidationException::withMessages(['invoice' => 'Referensi invoice DP tidak sesuai penawaran.']);
                }

                $existing = $this->findForInvoice($invoice);

                if ($existing) {
                    return $existing;
                }

                if (in_array($invoice->event_status, ['cancel', 'cancelled', 'canceled'], true)) {
                    throw ValidationException::withMessages(['invoice' => 'Invoice yang dibatalkan tidak dapat membuat SPK.']);
                }

                $invoice->loadMissing([
                    'quote',
                    'person',
                    'user',
                    'items',
                ]);

                $workOrder =
                    WorkOrder::query()
                        ->create([
                            'work_order_number' => $this->numberService
                                ->generate(
                                    $invoice->event_date
                                    ?: now()
                                ),

                            'invoice_id' => $invoice->id,

                            'invoice_number' => $invoice->invoice_number,

                            'quote_id' => $invoice->quote_id,

                            'quote_number' => $invoice->quote?->quote_number,

                            'project_code' => $invoice->project_code,

                            'business_unit' => $invoice->business_unit,

                            'project_name' => $invoice->subject,

                            'person_id' => $invoice->person_id,

                            'customer_name' => $invoice->person?->name,

                            'user_id' => $invoice->user_id,

                            'sales_person_name' => $invoice->user?->name,

                            'event_date' => $invoice->event_date,

                            'location' => $invoice->location,

                            'notes' => null,

                            'status' => 'draft',

                            /*
                             * Sales signature name is prefilled from Invoice.
                             * Admin Sales + Operational remain editable.
                             */
                            'admin_sales_name' => null,

                            'sales_name' => $invoice->user?->name,

                            'operational_name' => null,

                            'created_by' => $createdBy,
                        ]);

                $sortOrder = 0;

                foreach ($invoice->items as $invoiceItem) {
                    $name =
                        trim(
                            (string) (
                                $invoiceItem->name
                                ?? ''
                            )
                        );

                    if ($name === '') {
                        continue;
                    }

                    $workOrder->items()
                        ->create([
                            'product_id' => $invoiceItem->product_id,

                            'name' => $name,

                            'notes' => null,

                            'sort_order' => $sortOrder++,
                        ]);
                }

                return $workOrder->fresh([
                    'items',
                ]);
            }
        );
    }

    public function findForInvoice(Invoice $invoice): ?WorkOrder
    {
        // Include legacy SPKs already attached to a settlement. Preserve them
        // instead of creating a second operational document during rollout.
        return WorkOrder::whereIn('invoice_id', $invoice->projectInvoiceIds())->orderBy('id')->first();
    }
}
