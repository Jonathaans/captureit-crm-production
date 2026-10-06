<?php

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

it('renders the Product Save action using product permissions rather than user-group permissions', function (string $permissionType, array $permissions, bool $visible): void {
    $role = (new Role)->forceFill([
        'name' => 'Sales Admin',
        'permission_type' => $permissionType,
        'permissions' => $permissions,
    ]);
    $user = (new User)->forceFill(['id' => 321, 'name' => 'Sales Tester', 'status' => 1]);
    $user->setRelation('role', $role);
    $this->actingAs($user, 'user');

    // Render the actual action block, without requiring unrelated layout or
    // attribute queries. This catches a wrong permission even if routes work.
    $view = file_get_contents(base_path('packages/Webkul/Admin/src/Resources/views/products/create.blade.php'));
    expect(preg_match('/<!-- Create button for Product -->.*?@endif/s', $view, $matches))->toBe(1);
    $html = Blade::render($matches[0], [], true);

    if ($visible) {
        expect($html)->toContain('type="submit"')
            ->and($html)->toContain(trans('admin::app.products.create.save-btn'));
    } else {
        expect($html)->not->toContain('<button');
    }
})->with([
    'Product Create without user-group access' => ['custom', ['products.create'], true],
    'User-group Create without product access' => ['custom', ['settings.user.groups.create'], false],
    'View only' => ['custom', ['products.view'], false],
    'Edit only' => ['custom', ['products.edit'], false],
    'Quick Add only' => ['custom', ['products.create.quick-create'], false],
    'Administrator with all permissions' => ['all', [], true],
]);
