<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('limits quote sales owners to active users in the four allowed roles', function (): void {
    $service = file_get_contents(
        base_path('packages/Webkul/Admin/src/Services/QuoteSalesOwnerService.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );

    expect($service)
        ->toContain("'administrator'")
        ->toContain("'sales admin'")
        ->toContain("'superadministrator'")
        ->toContain("'sales user'")
        ->toContain("->where('status', true)")
        ->toContain("DB::raw('LOWER(TRIM(name))')")
        ->toContain('public function isEligible(');

    expect($controller)
        ->toContain('$this->quoteSalesOwnerService->isEligible($selectedOwnerId)')
        ->toContain('Sales Owner harus aktif dan memiliki role Administrator, Sales Admin, SuperAdministrator, atau Sales User.');
});

it('searches eligible sales owners by name or email after two characters', function (): void {
    $service = file_get_contents(
        base_path('packages/Webkul/Admin/src/Services/QuoteSalesOwnerService.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );
    $routes = file_get_contents(
        base_path('packages/Webkul/Admin/src/Routes/Admin/quote-routes.php')
    );

    expect($service)
        ->toContain('public const SEARCH_LIMIT = 20;')
        ->toContain('mb_strlen($searchTerm) < 2')
        ->toContain("->where('name', 'like', '%'.\$searchTerm.'%')")
        ->toContain("->orWhere('email', 'like', '%'.\$searchTerm.'%')")
        ->toContain('->limit($limit)');

    expect($controller)
        ->toContain('public function salesOwners(): JsonResponse')
        ->toContain('$this->quoteSalesOwnerService->search($searchTerm, $limit)');

    expect($routes)
        ->toContain("Route::get('sales-owners', 'salesOwners')")
        ->toContain("name('admin.quotes.sales_owners')");
});

it('uses a debounced searchable sales owner control on quote create and edit', function (): void {
    $createView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php')
    );
    $editView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php')
    );
    $lookup = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/partials/sales-owner-lookup.blade.php')
    );

    foreach ([$createView, $editView] as $view) {
        expect($view)
            ->toContain("@include('admin::quotes.partials.sales-owner-lookup')")
            ->toContain('<v-quote-sales-owner-lookup')
            ->toContain("route('admin.quotes.sales_owners')")
            ->not->toContain('Select Sales Owner</option>')
            ->not->toContain('$quoteSalesOwners = app(');
    }

    expect($lookup)
        ->toContain('type="hidden"')
        ->toContain('name="user_id"')
        ->toContain('minimumCharacters: 2')
        ->toContain('debounceMilliseconds: 300')
        ->toContain('Ketik minimal 2 huruf nama atau email')
        ->toContain('this.$axios.get(this.searchUrl')
        ->toContain('@{{ owner.role_name }}')
        ->toContain('@{{ owner.email }}');
});

it('keeps the existing owner visible for old quotes and locks it on archived quotes', function (): void {
    $service = file_get_contents(
        base_path('packages/Webkul/Admin/src/Services/QuoteSalesOwnerService.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php')
    );
    $editView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php')
    );

    expect($service)
        ->toContain('public function initialSelection(')
        ->toContain('$isUnchangedLegacyOwner')
        ->toContain("'is_legacy_current' => \$isLegacyCurrent");

    expect($controller)
        ->toContain('(int) $quote->user_id')
        ->toContain("'salesOwnerLookUpData'");

    expect($editView)
        ->toContain(':disabled="@json((bool) $archiveReason)"');
});
