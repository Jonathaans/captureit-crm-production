<?php

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Admin\Support\OptionalSalesAddress;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

uses(TestCase::class);

it('accepts empty or partial addresses and rejects malformed values', function (): void {
    $rules = OptionalSalesAddress::rules('billing_address');
    foreach ([[], ['billing_address' => null], ['billing_address' => []], ['billing_address' => ['city' => 'Jakarta']], ['billing_address' => ['address' => '', 'country' => null]]] as $input) {
        expect(Validator::make($input, $rules)->passes())->toBeTrue();
    }

    expect(Validator::make(['billing_address' => 'invalid'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['billing_address' => ['city' => ['invalid']]], $rules)->fails())->toBeTrue()
        ->and(OptionalSalesAddress::appliesTo('persons'))->toBeFalse();
});

it('overrides required address metadata in Quote and Lead requests only', function (): void {
    $attributes = Mockery::mock(AttributeRepository::class);
    $values = Mockery::mock(AttributeValueRepository::class);
    $attributes->shouldReceive('scopeQuery')->andReturnSelf();
    $attributes->shouldReceive('get')->andReturnUsing(fn () => collect([
        (object) ['code' => 'billing_address', 'entity_type' => 'quotes', 'type' => 'address', 'is_required' => true, 'is_unique' => false],
    ]));
    $rules = (new AttributeForm($attributes, $values))->rules();
    expect(Validator::make(['billing_address' => []], $rules)->passes())->toBeTrue();

    $leadAttributes = Mockery::mock(AttributeRepository::class);
    $leadAttributes->shouldReceive('scopeQuery')->andReturnSelf();
    $leadAttributes->shouldReceive('get')->andReturn(
        collect([(object) ['code' => 'address', 'entity_type' => 'leads', 'type' => 'address', 'is_required' => true, 'is_unique' => false]]),
        collect([(object) ['code' => 'address', 'entity_type' => 'persons', 'type' => 'address', 'is_required' => true, 'is_unique' => false]])
    );
    $rules = (new LeadForm($leadAttributes, $values))->rules();
    expect(Validator::make(['address' => [], 'person' => ['address' => [], 'organization_address' => []]], $rules)->passes())->toBeTrue();
});

it('keeps required addresses required outside sales documents', function (): void {
    $attributes = Mockery::mock(AttributeRepository::class);
    $attributes->shouldReceive('scopeQuery')->andReturnSelf();
    $attributes->shouldReceive('get')->andReturn(collect([
        (object) ['code' => 'address', 'entity_type' => 'organizations', 'type' => 'address', 'is_required' => true, 'is_unique' => false],
    ]));
    $rules = (new AttributeForm($attributes, Mockery::mock(AttributeValueRepository::class)))->rules();
    expect(Validator::make(['address' => []], $rules)->fails())->toBeTrue();
});

it('renders partial address labels without requiring missing fields', function (): void {
    $repository = (new ReflectionClass(AttributeValueRepository::class))->newInstanceWithoutConstructor();
    $attribute = (object) ['type' => 'address'];
    expect($repository->getAttributeLabel(['city' => 'Jakarta'], $attribute))->toBe('Jakarta<br>')
        ->and($repository->getAttributeLabel([], $attribute))->toBeNull()
        ->and($repository->getAttributeLabel(null, $attribute))->toBeNull();
});

it('accepts long multiline address text in contact and quote requests', function (string $entityType, string $key): void {
    $attributes = Mockery::mock(AttributeRepository::class);
    $attributes->shouldReceive('scopeQuery')->andReturnSelf();
    $attributes->shouldReceive('get')->andReturn(collect([
        (object) ['code' => $key, 'entity_type' => $entityType, 'type' => 'address', 'is_required' => true, 'is_unique' => false],
    ]));
    $rules = (new AttributeForm($attributes, Mockery::mock(AttributeValueRepository::class)))->rules();
    $address = [
        'address' => str_repeat("Jalan Danau Tondano Blok AA, Gedung Utama.\nLantai 15, Jakarta Pusat.\n", 100),
        'country' => 'ID', 'state' => 'JK', 'city' => 'Jakarta', 'postcode' => '10210',
    ];
    expect(Validator::make([$key => $address], $rules)->passes())->toBeTrue();
})->with([['organizations', 'address'], ['persons', 'address'], ['quotes', 'billing_address'], ['quotes', 'shipping_address']]);

it('accepts long company addresses in leads while still rejecting non-text address values', function (): void {
    $attributes = Mockery::mock(AttributeRepository::class);
    $attributes->shouldReceive('scopeQuery')->andReturnSelf();
    $attributes->shouldReceive('get')->andReturn(collect());
    $rules = (new LeadForm($attributes, Mockery::mock(AttributeValueRepository::class)))->rules();
    $input = ['person' => ['organization_address' => ['address' => str_repeat('Alamat lengkap perusahaan. ', 400)]]];
    expect(Validator::make($input, $rules)->passes())->toBeTrue();
    $input['person']['organization_address']['address'] = ['invalid'];
    expect(Validator::make($input, $rules)->fails())->toBeTrue();
});
