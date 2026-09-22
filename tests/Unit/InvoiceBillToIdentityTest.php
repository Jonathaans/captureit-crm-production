<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('stores the selected quote bill to identity on generated invoices', function (): void {
    $migration = file_get_contents(
        base_path('database/migrations/2026_09_21_140000_add_bill_to_identity_to_invoices_table.php')
    );
    $model = file_get_contents(
        base_path('packages/Webkul/Invoice/src/Models/Invoice.php')
    );
    $flexibleService = file_get_contents(
        base_path('packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php')
    );
    $legacyService = file_get_contents(
        base_path('packages/Webkul/Invoice/src/Services/InvoiceService.php')
    );

    expect($migration)
        ->toContain("string('bill_to_display_mode', 20)")
        ->toContain("string('bill_to_person_name')")
        ->toContain("string('bill_to_company_name')");

    expect($model)
        ->toContain("'bill_to_display_mode'")
        ->toContain("'bill_to_person_name'")
        ->toContain("'bill_to_company_name'")
        ->toContain('public function billToIdentity(): array')
        ->toContain('$quote?->bill_to_person_name')
        ->toContain('$quote?->bill_to_company_name');

    foreach ([$flexibleService, $legacyService] as $service) {
        expect($service)
            ->toContain('resolveQuoteBillToIdentity')
            ->toContain("'bill_to_display_mode' => \$billToIdentity['mode']")
            ->toContain("'bill_to_person_name' => \$billToIdentity['person_name']")
            ->toContain("'bill_to_company_name' => \$billToIdentity['company_name']");
    }
});

it('prints invoice bill to using the selected person company mode', function (): void {
    $pdf = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php')
    );
    $show = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php')
    );

    foreach ([$pdf, $show] as $view) {
        expect($view)
            ->toContain('$invoice->billToIdentity()')
            ->toContain("\$billToIdentity['mode'] === 'company'")
            ->toContain("\$billToIdentity['mode'] === 'both'")
            ->toContain("\$billToIdentity['person_name']")
            ->toContain("\$billToIdentity['company_name']");
    }

    expect($pdf)
        ->not->toContain('Attn: {{ $billToIdentity');
});
