<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(TestCase::class);

beforeEach(function () {
    $this->previousConnection = DB::getDefaultConnection();
    config([
        'database.connections.exception_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ],
        'app.debug' => false,
        'app.key' => str_repeat('a', 32),
    ]);
    DB::setDefaultConnection('exception_test');
    Schema::clearResolvedInstance('db.schema');
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    $this->withoutMiddleware(CanInstall::class);
});

afterEach(function () {
    DB::purge('exception_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('returns the actual error status with the branded HTML error page', function (int $status) {
    Route::middleware('web')->get('_tests/http-error', fn () => throw new HttpException($status, 'Private exception details'));

    $this->get('/_tests/http-error')
        ->assertStatus($status)
        ->assertSee(trans('admin::app.errors.'.$status.'.title'))
        ->assertDontSee('Private exception details');
})->with([401, 403, 404, 500, 503]);

it('preserves HTTP headers for both browser and JSON errors', function (int $status, array $headers) {
    Route::middleware('web')->get('_tests/http-error', fn () => throw new HttpException($status, 'Request rejected', null, $headers));

    foreach ([$this->get('/_tests/http-error'), $this->getJson('/_tests/http-error')] as $response) {
        $response->assertStatus($status);
        foreach ($headers as $name => $value) {
            $response->assertHeader($name, $value);
        }
    }
})->with([
    'unsupported method' => [405, ['Allow' => 'POST']],
    'rate limit' => [429, ['Retry-After' => '60']],
    'maintenance' => [503, ['Retry-After' => '120']],
]);

it('normalizes framework exceptions before choosing the browser or JSON response', function (string $kind, int $status) {
    Route::middleware('web')->get('_tests/framework-error', function () use ($kind) {
        throw match ($kind) {
            'csrf' => new TokenMismatchException('CSRF token mismatch.'),
            'authorization' => new AuthorizationException('This action is unauthorized.'),
            'record' => (new ModelNotFoundException)->setModel('Invoice', [99]),
        };
    });

    $this->get('/_tests/framework-error')->assertStatus($status);
    $this->getJson('/_tests/framework-error')->assertStatus($status)->assertJsonStructure(['message']);
})->with([
    'expired session' => ['csrf', 419],
    'missing permission' => ['authorization', 403],
    'missing record' => ['record', 404],
]);

it('redirects an unauthenticated browser to the admin login and returns JSON 401 to API clients', function () {
    Route::middleware('web')->get('_tests/unauthenticated', fn () => throw new AuthenticationException);

    $this->get('/_tests/unauthenticated')->assertRedirect(route('admin.session.create'));
    $this->getJson('/_tests/unauthenticated')->assertUnauthorized()->assertJsonStructure(['message']);
});

it('retains field errors and old input when validation fails', function () {
    Route::middleware('web')->post('_tests/validation-error', fn () => request()->validate(['amount' => ['required', 'numeric', 'gt:0']]));

    $this->postJson('/_tests/validation-error', ['amount' => -1])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->from('/_tests/form')->post('/_tests/validation-error', ['amount' => -1])
        ->assertRedirect('/_tests/form')->assertSessionHasErrors('amount')->assertSessionHasInput('amount', -1);
});

it('conceals server exception details in production JSON responses', function () {
    Route::middleware('web')->get('_tests/server-error', fn () => throw new RuntimeException('Private database credentials'));

    $this->getJson('/_tests/server-error')->assertStatus(500)
        ->assertJsonStructure(['message'])->assertDontSee('Private database credentials')->assertJsonMissingPath('trace');
});

it('keeps the framework debug renderer available during development', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('_tests/server-error', fn () => throw new RuntimeException('Development exception details'));

    $this->getJson('/_tests/server-error')->assertStatus(500)
        ->assertJsonPath('message', 'Development exception details')->assertJsonStructure(['trace']);
});
