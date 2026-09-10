<?php

declare(strict_types=1);

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\SalesCommissionExportService;

/** CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1 */
class SalesCommissionExportController extends Controller
{
    public function __construct(
        private readonly SalesCommissionExportService $commissionExport,
    ) {
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeDashboard();

        $input = [
            'paid_from' => $request->input('paid_from', now()->startOfYear()->toDateString()),
            'paid_to' => $request->input('paid_to', now()->toDateString()),
            'sales_user_id' => (int) $request->input('sales_user_id', 0),
            'business_unit' => $request->input('business_unit', ''),
        ];

        $filters = Validator::make($input, [
            'paid_from' => ['required', 'date_format:Y-m-d'],
            'paid_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:paid_from'],
            'sales_user_id' => ['nullable', 'integer', 'min:0'],
            'business_unit' => ['nullable', 'string', 'max:80'],
        ])->validate();

        $filters = $this->commissionExport->normalizeFilters($filters);
        $rows = $this->commissionExport->rows($filters);
        $summary = $this->commissionExport->summary($rows);
        $filename = 'sales-commission-paid-deals-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters, $rows, $summary): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('CSV output tidak dapat dibuka.');
            }

            fwrite($output, "\xEF\xBB\xBF");

            $safeCell = static function (mixed $value): string|int|float {
                if (is_int($value) || is_float($value)) {
                    return $value;
                }

                $value = str_replace(["\r\n", "\r", "\n"], ' ', trim((string) $value));

                if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                    return "'".$value;
                }

                return $value;
            };

            $write = static function (array $cells) use ($output, $safeCell): void {
                fputcsv($output, array_map($safeCell, $cells), ';');
            };

            $write(['EXPORT KOMISI SALES — DEAL LUNAS']);
            $write(['Periode pelunasan', $filters['paid_from'].' s/d '.$filters['paid_to']]);
            $write(['Kebijakan', 'DP saja tidak dihitung; DP + Pelunasan diakui satu kali setelah seluruh deal lunas.']);
            $write([]);
            $write(['RINGKASAN PER SALES']);
            $write(['Sales', 'Deal Lunas', 'Dasar Komisi']);

            foreach ($summary as $sales) {
                $write([
                    $sales['sales_name'],
                    $sales['deal_count'],
                    $sales['commission_base'],
                ]);
            }

            $write([]);
            $write(['DETAIL DEAL LUNAS']);
            $write([
                'Sales',
                'Business Unit',
                'Quote Number',
                'DP Invoice',
                'Pelunasan / Full Payment Invoice',
                'Semua Invoice',
                'Project Code',
                'Project Name',
                'Customer',
                'Product',
                'Skema Billing',
                'Nilai Deal',
                'Total Diterima',
                'Tanggal Lunas',
                'Status',
                'Dasar Komisi',
            ]);

            foreach ($rows as $row) {
                $write([
                    $row['sales_name'],
                    $row['business_unit'],
                    $row['quote_number'],
                    $row['dp_invoice'],
                    $row['terminal_invoice'],
                    $row['invoice_numbers'],
                    $row['project_code'],
                    $row['project_name'],
                    $row['customer'],
                    $row['product'],
                    $row['billing_path'],
                    $row['deal_value'],
                    $row['total_received'],
                    $row['paid_completion_date'],
                    $row['status'],
                    $row['commission_base'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function authorizeDashboard(): void
    {
        $user = auth()->guard('user')->user();

        abort_unless($user, 403);

        $allowed = $user->hasPermission('invoices')
            || $user->hasPermission('invoices.view')
            || $user->hasPermission('invoices.financial-report');

        abort_unless($allowed, 403);
    }
}
