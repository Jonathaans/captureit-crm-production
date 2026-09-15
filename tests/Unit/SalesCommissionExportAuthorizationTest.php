<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('restricts commission exports to administrator roles', function (): void {
    $commissionController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Invoice/SalesCommissionExportController.php')
    );
    $dashboardView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php')
    );

    expect($commissionController)
        ->toContain('ALLOWED_EXPORT_ROLES')
        ->toContain("'administrator'")
        ->toContain("'superadministrator'")
        ->toContain('$user->role?->name')
        ->toContain('in_array($roleName, self::ALLOWED_EXPORT_ROLES, true)');

    expect($dashboardView)
        ->toContain('$canExportSalesCommission')
        ->toContain("['administrator', 'superadministrator']")
        ->toContain('@if ($canExportSalesCommission)');

    expect(str_contains($commissionController, '$user->hasPermission('))->toBeFalse();

    $allowedRoles = ['administrator', 'superadministrator'];

    expect(in_array('administrator', $allowedRoles, true))->toBeTrue();
    expect(in_array('superadministrator', $allowedRoles, true))->toBeTrue();
    expect(in_array('sales admin', $allowedRoles, true))->toBeFalse();
    expect(in_array('sales user', $allowedRoles, true))->toBeFalse();
    expect(in_array('head warehouse', $allowedRoles, true))->toBeFalse();
});
