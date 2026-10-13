<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;
use Webkul\Admin\Http\Middleware\Bouncer;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

it('restores the browser session before authorizing every billing endpoint', function (string $name): void {
    $route = app('router')->getRoutes()->getByName($name);
    $middleware = app('router')->gatherRouteMiddleware($route);

    expect($middleware)->toContain(EncryptCookies::class, StartSession::class, ValidateCsrfToken::class, Bouncer::class)
        ->and(array_search(StartSession::class, $middleware, true))->toBeLessThan(array_search(Bouncer::class, $middleware, true));
})->with(['admin.invoices.billing.create', 'admin.invoices.billing.summary', 'admin.invoices.billing.store']);

it('opens proforma creation for the saved quote without submitting the quote form', function (string $permissionType, array $permissions, bool $visible): void {
    $role = (new Role)->forceFill(['permission_type' => $permissionType, 'permissions' => $permissions]);
    $user = (new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role', $role);
    $this->actingAs($user, 'user');
    $quote = (new Quote)->forceFill(['id' => 68, 'event_date' => '2026-10-05']);

    // Render the actual action independently of the unrelated quote editor fields.
    $view = file_get_contents(base_path('packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php'));
    expect(preg_match('/@if \(bouncer\(\)->hasPermission\(\'invoices.generate\'\)\).*?@endif/s', $view, $matches))->toBe(1);
    $html = Blade::render($matches[0], compact('quote'), true);

    if (! $visible) {
        expect($html)->not->toContain('Buat Proforma');

        return;
    }

    $document = new DOMDocument;
    $document->loadHTML($html);
    $links = (new DOMXPath($document))->query('//a[contains(normalize-space(.), "Buat Proforma")]');
    expect($links->length)->toBe(1);
    $url = $links->item(0)->getAttribute('href');
    $request = Request::create($url, 'GET');

    expect($request->integer('quote_id'))->toBe(68)
        ->and(app('router')->getRoutes()->match($request)->getName())->toBe('admin.invoices.billing.create')
        ->and($html)->not->toContain('type="submit"');
})->with([
    'Administrator' => ['all', [], true],
    'Sales with generate permission' => ['custom', ['invoices.generate', 'invoices.view'], true],
    'Invoice viewer' => ['custom', ['invoices', 'invoices.view'], false],
    'Quote editor only' => ['custom', ['quotes.edit'], false],
]);
