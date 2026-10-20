<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route as HttpRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Webkul\Admin\DataGrids\Invoice\InvoiceDataGrid;
use Webkul\Admin\Http\Middleware\Bouncer;
use Webkul\Core\Acl;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

function aclCoverageUser(array $permissions): User
{
    return (new User)->forceFill(['id' => 21, 'status' => 1])->setRelation(
        'role', (new Role)->forceFill(['name' => 'Sales Admin', 'permission_type' => 'custom', 'permissions' => $permissions])
    );
}

it('covers every registered admin route with ACL or an explicit public or account-scoped policy', function () {
    $public = [
        'admin.session.create', 'admin.session.store', 'admin.session.destroy',
        'admin.forgot_password.create', 'admin.forgot_password.store',
        'admin.reset_password.create', 'admin.reset_password.store',
        'admin.mail.inbound_parse',
        'admin.settings.web_forms.form_js', 'admin.settings.web_forms.preview', 'admin.settings.web_forms.form_store',
    ];
    $mapped = (new Acl)->getRoles(); // Duplicate mappings must fail, not overwrite.
    $known = array_merge($mapped->keys()->all(), array_keys(config('acl_routes.permissions')), config('acl_routes.authenticated'), [
        'admin.settings.attributes.lookup', 'admin.settings.attributes.lookup_entity',
    ]);
    $routes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'admin.'));
    foreach ($routes as $route) {
        if (in_array($route->getName(), $public, true)) {
            continue;
        }
        expect($route->middleware(), $route->getName())->toContain('user');
        $this->assertNotContains('user', $route->excludedMiddleware(), $route->getName());
        expect($known, $route->getName())->toContain($route->getName());
    }
    foreach (array_merge($mapped->keys()->all(), array_keys(config('acl_routes.permissions')), config('acl_routes.authenticated'), array_keys(config('acl_routes.quick_create'))) as $name) {
        expect(Route::has($name), $name)->toBeTrue();
    }
    $keys = array_column(config('acl'), 'key');
    foreach (array_merge(array_values(config('acl_routes.permissions')), array_values(config('acl_routes.lookups')), config('acl_routes.aliases')) as $permissions) {
        foreach ((array) $permissions as $permission) {
            expect($keys, $permission)->toContain($permission);
        }
    }
});

it('allows each granted route permission and denies an unrelated role across the complete ACL catalog', function () {
    $map = (new Acl)->getRoles()->merge(config('acl_routes.permissions'));
    $request = Request::create('/admin/acl-check');
    app()->instance('request', $request);
    $current = '';
    Route::shouldReceive('currentRouteName')->andReturnUsing(function () use (&$current) {
        return $current;
    });
    foreach ($map as $name => $permissions) {
        if ($name === 'admin.mail.index') {
            continue; // Folder-dependent rule is exercised separately.
        }
        $current = $name;
        foreach ((array) $permissions as $permission) {
            $this->actingAs(aclCoverageUser([$permission]), 'user');
            expect((new Bouncer)->handle($request, fn () => 'allowed'), $name.' / '.$permission)->toBe('allowed');
        }
        $this->actingAs(aclCoverageUser([in_array('help', (array) $permissions, true) ? 'dashboard' : 'help']), 'user');
        try {
            (new Bouncer)->handle($request, fn () => 'unexpected');
            $this->fail('Unauthorized route allowed: '.$name);
        } catch (HttpExceptionInterface $e) {
            expect($e->getStatusCode(), $name)->toBe(401);
        }
    }
});

it('does not silently authorize future routes missing an ACL policy', function () {
    $this->actingAs(aclCoverageUser(['invoices.view']), 'user');
    Route::shouldReceive('currentRouteName')->andReturn('admin.unmapped.update');
    expect(fn () => (new Bouncer)->handle(Request::create('/'), fn () => 'unexpected'))->toThrow(HttpException::class);
});

it('resolves permissions freshly and rejects ambiguous route registrations', function () {
    $acl = new Acl;
    config(['acl' => [['key' => 'first', 'route' => 'example.index']]]);
    expect($acl->getRoles()->get('example.index'))->toBe('first');
    config(['acl' => [['key' => 'second', 'route' => 'example.index']]]);
    expect($acl->getRoles()->get('example.index'))->toBe('second');
    config(['acl' => [['key' => 'first', 'route' => 'example.index'], ['key' => 'second', 'route' => 'example.index']]]);
    expect(fn () => $acl->getRoles())->toThrow(LogicException::class);
});

it('maps formerly missing or incorrect actions to the intended module capability', function (string $route, string $permission) {
    expect((new Acl)->getRoles()->get($route))->toBe($permission);
})->with([
    ['admin.invoices.edit', 'invoices.edit'],
    ['admin.invoices.update', 'invoices.edit'],
    ['admin.commercial-revisions.update', 'invoices.edit'],
    ['admin.leads.store', 'leads.create'],
    ['admin.contacts.persons.store', 'contacts.persons.create'],
    ['admin.contacts.organizations.store', 'contacts.organizations.create'],
    ['admin.contacts.persons.identity.update', 'contacts.persons.edit'],
    ['admin.contacts.organizations.identity.update', 'contacts.organizations.edit'],
    ['admin.settings.warehouses.update', 'settings.inventory.warehouse.edit'],
    ['admin.settings.locations.delete', 'settings.inventory.warehouse.edit'],
    ['admin.settings.data_transfer.imports.store', 'settings.automation.data_transfer.imports.create'],
    ['admin.settings.data_transfer.imports.start', 'settings.automation.data_transfer.imports.import'],
    ['admin.settings.attributes.mass_delete', 'settings.automation.attributes.delete'],
    ['admin.leads.stage.update', 'leads.edit'],
    ['admin.products.inventories.store', 'products.edit'],
    ['admin.invoices.financial-report.expenses.export', 'invoices.expense.export-all'],
    ['admin.finance-sales-dashboard.export', 'invoices.financial-report.export'],
    ['admin.my-email.send', 'my-email'],
]);

it('keeps full creation and quick creation independent on every shared endpoint', function (string $module) {
    $permission = $module === 'mail' ? 'mail.compose' : $module.'.create';
    Route::shouldReceive('currentRouteName')->andReturn('admin.'.$module.'.store');
    foreach ([[$permission, false, true], [$permission, true, true], [$permission.'.quick-create', true, true], [$permission.'.quick-create', false, false], ['help', true, false]] as [$granted, $quick, $allowed]) {
        $request = Request::create('/admin/create', 'POST', $quick ? ['quick_add' => $module] : []);
        app()->instance('request', $request);
        $this->actingAs(aclCoverageUser([$granted]), 'user');
        try {
            expect((new Bouncer)->handle($request, fn () => 'allowed'))->toBe('allowed');
            expect($allowed, $granted)->toBeTrue();
        } catch (HttpExceptionInterface $e) {
            expect($allowed, $granted)->toBeFalse()->and($e->getStatusCode())->toBe(401);
        }
    }
})->with(['leads', 'contacts.persons', 'contacts.organizations', 'products', 'mail']);

it('authorizes shared form lookups without granting the contact module or unrelated lookups', function (string $lookup, bool $allowed) {
    $this->actingAs(aclCoverageUser(['invoices.edit']), 'user');
    $request = Request::create('/admin/lookup/'.$lookup);
    $route = (new HttpRoute('GET', 'admin/lookup/{lookup}', fn () => null))->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);
    Route::shouldReceive('currentRouteName')->andReturn('admin.settings.attributes.lookup_entity');
    expect(bouncer()->hasPermission('contacts.persons'))->toBeFalse();
    try {
        expect((new Bouncer)->handle($request, fn () => 'allowed'))->toBe('allowed');
        expect($allowed)->toBeTrue();
    } catch (HttpExceptionInterface $e) {
        expect($allowed)->toBeFalse()->and($e->getStatusCode())->toBe(401);
    }
})->with([['persons', true], ['organizations', true], ['users', true], ['warehouses', false], ['unknown', false]]);

it('enforces ACL on provider registered endpoints before their controllers run', function (string $method, string $name, array $parameters) {
    $this->withoutMiddleware(CanInstall::class);
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    $this->actingAs(aclCoverageUser(['help']), 'user');
    $this->json($method, route($name, $parameters))->assertUnauthorized();
})->with([
    ['GET', 'admin.finance-sales-dashboard.index', []],
    ['GET', 'admin.finance-sales-dashboard.export', []],
    ['POST', 'admin.financial-periods.store', []],
    ['GET', 'admin.my-email.inbox', []],
    ['POST', 'admin.my-email.send', []],
    ['GET', 'admin.work-orders.edit', [999]],
    ['POST', 'admin.operations-dashboard.backups.store', []],
    ['GET', 'admin.system-control.email-accounts', []],
]);

it('shows only granted actions in the invoice grid', function (array $permissions, array $actions) {
    $this->actingAs(aclCoverageUser($permissions), 'user');
    $grid = app(InvoiceDataGrid::class);
    $grid->prepareActions();
    expect(array_map(fn ($action) => $action->index, $grid->getActions()))->toBe($actions);
})->with([
    [['invoices'], []],
    [['invoices.view'], ['view']],
    [['invoices.edit'], ['edit']],
    [['invoices.revise'], ['edit']],
    [['invoices.print'], ['print']],
    [['invoices.view', 'invoices.edit', 'invoices.print'], ['view', 'edit', 'print']],
]);
