<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\HistoricalDocumentDatabase;
use Tests\TestCase;
use Webkul\Core\Menu;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function () {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.role_acl_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('role_acl_test');
    Schema::clearResolvedInstance('db.schema');
    HistoricalDocumentDatabase::createFinancialSchema();
    (require base_path('packages/Webkul/User/src/Database/Migrations/2021_03_12_074597_create_roles_table.php'))->up();
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedInteger('role_id');
        $table->boolean('status')->default(true);
    });
    $this->administrator = (new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role', (new Role)->forceFill(['permission_type' => 'all']));
    $this->actingAs($this->administrator, 'user');
    $this->withoutMiddleware(CanInstall::class);
    $this->salesRole = Role::create(['name' => 'Sales Admin', 'description' => 'Sales administration', 'permission_type' => 'custom', 'permissions' => ['invoices.view']]);
    DB::table('users')->insert(['id' => 21, 'name' => 'Sales Admin', 'role_id' => $this->salesRole->id, 'status' => true]);
});

afterEach(function () {
    DB::purge('role_acl_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('saves exact checked permissions and applies grants and revocations to the next request and menu', function () {
    $payload = ['name' => 'Sales Admin', 'description' => 'Sales administration', 'permission_type' => 'custom'];
    $url = route('admin.settings.roles.update', $this->salesRole->id);
    foreach ([['invoices', 'invoices.view', 'invoices.edit'], ['invoices.view'], ['products']] as $permissions) {
        $this->actingAs($this->administrator, 'user');
        $this->put($url, $payload + ['permissions' => $permissions])->assertRedirect()->assertSessionHasNoErrors();
        expect($this->salesRole->fresh()->permissions)->toBe($permissions);
        // A new request reloads the user's role from storage, as the session guard does.
        $user = User::findOrFail(21);
        $this->actingAs($user, 'user');
        expect($user->hasPermission('invoices.edit'))->toBe(in_array('invoices.edit', $permissions, true))
            ->and($user->hasPermission('invoices.revise'))->toBe(in_array('invoices.edit', $permissions, true))
            ->and($user->hasPermission('invoices.payment'))->toBeFalse()
            ->and($user->hasPermission('invoices.financial-report.export'))->toBeFalse();
        expect((new Menu)->getItems('admin')->map(fn ($item) => $item->getKey())->values()->all())
            ->toBe(in_array('invoices', $permissions, true) ? ['invoices', 'proformas'] : (in_array('products', $permissions, true) ? ['products'] : []));
    }
});

it('rejects malformed empty and unknown permission submissions without overwriting the role', function (mixed $permissions) {
    $before = $this->salesRole->permissions;
    $this->putJson(route('admin.settings.roles.update', $this->salesRole->id), [
        'name' => 'Sales Admin', 'description' => 'Sales administration', 'permission_type' => 'custom', 'permissions' => $permissions,
    ])->assertUnprocessable();
    expect($this->salesRole->fresh()->permissions)->toBe($before);
})->with([['invoices.edit'], [[]], [['invented.permission']], [['invoices.edit', 'invoices.edit']], [[['invoices.edit']]]]);

it('creates a custom role with the invoice edit permission and clears custom grants for full access', function () {
    $this->postJson(route('admin.settings.roles.store'), [
        'name' => 'Invoice Editor', 'description' => 'Invoice revision', 'permission_type' => 'custom', 'permissions' => ['invoices.edit'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(Role::where('name', 'Invoice Editor')->firstOrFail()->permissions)->toBe(['invoices.edit']);
    $this->put(route('admin.settings.roles.update', $this->salesRole->id), [
        'name' => 'Sales Admin', 'description' => 'All access', 'permission_type' => 'all', 'permissions' => ['invoices.edit'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->salesRole->fresh()->permissions)->toBe([])
        ->and(User::findOrFail(21)->hasPermission('invoices.payment'))->toBeTrue();
});
