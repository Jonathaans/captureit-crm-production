<?php

namespace Webkul\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Webkul\Core\Acl\AclItem;

class Acl
{
    /**
     * acl items.
     */
    protected array $items = [];

    /**
     * Add a new acl item.
     */
    public function addItem(AclItem $aclItem): void
    {
        $this->items[] = $aclItem;
    }

    /**
     * Get all acl items.
     */
    public function getItems(): Collection
    {
        if (! $this->items) {
            $this->prepareAclItems();
        }

        return collect($this->items)
            ->sortBy('sort')
            ->values();
    }

    /**
     * Acl Config.
     */
    private function getAclConfig(): array
    {
        return config('acl', []);
    }

    /**
     * Get all roles.
     */
    public function getRoles(): Collection
    {
        $roles = collect();

        foreach ($this->getAclConfig() as $role) {
            foreach ((array) $role['route'] as $route) {
                if ($roles->has($route)) {
                    throw new \LogicException("Duplicate ACL route: {$route}");
                }

                $roles->put($route, $role['key']);
            }
        }

        return $roles;
    }

    /**
     * Prepare acl items.
     */
    private function prepareAclItems(): void
    {
        $aclWithDotNotation = [];

        foreach ($this->getAclConfig() as $item) {
            $aclWithDotNotation[$item['key']] = $item;
        }

        $acl = Arr::undot(Arr::dot($aclWithDotNotation));

        foreach ($acl as $aclItemKey => $aclItem) {
            $subAclItems = $this->processSubAclItems($aclItem);

            $this->addItem(new AclItem(
                key: $aclItemKey,
                name: trans($aclItem['name']),
                route: $aclItem['route'],
                sort: $aclItem['sort'],
                children: $subAclItems,
            ));
        }
    }

    /**
     * Process sub acl items.
     */
    private function processSubAclItems($aclItem): Collection
    {
        return collect($aclItem)
            ->sortBy('sort')
            ->filter(fn ($value, $key) => is_array($value) && $key !== 'route')
            ->map(function ($subAclItem) {
                $subSubAclItems = $this->processSubAclItems($subAclItem);

                return new AclItem(
                    key: $subAclItem['key'],
                    name: trans($subAclItem['name']),
                    route: $subAclItem['route'],
                    sort: $subAclItem['sort'],
                    children: $subSubAclItems,
                );
            });
    }
}
