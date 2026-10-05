<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Webkul\Admin\Menu\DashboardMenuItem;
use Webkul\Core\Menu\MenuItem;

class SidebarNavigationService
{
    /**
     * Group permitted entries for presentation; keep login landing order intact.
     */
    public function getGroups(): Collection
    {
        $items = $this->getItems();
        $definitions = [
            ['key' => 'overview', 'label' => 'Ringkasan', 'items' => ['dashboard', 'inventory-dashboard', 'sales-dashboard', 'operations-dashboard']],
            ['key' => 'sales', 'label' => 'Penjualan', 'items' => ['leads', 'quotes', 'activities']],
            ['key' => 'finance', 'label' => 'Keuangan', 'items' => ['invoices', 'purchase-orders', 'financial-report']],
            ['key' => 'operations', 'label' => 'Operasional', 'items' => ['work-orders', 'delivery-orders', 'inventory']],
            ['key' => 'master', 'label' => 'Data Master', 'items' => ['contacts', 'products']],
            ['key' => 'communication', 'label' => 'Komunikasi', 'items' => ['my-email', 'mail']],
            ['key' => 'system', 'label' => 'Sistem', 'items' => ['settings', 'configuration', 'internal-chat-audit', 'help']],
        ];

        $groups = collect($definitions)->map(fn (array $group): array => [
            'key' => $group['key'],
            'label' => $group['label'],
            'items' => $items->filter(fn (MenuItem $item) => in_array($item->getKey(), $group['items'], true))->values(),
        ]);

        // Keep permitted extension menus reachable without changing their ACL.
        $knownKeys = collect($definitions)->pluck('items')->flatten()->all();
        $groups->push([
            'key' => 'other',
            'label' => 'Lainnya',
            'items' => $items->reject(fn (MenuItem $item) => in_array($item->getKey(), $knownKeys, true))->values(),
        ]);

        return $groups->filter(fn (array $group) => $group['items']->isNotEmpty())->values();
    }

    public function getItems(): Collection
    {
        $items = menu()->getItems('admin')
            ->map(function (MenuItem $item): ?MenuItem {
                // Desktop and mobile share the cached core menu. Leave it intact.
                $item = clone $item;

                if ($item->getKey() !== 'inventory') {
                    return $item;
                }

                $children = $item->getChildren()
                    ->reject(fn (MenuItem $child) => $child->getKey() === 'inventory.dashboard')
                    ->values();

                if ($children->isEmpty()) {
                    return null;
                }

                // Use the first permitted child, including for restricted roles.
                $firstChild = $children->first();

                return $item->setChildren($children)
                    ->setRoute($firstChild->getRoute())
                    ->setUrl($firstChild->getUrl());
            })
            ->filter();

        $dashboards = [
            [
                'key' => 'inventory-dashboard',
                'name' => 'Dashboard Inventory',
                'route' => 'admin.inventory.dashboard',
                'sort' => 11,
                'permissions' => ['inventory.dashboard'],
            ],
            [
                'key' => 'sales-dashboard',
                'name' => 'Dashboard Sales',
                'route' => 'admin.finance-sales-dashboard.index',
                'sort' => 12,
                // Keep the same alternatives as authorizeDashboard().
                'permissions' => ['invoices', 'invoices.view', 'invoices.financial-report'],
            ],
        ];

        foreach ($dashboards as $dashboard) {
            if (! collect($dashboard['permissions'])->contains(
                fn (string $permission) => bouncer()->hasPermission($permission)
            )) {
                continue;
            }

            $items->push(new DashboardMenuItem(
                key: $dashboard['key'],
                name: $dashboard['name'],
                route: $dashboard['route'],
                url: route($dashboard['route']),
                sort: $dashboard['sort'],
                icon: 'icon-dashboard',
                info: '',
                children: collect(),
            ));
        }

        return $items->sortBy(fn (MenuItem $item) => $item->getPosition())->values();
    }
}
