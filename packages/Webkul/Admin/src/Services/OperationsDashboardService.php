<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class OperationsDashboardService
{
    public function build(
        $user
    ): array {
        $role =
            trim(
                (string) (
                    $user?->role?->name
                    ?? 'Unknown'
                )
            );

        $roleLower =
            strtolower(
                $role
            );

        $isAdministrator =
            $roleLower === 'administrator';

        $isFinance =
            $roleLower === 'admin finance';

        $isWarehouse =
            $roleLower === 'head warehouse';

        $isSales =
            in_array(
                $roleLower,
                [
                    'sales admin',
                    'sales user',
                ],
                true
            );

        $cards = [];

        if (
            $isAdministrator
            || $isSales
        ) {
            $cards[] =
                $this->salesLeadsCard(
                    $user,
                    $roleLower
                );

            $cards[] =
                $this->quotesCard(
                    $user,
                    $roleLower
                );

            $cards[] =
                $this->upcomingEventsCard(
                    $user,
                    $roleLower
                );
        }

        if (
            $isAdministrator
            || $isFinance
        ) {
            $cards[] =
                $this->outstandingCard();

            $cards[] =
                $this->expenseCard();

            $cards[] =
                $this->purchaseOrdersCard();
        }

        if (
            $isAdministrator
            || $isWarehouse
        ) {
            $cards[] =
                $this->deliveryOrdersCard();

            $cards[] =
                $this->inventoryMovementCard();
        }

        $cards[] =
            $this->notificationsCard(
                (int) $user->id
            );

        if ($isAdministrator) {
            $cards[] =
                $this->incidentsCard();
        }

        /* CRM_FULL_QA_BACKUP_CENTER_V1 */
        $qa = app(CrmFlowQualityAssuranceService::class)->run(request()->boolean('refresh_qa'));
        $backup = app(CrmBackupStatusService::class)->summary();

        /* CRM_PRODUCTION_OPERATIONS_V2 */
        $operationsHealth = app(\Webkul\Admin\Services\CrmOperationalHealthService::class)->summary();

        return [
            'role' => $role,
            'operationsHealth' => $operationsHealth,
            'qa' => $qa,
            'backup' => $backup,
            'cards' => array_values(
                array_filter(
                    $cards
                )
            ),
            'links' =>
                $this->quickLinks(
                    $isAdministrator,
                    $isFinance
                ),
        ];
    }

    private function salesLeadsCard(
        $user,
        string $role
    ): ?array {
        if (! Schema::hasTable('leads')) {
            return null;
        }

        $query =
            app(\Webkul\Admin\Services\SalesLeadAccessService::class)->scopeQuery(DB::table('leads'));

        if (
            $role === 'sales user'
            && Schema::hasColumn(
                'leads',
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $user->id
            );
        }

        return [
            'label' => 'Leads',
            'value' => (int) $query->count(),
            'hint' => $role === 'sales user'
                ? 'Owned by you'
                : 'Sales pipeline',
            'url' => $this->routeUrl(
                'admin.leads.index'
            ),
        ];
    }

    private function quotesCard(
        $user,
        string $role
    ): ?array {
        if (! Schema::hasTable('quotes')) {
            return null;
        }

        $query =
            DB::table('quotes');

        if (
            $role === 'sales user'
            && Schema::hasColumn(
                'quotes',
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $user->id
            );
        }

        return [
            'label' => 'Quotes',
            'value' => (int) $query->count(),
            'hint' => 'Quotation workload',
            'url' => $this->routeUrl(
                'admin.quotes.index'
            ),
        ];
    }

    private function upcomingEventsCard(
        $user,
        string $role
    ): ?array {
        if (
            ! Schema::hasTable(
                'google_calendar_events'
            )
        ) {
            return null;
        }

        $query =
            DB::table(
                'google_calendar_events'
            )
                ->whereNotNull(
                    'start_at'
                )
                ->where(
                    'start_at',
                    '>=',
                    now()
                )
                ->where(
                    'start_at',
                    '<=',
                    now()->addDays(14)
                )
                ->where(
                    'event_status',
                    'confirmed'
                );

        if (
            $role === 'sales user'
        ) {
            $query->where(
                'sales_owner_id',
                $user->id
            );
        }

        return [
            'label' => 'Upcoming Events',
            'value' => (int) $query->count(),
            'hint' => 'Next 14 days',
            'url' => $this->routeUrl(
                'admin.crm-notifications.index'
            ),
        ];
    }

    private function outstandingCard(): ?array
    {
        if (
            ! Schema::hasTable('invoices')
            || ! Schema::hasColumn(
                'invoices',
                'balance_due'
            )
        ) {
            return null;
        }

        $amount =
            (float) DB::table(
                'invoices'
            )
                ->where(
                    'balance_due',
                    '>',
                    0
                )
                ->sum(
                    'balance_due'
                );

        return [
            'label' => 'Outstanding',
            'value' =>
                'Rp '
                .number_format(
                    $amount,
                    0,
                    ',',
                    '.'
                ),
            'hint' => 'Open invoice balance',
            'url' => $this->routeUrl(
                'admin.invoices.index'
            ),
        ];
    }

    private function expenseCard(): ?array
    {
        if (
            ! Schema::hasTable('expenses')
            || ! Schema::hasColumn(
                'expenses',
                'amount'
            )
        ) {
            return null;
        }

        $query =
            DB::table('expenses');

        if (
            Schema::hasColumn(
                'expenses',
                'expense_date'
            )
        ) {
            $query
                ->whereDate(
                    'expense_date',
                    '>=',
                    now()
                        ->startOfMonth()
                        ->toDateString()
                )
                ->whereDate(
                    'expense_date',
                    '<=',
                    now()
                        ->endOfMonth()
                        ->toDateString()
                );
        }

        $amount =
            (float) $query
                ->sum('amount');

        return [
            'label' => 'Expenses This Month',
            'value' =>
                'Rp '
                .number_format(
                    $amount,
                    0,
                    ',',
                    '.'
                ),
            'hint' => now()->format('F Y'),
            'url' => $this->routeUrl(
                'admin.invoices.index'
            ),
        ];
    }

    private function purchaseOrdersCard(): ?array
    {
        if (
            ! Schema::hasTable(
                'purchase_orders'
            )
        ) {
            return null;
        }

        $count =
            DB::table(
                'purchase_orders'
            )
                ->whereIn(
                    'status',
                    [
                        'draft',
                        'released',
                    ]
                )
                ->count();

        return [
            'label' => 'Open Purchase Orders',
            'value' => (int) $count,
            'hint' => 'Draft + Released',
            'url' => $this->routeUrl(
                'admin.purchase-orders.index'
            ),
        ];
    }

    private function deliveryOrdersCard(): ?array
    {
        if (
            ! Schema::hasTable(
                'delivery_orders'
            )
        ) {
            return null;
        }

        return [
            'label' => 'Surat Jalan',
            'value' =>
                (int) DB::table(
                    'delivery_orders'
                )->count(),
            'hint' => 'Operational deliveries',
            'url' => $this->firstRouteUrl([
                'admin.delivery-orders.index',
                'admin.invoices.index',
            ]),
        ];
    }

    private function inventoryMovementCard(): ?array
    {
        if (
            ! Schema::hasTable(
                'inventory_stock_movements'
            )
        ) {
            return null;
        }

        $query =
            DB::table(
                'inventory_stock_movements'
            );

        if (
            Schema::hasColumn(
                'inventory_stock_movements',
                'created_at'
            )
        ) {
            $query->where(
                'created_at',
                '>=',
                now()->subDays(7)
            );
        }

        return [
            'label' => 'Inventory Movements',
            'value' => (int) $query->count(),
            'hint' => 'Last 7 days',
            'url' => $this->firstRouteUrl([
                'admin.inventory.movements.index',
                'admin.inventory.index',
            ]),
        ];
    }

    private function notificationsCard(
        int $userId
    ): ?array {
        if (
            ! Schema::hasTable(
                'crm_notifications'
            )
        ) {
            return null;
        }

        $count =
            DB::table(
                'crm_notifications'
            )
                ->whereNull(
                    'resolved_at'
                )
                ->whereNull(
                    'read_at'
                )
                ->where(
                    function ($query) use ($userId) {
                        $query
                            ->whereNull('user_id')
                            ->orWhere(
                                'user_id',
                                $userId
                            );
                    }
                )
                ->count();

        return [
            'label' => 'Unread Notifications',
            'value' => (int) $count,
            'hint' => 'Actionable items',
            'url' => $this->routeUrl(
                'admin.crm-notifications.index'
            ),
        ];
    }

    private function incidentsCard(): ?array
    {
        if (
            ! Schema::hasTable(
                'crm_system_incidents'
            )
        ) {
            return null;
        }

        return [
            'label' => 'Open Incidents',
            'value' =>
                (int) DB::table(
                    'crm_system_incidents'
                )
                    ->whereNull(
                        'resolved_at'
                    )
                    ->count(),
            'hint' => 'Production monitoring',
            'url' => $this->routeUrl(
                'admin.system-control.incidents'
            ),
        ];
    }

    private function quickLinks(
        bool $administrator,
        bool $finance
    ): array {
        $links = [
            [
                'label' => 'Notifications',
                'url' => $this->routeUrl(
                    'admin.crm-notifications.index'
                ),
            ],
        ];

        if (
            $administrator
            || $finance
        ) {
            $links[] = [
                'label' => 'Financial Periods',
                'url' => $this->routeUrl(
                    'admin.financial-periods.index'
                ),
            ];
        }

        if ($administrator) {
            $links[] = [
                'label' => 'System Control',
                'url' => $this->routeUrl(
                    'admin.system-control.index'
                ),
            ];
        }

        return array_values(
            array_filter(
                $links,
                fn ($link) =>
                    ! empty(
                        $link['url']
                    )
            )
        );
    }

    private function routeUrl(
        string $name
    ): ?string {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstRouteUrl(
        array $names
    ): ?string {
        foreach ($names as $name) {
            $url =
                $this->routeUrl(
                    $name
                );

            if ($url) {
                return $url;
            }
        }

        return null;
    }
}
