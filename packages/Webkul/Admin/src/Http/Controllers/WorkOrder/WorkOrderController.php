<?php

namespace Webkul\Admin\Http\Controllers\WorkOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\WorkOrderAccessService;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\WorkOrder;
use Webkul\Invoice\Services\WorkOrderDeliveryOrderService;
use Webkul\Invoice\Services\WorkOrderService;

class WorkOrderController extends Controller
{
    use PDFHandler;

    public function __construct(
        protected WorkOrderAccessService $access
    ) {}

    public function index(
        Request $request
    ): View {
        $user =
            $this->access->user();

        $this->access
            ->assertView(
                $user
            );

        $query =
            WorkOrder::query()
                ->withCount(
                    'deliveryOrders'
                )
                ->latest('id');

        /*
         * Sales User sees their own SPK only.
         * Sales Admin / Administrator / Operational can see all.
         */
        if (
            $this->access->roleName(
                $user
            ) === 'sales user'
        ) {
            $query->where(
                'user_id',
                $user->id
            );
        }

        $search =
            trim(
                (string) $request->input(
                    'search',
                    ''
                )
            );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $like =
                        '%'
                        .$search
                        .'%';

                    $builder
                        ->where(
                            'work_order_number',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'invoice_number',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'project_code',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'customer_name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'project_name',
                            'like',
                            $like
                        );
                }
            );
        }

        $workOrders =
            $query->paginate(
                30
            )->withQueryString();

        return view(
            'admin::work-orders.index',
            compact(
                'workOrders',
                'search'
            )
        );
    }

    /**
     * Legacy Invoice -> Surat Jalan POST route is repointed here by installer.
     * This makes stale buttons/bookmarks SAFE: they now generate/open SPK,
     * never a direct Surat Jalan.
     */
    public function storeFromInvoice(
        int $id,
        WorkOrderService $service
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertGenerateSpk(
                $user
            );

        $invoice =
            Invoice::query()
                ->findOrFail(
                    $id
                );

        $workOrder =
            $service->createFromInvoice(
                $invoice,
                $user->id
            );

        session()->flash(
            'success',
            'Surat Perintah Kerja siap: '
            .$workOrder->work_order_number
        );

        return redirect()->route(
            'admin.work-orders.show',
            $workOrder->id
        );
    }

    public function openForInvoice(
        int $id
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertView(
                $user
            );

        $workOrder =
            WorkOrder::query()
                ->where(
                    'invoice_id',
                    $id
                )
                ->first();

        if (! $workOrder) {
            session()->flash(
                'warning',
                'SPK belum dibuat untuk Invoice ini.'
            );

            return redirect()->route(
                'admin.invoices.show',
                $id
            );
        }

        return redirect()->route(
            'admin.work-orders.show',
            $workOrder->id
        );
    }

    public function show(
        int $id
    ): View {
        $user =
            $this->access->user();

        $this->access
            ->assertView(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        if (
            $this->access->roleName(
                $user
            ) === 'sales user'
            && (int) $workOrder->user_id
                !== (int) $user->id
        ) {
            abort(403);
        }

        $canEdit = $this->access->canEditSpk($user);
        $canPrint = $this->access->canPrint($user);
        $canUpdateStatus = $this->access->canUpdateStatus($user);

        $canGenerateDeliveryOrder =
            $this->access
                ->canGenerateDeliveryOrder(
                    $user
                );

        return view(
            'admin::work-orders.show',
            compact(
                'workOrder',
                'canEdit',
                'canPrint',
                'canUpdateStatus',
                'canGenerateDeliveryOrder'
            )
        );
    }

    public function edit(
        int $id
    ): View {
        $user =
            $this->access->user();

        $this->access
            ->assertEditSpk(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        if (
            $this->access->roleName(
                $user
            ) === 'sales user'
            && (int) $workOrder->user_id
                !== (int) $user->id
        ) {
            abort(403);
        }

        abort_if(
            strtolower(
                (string) $workOrder->status
            ) === 'cancelled',
            422,
            'SPK cancelled tidak dapat diedit.'
        );

        return view(
            'admin::work-orders.edit',
            compact(
                'workOrder'
            )
        );
    }

    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertEditSpk(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        if (
            $this->access->roleName(
                $user
            ) === 'sales user'
            && (int) $workOrder->user_id
                !== (int) $user->id
        ) {
            abort(403);
        }

        abort_if(
            strtolower(
                (string) $workOrder->status
            ) === 'cancelled',
            422,
            'SPK cancelled tidak dapat diedit.'
        );

        $validated =
            $request->validate([
                'event_date' => [
                    'nullable',
                    'date',
                ],

                'location' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:20000',
                ],

                'admin_sales_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'sales_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'operational_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'items' => [
                    'nullable',
                    'array',
                    'max:100',
                ],

                'items.*.product_id' => [
                    'nullable',
                    'integer',
                ],

                'items.*.name' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'items.*.notes' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);

        DB::transaction(
            function () use (
                $workOrder,
                $validated
            ) {
                $workOrder->update([
                    'event_date' => $validated[
                            'event_date'
                        ]
                        ?? null,

                    'location' => $validated[
                            'location'
                        ]
                        ?? null,

                    'notes' => $validated[
                            'notes'
                        ]
                        ?? null,

                    'admin_sales_name' => $validated[
                            'admin_sales_name'
                        ]
                        ?? null,

                    'sales_name' => $validated[
                            'sales_name'
                        ]
                        ?? null,

                    'operational_name' => $validated[
                            'operational_name'
                        ]
                        ?? null,
                ]);

                $workOrder
                    ->items()
                    ->delete();

                $sortOrder = 0;

                foreach (
                    $validated[
                        'items'
                    ]
                    ?? [] as $item
                ) {
                    $name =
                        trim(
                            (string) (
                                $item['name']
                                ?? ''
                            )
                        );

                    if ($name === '') {
                        continue;
                    }

                    $workOrder
                        ->items()
                        ->create([
                            'product_id' => ! empty(
                                $item[
                                    'product_id'
                                ]
                            )
                                    ? (int) $item[
                                        'product_id'
                                    ]
                                    : null,

                            'name' => $name,

                            'notes' => $item['notes']
                                ?? null,

                            'sort_order' => $sortOrder++,
                        ]);
                }
            }
        );

        session()->flash(
            'success',
            'SPK berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.work-orders.show',
            $workOrder->id
        );
    }

    public function print(
        int $id
    ): Response|StreamedResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertPrint(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        if (
            $this->access->roleName(
                $user
            ) === 'sales user'
            && (int) $workOrder->user_id
                !== (int) $user->id
        ) {
            abort(403);
        }

        $fileName =
            'SPK_'
            .str_replace(
                [
                    '/',
                    '\\',
                    ' ',
                ],
                [
                    '-',
                    '-',
                    '_',
                ],
                $workOrder
                    ->work_order_number
            );

        return $this->downloadPDF(
            view(
                'admin::work-orders.print',
                compact(
                    'workOrder'
                )
            )->render(),
            $fileName
        );
    }

    public function generateDeliveryOrder(
        int $id,
        WorkOrderDeliveryOrderService $service
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertGenerateDeliveryOrder(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        try {
            $deliveryOrder =
                $service->create(
                    $workOrder,
                    $user->id
                );
        } catch (\Throwable $exception) {
            session()->flash(
                'error',
                $exception->getMessage()
            );

            return back();
        }

        session()->flash(
            'success',
            'Surat Jalan berhasil dibuat dari '
            .$workOrder->work_order_number
            .'.'
        );

        return redirect()->route(
            'admin.delivery-orders.show',
            $deliveryOrder->id
        );
    }

    public function release(
        int $id
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertUpdateStatus(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        abort_unless(
            strtolower(
                (string) $workOrder->status
            ) === 'draft',
            422
        );

        $workOrder->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        session()->flash(
            'success',
            'SPK di-Release ke Operational.'
        );

        return back();
    }

    public function complete(
        int $id
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertUpdateStatus(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        abort_if(
            strtolower(
                (string) $workOrder->status
            ) === 'cancelled',
            422
        );

        $workOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        session()->flash(
            'success',
            'SPK ditandai Completed.'
        );

        return back();
    }

    public function cancel(
        int $id
    ): RedirectResponse {
        $user =
            $this->access->user();

        $this->access
            ->assertUpdateStatus(
                $user
            );

        $workOrder =
            $this->findWorkOrder(
                $id
            );

        $activeDeliveryOrders =
            $workOrder
                ->deliveryOrders()
                ->whereNotIn(
                    'status',
                    [
                        'cancelled',
                        'returned',
                    ]
                )
                ->count();

        abort_if(
            $activeDeliveryOrders > 0,
            422,
            'SPK tidak dapat dibatalkan selama masih memiliki Surat Jalan aktif.'
        );

        $workOrder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        session()->flash(
            'success',
            'SPK dibatalkan.'
        );

        return back();
    }

    private function findWorkOrder(
        int $id
    ): WorkOrder {
        return WorkOrder::query()
            ->with([
                'items',
                'deliveryOrders.items',
            ])
            ->findOrFail(
                $id
            );
    }
}
