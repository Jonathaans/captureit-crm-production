<?php

namespace Webkul\Admin\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\FinancialReportAuditDashboardService;
use Webkul\Admin\Services\OperationsDashboardService;

class OperationsDashboardController extends Controller
{
    public function index(
        Request $request,
        OperationsDashboardService $service,
        FinancialReportAuditDashboardService $financialAudit
    ): View {
        $this->authorizeAdministrator();

        return view('admin::operations-dashboard.index', $service->build(auth()->guard('user')->user()) + [
            'financialAudit' => $financialAudit->build($request),
        ]);
    }

    public function financialAuditExport(Request $request, FinancialReportAuditDashboardService $financialAudit): JsonResponse
    {
        $this->authorizeAdministrator();
        $result = $financialAudit->run($financialAudit->filters($request));

        return response()->json($result, 200, [
            'Content-Disposition' => 'attachment; filename="financial-report-audit-'.now()->format('Ymd-His').'.json"',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        $user =
            auth()->guard('user')->user();

        $user->loadMissing('role');

        /*
         * OPERATIONS DASHBOARD ADMINISTRATOR ONLY V1
         *
         * Backend hard lock:
         * even if another role accidentally receives the
         * "operations-dashboard" ACL permission, access is still denied.
         */
        abort_unless(
            strtolower(
                trim(
                    (string) (
                        $user->role?->name
                        ?? ''
                    )
                )
            ) === 'administrator',
            403
        );

        /*
         * Keep the existing ACL as a second security layer.
         */
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'operations-dashboard'
            )
        ) {
            abort(403);
        }
    }
}
