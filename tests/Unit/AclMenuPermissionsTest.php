<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route as HttpRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Webkul\Admin\Http\Middleware\Bouncer;
use Webkul\Admin\Services\AclLandingPageService;
use Webkul\Admin\Services\SidebarNavigationService;
use Webkul\Core\Menu;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

function menuAclUser(array $permissions, string $type = 'custom', string $name = 'Superadministrator', int $id = 321): User
{
    $role = (new Role)->forceFill(['name' => $name, 'permission_type' => $type, 'permissions' => $permissions]);
    $user = (new User)->forceFill(['id' => $id, 'status' => 1]);
    $user->setRelation('role', $role);

    return $user;
}

it('groups Proforma under finance using the existing invoice permission', function (): void {
    $this->actingAs(menuAclUser(['invoices']), 'user');
    $finance = app(SidebarNavigationService::class)->getGroups()->firstWhere('key', 'finance');
    expect($finance['label'])->toBe('Keuangan')
        ->and($finance['items']->map(fn ($item) => $item->getKey())->all())->toBe(['invoices', 'proformas'])
        ->and($finance['items']->last()->getUrl())->toBe(route('admin.proformas.index'))
        ->and(bouncer()->hasPermission('invoices.generate'))->toBeFalse();
    $this->actingAs(menuAclUser(['invoices.financial-report']), 'user');
    expect((new Menu)->getAdminMenuByKey('proformas'))->toBeNull();
});

it('shows Financial Report for its checked ACL permission without granting invoice or export access', function (): void {
    $this->actingAs(menuAclUser(['invoices.financial-report']), 'user');

    $items = app(SidebarNavigationService::class)->getItems();
    $report = $items->first(fn ($item) => $item->getKey() === 'financial-report');

    expect($report)->not->toBeNull()
        ->and($report->getUrl())->toBe(route('admin.invoices.financial-report'))
        ->and($items->contains(fn ($item) => $item->getKey() === 'invoices'))->toBeFalse()
        ->and(bouncer()->hasPermission('invoices.financial-report.export'))->toBeFalse()
        ->and(bouncer()->hasPermission('invoices.edit'))->toBeFalse();
});

it('keeps permitted inventory children visible without granting their parent or siblings', function (string $permission, string $route): void {
    $this->actingAs(menuAclUser([$permission]), 'user');

    $items = app(SidebarNavigationService::class)->getItems();
    $inventory = $items->first(fn ($item) => $item->getKey() === 'inventory');

    expect($inventory)->not->toBeNull()
        ->and($inventory->getChildren()->map(fn ($item) => $item->getKey())->values()->all())->toBe([$permission])
        ->and($inventory->getUrl())->toBe(route($route))
        ->and(app(AclLandingPageService::class)->getUrl())->toBe(route($route))
        ->and(bouncer()->hasPermission('inventory'))->toBeFalse()
        ->and(bouncer()->hasPermission($permission.'.create'))->toBeFalse()
        ->and(bouncer()->hasPermission('inventory.assets.edit'))->toBeFalse();
})->with([
    ['inventory.items', 'admin.inventory.items.index'],
    ['inventory.assets', 'admin.inventory.assets.index'],
    ['inventory.consumables', 'admin.inventory.consumables.index'],
    ['inventory.movements', 'admin.inventory.movements.index'],
    ['inventory.maintenance', 'admin.inventory.maintenance.index'],
    ['inventory.stock-opname', 'admin.inventory.stock-opname.index'],
    ['inventory.alerts', 'admin.inventory.alerts.index'],
    ['inventory.qa', 'admin.inventory.qa.index'],
]);

it('keeps nested settings reachable when only the role list permission is checked', function (): void {
    $this->actingAs(menuAclUser(['settings.user.roles']), 'user');
    $settings = (new Menu)->getAdminMenuByKey('settings');

    expect($settings->getUrl())->toBe(route('admin.settings.roles.index'))
        ->and($settings->getChildren()->first()->getChildren()->first()->getKey())->toBe('settings.user.roles')
        ->and(bouncer()->hasPermission('settings.user.roles.edit'))->toBeFalse()
        ->and(bouncer()->hasPermission('settings.user.users'))->toBeFalse();
});

it('refreshes cached menus after permission changes and account switches', function (): void {
    $user = menuAclUser(['invoices.financial-report']);
    $this->actingAs($user, 'user');
    $menu = new Menu;
    expect($menu->getAdminMenuByKey('financial-report'))->not->toBeNull();

    $user->role->permissions = ['products'];
    expect($menu->getAdminMenuByKey('financial-report'))->toBeNull()
        ->and($menu->getAdminMenuByKey('products'))->not->toBeNull();

    $this->actingAs(menuAclUser(['quotes'], id: 322), 'user');
    expect($menu->getItems('admin')->map(fn ($item) => $item->getKey())->values()->all())->toBe(['quotes']);

    auth()->guard('user')->forgetUser();
    expect($menu->getItems('admin'))->toBeEmpty();
});

it('uses granted permissions rather than the role name for menu access', function (string $name): void {
    $this->actingAs(menuAclUser(['products'], name: $name), 'user');

    expect((new Menu)->getItems('admin')->map(fn ($item) => $item->getKey())->values()->all())->toBe(['products'])
        ->and(bouncer()->hasPermission('products.delete'))->toBeFalse();
})->with(['Superadministrator', 'superadministrator', 'Administrator', 'Custom Finance']);

it('consistently allows all permissions and denies users with no role', function (): void {
    $user = menuAclUser([], 'all');
    $this->actingAs($user, 'user');

    expect($user->hasPermission('invoices.financial-report'))->toBeTrue()
        ->and(bouncer()->hasPermission('invoices.financial-report'))->toBeTrue();
    bouncer()->allow('invoices.financial-report');
    expect((new Menu)->getAdminMenuByKey('financial-report'))->not->toBeNull();

    $user->setRelation('role', null);
    expect($user->hasPermission('invoices.financial-report'))->toBeFalse()
        ->and(bouncer()->hasPermission('invoices.financial-report'))->toBeFalse();
});

it('resolves the active permitted menu including shared mail routes', function (array $permissions, string $route, array $params, string $key): void {
    $this->actingAs(menuAclUser($permissions), 'user');
    app()->instance('request', Request::create(route($route, $params)));

    expect((new Menu)->getCurrentActiveMenu('admin')?->getKey())->toBe($key);
})->with([
    [['inventory.assets'], 'admin.inventory.assets.index', [], 'inventory.assets'],
    [['invoices'], 'admin.proformas.index', [], 'proformas'],
    [['invoices', 'invoices.view'], 'admin.proformas.show', [1], 'proformas'],
    [['mail.inbox', 'mail.sent'], 'admin.mail.index', ['route' => 'inbox'], 'mail.inbox'],
]);

it('enforces checked permissions on direct URLs without inheriting unchecked actions', function (array $permissions, string $route, bool $allowed): void {
    $this->actingAs(menuAclUser($permissions), 'user');
    $request = Request::create('/admin/acl-regression');
    app()->instance('request', $request);
    Route::shouldReceive('currentRouteName')->andReturn($route);

    try {
        $result = (new Bouncer)->handle($request, static fn () => 'allowed');
        expect($allowed)->toBeTrue()->and($result)->toBe('allowed');
    } catch (HttpExceptionInterface $exception) {
        expect($allowed)->toBeFalse()->and($exception->getStatusCode())->toBe(401);
    }
})->with([
    [['invoices.financial-report'], 'admin.invoices.financial-report', true],
    [['invoices.financial-report'], 'admin.invoices.financial-report.export', false],
    [['invoices.financial-report.export'], 'admin.invoices.financial-report.export', true],
    [['invoices'], 'admin.invoices.financial-report', false],
    [['invoices'], 'admin.proformas.index', true],
    [['invoices.financial-report'], 'admin.proformas.index', false],
    [['inventory.assets'], 'admin.inventory.assets.index', true],
    [['inventory.assets'], 'admin.inventory.assets.update', false],
    [['inventory.assets'], 'admin.inventory.items.index', false],
    [['settings.user.roles'], 'admin.settings.roles.index', true],
    [['settings.user.roles'], 'admin.settings.roles.update', false],
]);

it('authorizes the requested mail folder instead of the last shared route ACL entry', function (string $folder, bool $allowed): void {
    $this->actingAs(menuAclUser(['mail.inbox']), 'user');
    $menu = (new Menu)->getAdminMenuByKey('mail');
    expect($menu->getUrl())->toBe(route('admin.mail.index', ['route' => 'inbox']))
        ->and($menu->getChildren()->map(fn ($item) => $item->getKey())->values()->all())->toBe(['mail.inbox']);

    $request = Request::create('/admin/mail/'.$folder);
    $route = (new HttpRoute('GET', 'admin/mail/{route?}', static fn () => null))->bind($request);
    $request->setRouteResolver(static fn () => $route);
    app()->instance('request', $request);
    Route::shouldReceive('currentRouteName')->andReturn('admin.mail.index');

    try {
        $result = (new Bouncer)->handle($request, static fn () => 'allowed');
        expect($allowed)->toBeTrue()->and($result)->toBe('allowed');
    } catch (HttpExceptionInterface $exception) {
        expect($allowed)->toBeFalse()->and($exception->getStatusCode())->toBe(401);
    }
})->with([['inbox', true], ['sent', false], ['trash', false], ['', true]]);
