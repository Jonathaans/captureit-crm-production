<?php

namespace Webkul\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Webkul\Core\Menu\MenuItem;

class Menu
{
    /**
     * Menu items.
     */
    private array $items = [];

    /**
     * Config menu.
     */
    private array $configMenu = [];

    /**
     * Contains current item key.
     */
    private string $currentKey = '';

    private ?string $cacheKey = null;

    private ?Collection $resolvedItems = null;

    /**
     * Menu area for admin.
     */
    const ADMIN = 'admin';

    /**
     * Menu area for customer.
     */
    const CUSTOMER = 'customer';

    /**
     * Add a new menu item.
     */
    public function addItem(MenuItem $menuItem): void
    {
        $this->items[] = $menuItem;
    }

    /**
     * Get all menu items.
     */
    public function getItems(?string $area = null, string $key = ''): Collection
    {
        if (! $area) {
            throw new \Exception('Area must be provided to get menu items.');
        }

        $user = auth()->guard('user')->user();
        $cacheKey = json_encode([
            $area,
            config("menu.$area"),
            app()->getLocale(),
            request()->fullUrl(),
            $user?->getAuthIdentifier(),
            $user?->role?->permission_type,
            $user?->role?->permissions,
        ]);

        if ($this->cacheKey === $cacheKey && $this->resolvedItems !== null) {
            return $this->resolvedItems;
        }

        $this->items = [];
        $this->currentKey = '';

        $this->configMenu = collect(config("menu.$area"))->map(function ($item) use ($area) {
            $permission = $item['permission'] ?? $item['key'];
            $routePermission = acl()->getRoles()->get($item['route'], $permission);

            // Mail folders share a route name, but have separate ACL entries.
            if ($item['route'] === 'admin.mail.index') {
                $routePermission = 'mail.'.($item['params']['route'] ?? 'inbox');
            }

            return Arr::except([
                ...$item,
                'url' => route($item['route'], $item['params'] ?? []),
                'allowed' => $area !== self::ADMIN || (
                    bouncer()->hasPermission($permission)
                    && bouncer()->hasPermission($routePermission)
                ),
            ], ['params']);
        })->toArray();

        $this->prepareMenuItems();
        $this->cacheKey = $cacheKey;

        return $this->resolvedItems = collect($this->items)
            ->sortBy(fn ($item) => $item->getPosition());
    }

    /**
     * Get admin menu by key or keys.
     */
    public function getAdminMenuByKey(array|string $keys): mixed
    {
        $items = $this->getItems('admin');

        $keysArray = (array) $keys;

        $filteredItems = $items->filter(fn ($item) => in_array($item->getKey(), $keysArray));

        return is_array($keys) ? $filteredItems : $filteredItems->first();
    }

    /**
     * Prepare menu items.
     */
    private function prepareMenuItems(): void
    {
        $menuWithDotNotation = [];
        $matchedUrlLength = 0;

        foreach ($this->configMenu as $item) {
            if (
                $item['allowed']
                && request()->fullUrlIs($item['url'].'*')
                && strlen($item['url']) >= $matchedUrlLength
            ) {
                $this->currentKey = $item['key'];
                $matchedUrlLength = strlen($item['url']);
            }

            $menuWithDotNotation[$item['key']] = $item;
        }

        $menu = Arr::undot(Arr::dot($menuWithDotNotation));

        foreach ($menu as $menuItem) {
            if ($item = $this->buildMenuItem($menuItem)) {
                $this->addItem($item);
            }
        }
    }

    /**
     * Process sub menu items.
     */
    private function processSubMenuItems($menuItem): Collection
    {
        return collect($menuItem)
            ->filter(fn ($value) => is_array($value))
            ->sortBy('sort')
            ->map(fn ($subMenuItem) => $this->buildMenuItem($subMenuItem))
            ->filter();
    }

    private function buildMenuItem(array $menuItem): ?MenuItem
    {
        $children = $this->processSubMenuItems($menuItem);

        if (! $menuItem['allowed'] && $children->isEmpty()) {
            return null;
        }

        // Keep ancestors as navigation containers only. Never grant their ACL
        // permissions or send users to a parent's unauthorized default page.
        $firstChild = $menuItem['allowed'] ? null : $children->first();

        return new MenuItem(
            key: $menuItem['key'],
            name: trans($menuItem['name']),
            route: $firstChild?->getRoute() ?? $menuItem['route'],
            url: $firstChild?->getUrl() ?? $menuItem['url'],
            sort: $menuItem['sort'],
            icon: $menuItem['icon-class'],
            info: trans($menuItem['info'] ?? ''),
            children: $children,
        );
    }

    /**
     * Get current active menu.
     */
    public function getCurrentActiveMenu(?string $area = null): ?MenuItem
    {
        $items = $this->getItems($area);
        $currentKey = implode('.', array_slice(explode('.', $this->currentKey), 0, 2));

        return $this->findMatchingItem($items, $currentKey);
    }

    /**
     * Finding the matching item.
     */
    private function findMatchingItem($items, $currentKey): ?MenuItem
    {
        foreach ($items as $item) {
            if ($item->getKey() == $currentKey) {
                return $item;
            }

            if ($item->haveChildren()) {
                $matchingChild = $this->findMatchingItem($item->getChildren(), $currentKey);

                if ($matchingChild) {
                    return $matchingChild;
                }
            }
        }

        return null;
    }
}
