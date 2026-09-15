<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('appends four terms and conditions pages to the quote PDF', function (): void {
    $quotePdf = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php')
    );
    $terms = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/partials/terms-and-conditions.blade.php')
    );

    expect($quotePdf)
        ->toContain("@include('admin::quotes.partials.terms-and-conditions'")
        ->toContain('$quote->person?->name')
        ->toContain('$quote->person?->organization?->name')
        ->toContain('$clientName')
        ->toContain('$clientCompanyName')
        ->toContain("trim((string)")
        ->toContain("?: '-'");

    expect(substr_count($terms, '<section class="terms-page'))->toBe(4);

    foreach (range(1, 18) as $sectionNumber) {
        expect($terms)->toContain('>'.$sectionNumber.'. ');
    }

    expect($terms)
        ->toContain('TERMS &amp; CONDITIONS')
        ->toContain('Rudy Tinambunan')
        ->toContain('PT Varbel Anvaya Bersaudara')
        ->toContain('{{ $clientName }}')
        ->toContain('{{ $clientCompanyName }}');
});

it('places terms after the quotation and keeps every terms page isolated', function (): void {
    $quotePdf = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php')
    );
    $terms = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/partials/terms-and-conditions.blade.php')
    );

    expect(strpos($quotePdf, "@include('admin::quotes.partials.terms-and-conditions'"))
        ->toBeGreaterThan(strpos($quotePdf, '<table class="bottom-table">'));

    expect($quotePdf)
        ->toContain('.terms-page {')
        ->toContain('page-break-before: always;')
        ->toContain('JK-0006-VII-2026');

    expect($terms)
        ->toContain('terms-page-one')
        ->toContain('terms-page-two')
        ->toContain('terms-page-three')
        ->toContain('terms-page-four');
});
