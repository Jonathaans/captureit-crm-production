<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\FinanceSalesDashboardService;

class FinanceSalesDashboardController extends Controller
{
    /* CRM_FINANCE_SALES_DASHBOARD_V1 */

    public function __construct(
        protected FinanceSalesDashboardService $dashboardService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeDashboard();

        $filters = $this->dashboardService->normalizeFilters($request->all());
        $dashboard = $this->dashboardService->build(
            $filters,
            (int) $request->input('per_page', 25)
        );

        return view(
            'admin::invoices.finance-sales-dashboard',
            $dashboard
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeDashboard();

        $filters = $this->dashboardService->normalizeFilters($request->all());
        $rows = $this->dashboardService->exportRows($filters);
        $fileName = 'finance-sales-collection-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $safeCell = static function ($value) {
                if (
                    is_string($value)
                    && preg_match('/^\s*[=+@-]/u', $value) === 1
                ) {
                    return "'".$value;
                }

                return $value;
            };

            fputcsv($handle, [
                'Invoice',
                'Project Code',
                'Customer',
                'Sales Owner',
                'Business Unit',
                'Billing Type',
                'Payment Status',
                'Invoice Value',
                'Paid To Date',
                'Outstanding',
                'Due Date',
                'Days Overdue',
            ], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map($safeCell, [
                    $row['invoice_number'],
                    $row['project_code'],
                    $row['customer'],
                    $row['salesperson'],
                    $row['business_unit_label'],
                    $row['billing_label'],
                    $row['payment_status_label'],
                    $row['invoice_value'],
                    $row['paid'],
                    $row['outstanding'],
                    $row['due_at_label'],
                    $row['days_overdue'],
                ]), ';', '"', '');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeDashboard(): void
    {
        abort_unless(auth()->guard('user')->check(), 403);

        abort_unless(
            bouncer()->hasPermission('invoices')
                || bouncer()->hasPermission('invoices.view')
                || bouncer()->hasPermission('invoices.financial-report'),
            403
        );
    }
}
