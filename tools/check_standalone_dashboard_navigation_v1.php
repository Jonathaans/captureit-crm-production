<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use Webkul\Admin\Services\SidebarNavigationService;
use Webkul\Core\Menu;
use Webkul\Core\Menu\MenuItem;

// Isolated navigation checks: no application boot, database, HTTP, or writes.
$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Install Composer dependencies before running this check.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';
require_once $root.'/packages/Webkul/Core/src/Http/helpers.php';
require_once $root.'/packages/Webkul/Admin/src/Http/helpers.php';

$container = new Container;
Container::setInstance($container);

$routes = new RouteCollection;

foreach ([
    'admin.dashboard.index' => 'admin/dashboard',
    'admin.leads.index' => 'admin/leads',
    'admin.invoices.index' => 'admin/invoices',
    'admin.inventory.dashboard' => 'admin/inventory',
    'admin.inventory.items.index' => 'admin/inventory/items',
    'admin.inventory.assets.index' => 'admin/inventory/assets',
    'admin.finance-sales-dashboard.index' => 'admin/finance-sales-dashboard',
] as $name => $uri) {
    $route = new Route('GET', $uri, []);
    $route->name($name);
    $routes->add($route);
}

$setRequest = static function (string $routeName, string $query = '') use ($container, $routes): void {
    $route = $routes->getByName($routeName);
    $request = Request::create('https://crm.example.test/'.$route->uri().$query);
    $request->setRouteResolver(fn () => $route);
    $container->instance('request', $request);
    $container->instance('url', new UrlGenerator($routes, $request));
};

$setRequest('admin.dashboard.index');

$makeItem = static fn (string $key, string $route, int $sort, array $children = []) => new MenuItem(
    key: $key,
    name: $key,
    route: $route,
    url: route($route),
    sort: $sort,
    icon: 'icon-dashboard',
    info: '',
    children: collect($children),
);

$setContext = static function (Collection $items, array $permissions) use ($container): void {
    $container->instance('menu', new class($items) extends Menu
    {
        public function __construct(private Collection $sourceItems) {}

        public function getItems(?string $area = null, string $key = ''): Collection
        {
            return $this->sourceItems;
        }
    });

    $container->instance('bouncer', new class($permissions)
    {
        public function __construct(private array $permissions) {}

        public function hasPermission(string $permission): bool
        {
            return in_array('*', $this->permissions, true)
                || in_array($permission, $this->permissions, true);
        }
    });
};

$checks = 0;
$check = static function (bool $passed, string $description) use (&$checks): void {
    if (! $passed) {
        throw new RuntimeException($description);
    }

    $checks++;
    fwrite(STDOUT, 'PASS '.$description.PHP_EOL);
};

$keys = static fn (Collection $items): array => $items->map(fn (MenuItem $item) => $item->getKey())->all();
$byKey = static fn (Collection $items, string $key): ?MenuItem => $items->first(fn (MenuItem $item) => $item->getKey() === $key);
$activeKeys = static fn (Collection $items): array => $items->filter(fn (MenuItem $item) => $item->isActive())->map(fn (MenuItem $item) => $item->getKey())->values()->all();

try {
    $inventoryChildren = [
        $makeItem('inventory.dashboard', 'admin.inventory.dashboard', 1),
        $makeItem('inventory.items', 'admin.inventory.items.index', 2),
        $makeItem('inventory.assets', 'admin.inventory.assets.index', 3),
    ];
    $sourceItems = collect([
        $makeItem('dashboard', 'admin.dashboard.index', 10),
        $makeItem('leads', 'admin.leads.index', 20),
        $makeItem('invoices', 'admin.invoices.index', 40),
        $makeItem('inventory', 'admin.inventory.dashboard', 70, $inventoryChildren),
    ]);

    $setContext($sourceItems, ['*']);
    $service = new SidebarNavigationService;
    $items = $service->getItems();
    $check(array_slice($keys($items), 0, 4) === ['dashboard', 'inventory-dashboard', 'sales-dashboard', 'leads'], 'Dashboards are separate entries immediately after Dashboard');
    $check($byKey($items, 'inventory-dashboard')->getName() === 'Dashboard Inventory', 'Inventory label');
    $check($byKey($items, 'sales-dashboard')->getName() === 'Dashboard Sales', 'Sales label');
    $check(! $byKey($items, 'inventory-dashboard')->haveChildren() && ! $byKey($items, 'sales-dashboard')->haveChildren(), 'Dashboard entries link directly to their pages');
    $check($keys($byKey($items, 'inventory')->getChildren()) === ['inventory.items', 'inventory.assets'], 'Inventory submenu contains working inventory pages only');
    $check($byKey($items, 'inventory')->getRoute() === 'admin.inventory.items.index', 'Inventory parent targets its first permitted child');
    $check(count($byKey($sourceItems, 'inventory')->getChildren()) === 3, 'Cached source menu is not mutated');
    $check($keys($service->getItems()) === $keys($items), 'Rendering desktop and mobile does not duplicate dashboards');

    $setRequest('admin.inventory.dashboard', '?period=month');
    $check($activeKeys($items) === ['inventory-dashboard'], 'Inventory dashboard alone is active, including query parameters');
    $setRequest('admin.inventory.items.index');
    $check($activeKeys($items) === ['inventory'], 'Inventory Items does not activate Dashboard Inventory');
    $setRequest('admin.inventory.assets.index');
    $check($activeKeys($items) === ['inventory'], 'Inventory Assets does not activate Dashboard Inventory');
    $setRequest('admin.finance-sales-dashboard.index', '?focus=overdue');
    $check($activeKeys($items) === ['sales-dashboard'], 'Sales dashboard alone is active');
    $setRequest('admin.invoices.index');
    $check($activeKeys($items) === ['invoices'], 'Invoices does not activate Dashboard Sales');

    $setContext(collect(), ['inventory.dashboard']);
    $check($keys($service->getItems()) === ['inventory-dashboard'], 'Dashboard-only inventory role works without an Inventory parent');

    foreach (['invoices', 'invoices.view', 'invoices.financial-report'] as $permission) {
        $setContext(collect(), [$permission]);
        $check($keys($service->getItems()) === ['sales-dashboard'], 'Sales retains access through '.$permission);
    }

    $setContext(collect(), []);
    $check($service->getItems()->isEmpty(), 'No dashboard is shown without the existing permissions');

    $setContext(collect([
        $makeItem('inventory', 'admin.inventory.items.index', 70, [
            $makeItem('inventory.assets', 'admin.inventory.assets.index', 3),
        ]),
    ]), ['inventory', 'inventory.assets']);
    $restricted = $service->getItems();
    $check($keys($restricted) === ['inventory'], 'Inventory access alone does not grant dashboard access');
    $check($byKey($restricted, 'inventory')->getRoute() === 'admin.inventory.assets.index', 'Restricted Inventory parent uses the allowed Assets page');

    $setContext(collect([
        $makeItem('inventory', 'admin.inventory.items.index', 70),
    ]), ['inventory.dashboard']);
    $check($keys($service->getItems()) === ['inventory-dashboard'], 'No empty Inventory container remains for a dashboard-only role');

    $config = collect(require $root.'/packages/Webkul/Admin/src/Config/menu.php');
    $check(! $config->contains('key', 'inventory.dashboard'), 'Nested inventory dashboard is removed from menu config');
    $check($config->firstWhere('key', 'inventory')['route'] === 'admin.inventory.items.index', 'Inventory config no longer points at the dashboard');

    foreach (['desktop', 'mobile'] as $layout) {
        $view = file_get_contents($root.'/packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/'.$layout.'/index.blade.php');
        $check(str_contains($view, 'SidebarNavigationService::class'), ucfirst($layout).' sidebar uses the shared navigation service');
    }

    $invoiceView = file_get_contents($root.'/packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php');
    $check(! str_contains($invoiceView, "route('admin.finance-sales-dashboard.index')"), 'Sales entry is removed from the Invoices header');
    $check(str_contains($invoiceView, "route('admin.invoices.billing.create')"), 'Generate from Quote remains available');

    fwrite(STDOUT, $checks." navigation checks passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL '.$exception->getMessage().PHP_EOL);
    exit(1);
}
