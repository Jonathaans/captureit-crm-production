<?php

declare(strict_types=1);

use Tests\TestCase;
use Webkul\Contact\Repositories\PersonRepository;

uses(TestCase::class);

it('submits a newly typed organization name from the lead form', function (): void {
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/leads/common/contact.blade.php')
    );

    expect($view)
        ->toContain('can-add-new="true"')
        ->toContain('@lookup-added="setOrganizationName"')
        ->toContain('@lookup-removed="setOrganizationName"')
        ->toContain('name="person[organization_name]"')
        ->toContain("(organization?.name || '').trim()");
});

it('keeps email optional only in the lead contact form', function (): void {
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/leads/common/contact.blade.php')
    );
    $request = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Requests/LeadForm.php')
    );

    $emailStart = strpos($view, '<!-- Person Email -->');
    $emailEnd = strpos($view, '<!-- Person Contact Numbers -->', $emailStart);
    $emailBlock = substr($view, $emailStart, $emailEnd - $emailStart);

    expect($emailBlock)
        ->not->toContain('class="required"')
        ->not->toContain('validations="required"');

    expect($request)
        ->toContain("if (\$attribute->code === 'person.emails')")
        ->toContain('$isRequired = false;')
        ->toContain("'person.organization_name' => ['nullable', 'string', 'max:255']");
});

it('normalizes blank contact channels without creating an empty unique key', function (): void {
    $repository = (new ReflectionClass(PersonRepository::class))
        ->newInstanceWithoutConstructor();
    $sanitize = new ReflectionMethod(PersonRepository::class, 'sanitizeRequestedPersonData');
    $sanitize->setAccessible(true);

    $result = $sanitize->invoke($repository, [
        'name' => 'Client Tanpa Email',
        'organization_id' => 10,
        'emails' => [
            ['value' => '', 'label' => 'work'],
        ],
        'contact_numbers' => [
            ['value' => '', 'label' => 'work'],
        ],
    ]);

    expect($result['emails'])->toBe([])
        ->and($result['contact_numbers'])->toBe([])
        ->and($result['unique_id'])->toBeNull();

    $repositorySource = file_get_contents(
        base_path('packages/Webkul/Contact/src/Repositories/PersonRepository.php')
    );

    expect($repositorySource)->toContain("\$data['emails'] ??= [];");
});

it('assigns new lead contacts and companies to the sales owner and accepts an optional company address', function (): void {
    $leadRepository = file_get_contents(
        base_path('packages/Webkul/Lead/src/Repositories/LeadRepository.php')
    );
    $personRepository = file_get_contents(
        base_path('packages/Webkul/Contact/src/Repositories/PersonRepository.php')
    );
    $personController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Contact/Persons/PersonController.php')
    );
    $leadForm = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Requests/LeadForm.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/leads/common/contact.blade.php')
    );

    expect($leadRepository)
        ->toContain('createPersonForCurrentSalesOwner')
        ->toContain("\$personData['user_id'] = \$userId");

    expect($personController)
        ->toContain("\$data['user_id'] = auth()->guard('user')->id();");

    expect($personRepository)
        ->toContain("\$data['organization_address']")
        ->toContain("\$organizationData['address'] = \$address;")
        ->toContain("'user_id' => \$userId ?: auth()->guard('user')->id()");

    expect($leadForm)
        ->toContain("'person.organization_address.address' => ['nullable', 'string', 'max:100']")
        ->toContain("'person.organization_address.country' => ['nullable', 'string', 'max:2']");

    expect($view)
        ->toContain('Company Address (Optional)')
        ->toContain("'person[organization_address]'")
        ->toContain('@if ($organizationAddressAttribute)');
});

it('saves the address and current owner on a newly added company', function (): void {
    $organization = (object) ['id' => 73];

    $organizationRepository = Mockery::mock(\Webkul\Contact\Repositories\OrganizationRepository::class);
    $organizationRepository
        ->shouldReceive('findOneWhere')
        ->once()
        ->with(['name' => 'Capture Studio'])
        ->andReturnNull();
    $organizationRepository
        ->shouldReceive('create')
        ->once()
        ->with([
            'entity_type' => 'organizations',
            'name' => 'Capture Studio',
            'user_id' => 42,
            'address' => [
                'address' => 'Jl. Sudirman 10',
                'country' => 'ID',
            ],
        ])
        ->andReturn($organization);

    $personRepository = (new ReflectionClass(PersonRepository::class))
        ->newInstanceWithoutConstructor();
    $organizationRepositoryProperty = new ReflectionProperty(
        PersonRepository::class,
        'organizationRepository'
    );
    $organizationRepositoryProperty->setValue(
        $personRepository,
        $organizationRepository
    );

    $result = $personRepository->fetchOrCreateOrganizationByName(
        ' Capture Studio ',
        [
            'address' => 'Jl. Sudirman 10',
            'country' => 'ID',
            'city' => '',
            'unknown_field' => 'ignored',
        ],
        42
    );

    expect($result)->toBe($organization);
});

it('carries the selected lead company address into a newly created quote', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );

    expect($controller)->toContain(
        "'billing_address' => \$lead->person->organization?->address"
    );
});
