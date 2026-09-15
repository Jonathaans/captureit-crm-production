<?php

declare(strict_types=1);

/**
 * Read-only structural checker for Quote Terms & Conditions PDF V1.
 *
 * Run from the project root:
 * php tools/check_quote_terms_and_conditions_pdf_v1.php
 */

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

function quoteTermsCheck(bool $condition, string $description): void
{
    global $checks, $errors;

    if ($condition) {
        $checks++;
        echo '[PASS] '.$description.PHP_EOL;

        return;
    }

    $errors[] = $description;
    echo '[FAIL] '.$description.PHP_EOL;
}

$paths = [
    'quote PDF' => $root.'/packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php',
    'terms partial' => $root.'/packages/Webkul/Admin/src/Resources/views/quotes/partials/terms-and-conditions.blade.php',
];

$contents = [];

foreach ($paths as $label => $path) {
    quoteTermsCheck(is_file($path), ucfirst($label).' tersedia');
    $contents[$label] = is_file($path) ? (string) file_get_contents($path) : '';
}

$quotePdf = $contents['quote PDF'];
$terms = $contents['terms partial'];

quoteTermsCheck(
    str_contains($quotePdf, "@include('admin::quotes.partials.terms-and-conditions'")
    && strpos($quotePdf, "@include('admin::quotes.partials.terms-and-conditions'")
        > strpos($quotePdf, '<table class="bottom-table">'),
    'Terms & Conditions ditempatkan setelah isi quotation'
);

quoteTermsCheck(
    substr_count($terms, '<section class="terms-page') === 4
    && str_contains($quotePdf, 'page-break-before: always;'),
    'Lampiran memiliki tepat empat section halaman dengan page break'
);

$allSectionsPresent = true;

foreach (range(1, 18) as $sectionNumber) {
    $allSectionsPresent = $allSectionsPresent
        && str_contains($terms, '>'.$sectionNumber.'. ');
}

quoteTermsCheck($allSectionsPresent, 'Seluruh 18 pasal Terms & Conditions tersedia');

quoteTermsCheck(
    str_contains($quotePdf, '$quote->person?->name')
    && str_contains($quotePdf, '$quote->person?->organization?->name')
    && str_contains($quotePdf, "?: '-'")
    && str_contains($terms, '{{ $clientName }}')
    && str_contains($terms, '{{ $clientCompanyName }}'),
    'Nama client dan perusahaan memakai detail quote dengan fallback aman'
);

quoteTermsCheck(
    str_contains($terms, 'Rudy Tinambunan')
    && str_contains($terms, 'PT Varbel Anvaya Bersaudara')
    && str_contains($terms, 'Disetujui oleh,'),
    'Blok persetujuan perusahaan dan client tersedia'
);

quoteTermsCheck(
    str_contains($quotePdf, 'Asosiasi Rental Indonesia')
    && str_contains($quotePdf, 'JK-0006-VII-2026'),
    'Identitas keanggotaan Rental Indonesia tersedia di footer'
);

quoteTermsCheck(
    ! str_contains($quotePdf, 'DB::')
    && ! str_contains($terms, 'DB::')
    && ! str_contains($terms, 'query()'),
    'Template tidak melakukan perubahan atau query database langsung'
);

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] '.count($errors).' pemeriksaan gagal.'.PHP_EOL;
    exit(1);
}

echo '[PASS] '.$checks.' pemeriksaan Quote Terms & Conditions lulus.'.PHP_EOL;
