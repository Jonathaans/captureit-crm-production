<?php

namespace Webkul\Admin\Menu;

use Webkul\Core\Menu\MenuItem;

class DashboardMenuItem extends MenuItem
{
    public function isActive(): bool
    {
        // The inventory dashboard URL is also the prefix of inventory pages.
        return request()->routeIs($this->getRoute());
    }
}
