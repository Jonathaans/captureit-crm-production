<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Webkul\Admin\Menu\DashboardMenuItem;
use Webkul\Core\Menu\MenuItem;

class SidebarNavigationService
{
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
