<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Webkul\Admin\Http\Middleware\Bouncer;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

it('honors Product Create and Quick Add independently for non-administrator roles', function (array $permissions, string $route, bool $quickAdd, bool $allowed): void {
    $role = (new Role)->forceFill(['name' => 'Sales Admin', 'permission_type' => 'custom', 'permissions' => $permissions]);
    $user = (new User)->forceFill(['id' => 321, 'name' => 'Sales Tester', 'status' => 1]);
    $user->setRelation('role', $role);
    $this->actingAs($user, 'user');

    $request = Request::create('/admin/products/create', $route === 'admin.products.create' ? 'GET' : 'POST', $quickAdd ? ['quick_add' => 1] : []);
    app()->instance('request', $request);
    Route::shouldReceive('currentRouteName')->andReturn($route);

    try {
        $result = (new Bouncer)->handle($request, static fn () => 'allowed');
        expect($allowed)->toBeTrue()->and($result)->toBe('allowed');
    } catch (HttpExceptionInterface $exception) {
        expect($allowed)->toBeFalse()->and($exception->getStatusCode())->toBe(401);
    }
})->with([
    'Create opens full form' => [['products.create'], 'admin.products.create', false, true],
    'Create saves full form' => [['products.create'], 'admin.products.store', false, true],
    'Create also permits quick creation' => [['products.create'], 'admin.products.store', true, true],
    'Quick Add saves quick form' => [['products.create.quick-create'], 'admin.products.store', true, true],
    'Quick Add does not open full form' => [['products.create.quick-create'], 'admin.products.create', false, false],
    'Quick Add does not save full form' => [['products.create.quick-create'], 'admin.products.store', false, false],
    'View only cannot create' => [['products.view'], 'admin.products.store', false, false],
    'View only cannot forge quick creation' => [['products.view'], 'admin.products.store', true, false],
    'Edit only cannot create' => [['products.edit'], 'admin.products.store', false, false],
]);

it('keeps administrator access to Product Create', function (): void {
    $role = (new Role)->forceFill(['permission_type' => 'all', 'permissions' => []]);
    $user = (new User)->forceFill(['id' => 1, 'status' => 1]);
    $user->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $request = Request::create('/admin/products/create', 'POST');

    expect((new Bouncer)->handle($request, static fn () => 'allowed'))->toBe('allowed');
});
