<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Settings\AttributeController;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

it('allows only registered attribute files belonging to a permitted module', function (string $permission, string $path, int $expectedStatus) {
    Storage::fake();
    Storage::put('products/1/manual.pdf', 'product manual');
    Storage::put('private/backup.zip', 'private backup');
    $this->actingAs((new User)->forceFill(['id' => 1, 'status' => 1])->setRelation('role',
        (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => [$permission]])
    ), 'user');
    $request = Request::create('/admin/settings/attributes/download', 'GET', ['path' => $path]);
    app()->instance('request', $request);
    $value = (new AttributeValue)->forceFill(['entity_type' => 'products', 'entity_id' => 1, 'text_value' => 'products/1/manual.pdf'])
        ->setRelation('attribute', (new Attribute)->forceFill(['type' => 'file']));
    $repository = Mockery::mock(AttributeValueRepository::class);
    $repository->shouldReceive('findOneWhere')->once()->with(['text_value' => $path])->andReturn($path === 'products/1/manual.pdf' ? $value : null);
    $controller = new AttributeController(Mockery::mock(AttributeRepository::class), $repository);
    try {
        $response = $controller->download();
        expect($response->getStatusCode())->toBe($expectedStatus);
        ob_start();
        $response->sendContent();
        expect(ob_get_clean())->toBe('product manual');
    } catch (HttpExceptionInterface $e) {
        expect($e->getStatusCode())->toBe($expectedStatus);
    }
})->with([
    ['products.view', 'products/1/manual.pdf', 200],
    ['products.edit', 'products/1/manual.pdf', 200],
    ['invoices.edit', 'products/1/manual.pdf', 403],
    ['products.view', 'private/backup.zip', 404],
    ['products.view', '../.env', 404],
]);
