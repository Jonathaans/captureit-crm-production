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
