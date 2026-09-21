<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('persists a quote bill to snapshot and validates the selected display mode', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );
    $model = file_get_contents(
        base_path('packages/Webkul/Quote/src/Models/Quote.php')
    );
    $migration = file_get_contents(
        base_path('database/migrations/2026_09_21_130000_add_bill_to_identity_to_quotes_table.php')
    );

    expect($controller)
        ->toContain("Rule::in(['person', 'company', 'both'])")
        ->toContain("\$data['bill_to_person_name'] = \$personName")
        ->toContain("\$data['bill_to_company_name'] = \$companyName ?: null")
        ->toContain("'client_signer_name'")
        ->toContain('prepareBillToIdentity($request->all())')
        ->toContain('Contact yang dipilih belum memiliki Company.');

    expect($model)
        ->toContain("'bill_to_display_mode'")
        ->toContain("'bill_to_person_name'")
        ->toContain("'bill_to_company_name'")
        ->toContain("'client_signer_name'")
        ->toContain("'client_signer_company'");

    expect($migration)
        ->toContain("string('bill_to_display_mode', 20)")
        ->toContain("default('person')")
        ->toContain("string('bill_to_person_name')")
        ->toContain("string('bill_to_company_name')")
        ->toContain("string('client_signer_name')")
        ->toContain("string('client_signer_company')");
});

it('shows company context in the quote bill to lookup and form', function (): void {
    $routes = file_get_contents(
        base_path('packages/Webkul/Admin/src/Routes/Admin/quote-routes.php')
    );
    $createView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php')
    );
    $editView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php')
    );
    $identityFields = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/partials/bill-to-identity-fields.blade.php')
    );

    expect($routes)
        ->toContain("name('admin.quotes.bill_to_people')")
        ->toContain("name('admin.quotes.bill_to_person')");

    foreach ([$createView, $editView] as $view) {
        expect($view)
            ->toContain("search-url=\"{{ route('admin.quotes.bill_to_people') }}\"")
            ->toContain("lookup-entity-url=\"{{ route('admin.quotes.bill_to_person') }}\"")
            ->toContain("@include('admin::quotes.partials.bill-to-identity-fields')")
            ->toContain('personCompanyName')
            ->toContain('clientSignerName');
    }

    expect($identityFields)
        ->toContain('name="bill_to_display_mode"')
        ->toContain('value="person"')
        ->toContain('value="company"')
        ->toContain('value="both"')
        ->toContain('name="client_signer_name"')
        ->toContain('name="client_signer_company"')
        ->toContain('Attn:');
});

it('renders the selected bill to format and signature snapshots in the quote PDF', function (): void {
    $quotePdf = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php')
    );
    $terms = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/partials/terms-and-conditions.blade.php')
    );

    expect($quotePdf)
        ->toContain('$quote->bill_to_person_name')
        ->toContain('$quote->bill_to_company_name')
        ->toContain('$quote->client_signer_name')
        ->toContain('$quote->client_signer_company')
        ->toContain("\$billToDisplayMode === 'company'")
        ->toContain("\$billToDisplayMode === 'both'")
        ->toContain('Attn: {{ $billToPersonName }}')
        ->toContain('$quote->person?->organization?->name');

    expect($terms)
        ->toContain('@if ($clientCompanyName)')
        ->toContain('{{ $clientName }}')
        ->toContain('{{ $clientCompanyName }}');
});
