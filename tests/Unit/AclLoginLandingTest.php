<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('uses the ACL-filtered sidebar as the post-login destination', function (): void {
    $service = file_get_contents(
        base_path('packages/Webkul/Admin/src/Services/AclLandingPageService.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php')
    );
    $forgotPasswordController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/User/ForgotPasswordController.php')
    );
    $resetPasswordController = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/User/ResetPasswordController.php')
    );

    expect($service)
        ->toContain('SidebarNavigationService')
        ->toContain("getItems()->first()?->getUrl()")
        ->toContain("auth()->guard('user')->user()")
        ->toContain('if (! $user?->role)');

    expect($controller)
        ->toContain('AclLandingPageService')
        ->toContain('$this->landingPage->getUrl()')
        ->toContain("session()->forget('url.intended')")
        ->not->toContain("route('admin.dashboard.index')")
        ->not->toContain('redirect()->intended(');

    expect($forgotPasswordController)
        ->toContain('AclLandingPageService')
        ->toContain('$this->landingPage->getUrl()')
        ->not->toContain("route('admin.dashboard.index')");

    expect($resetPasswordController)
        ->toContain('AclLandingPageService')
        ->toContain('$this->landingPage->getUrl()')
        ->not->toContain("route('admin.dashboard.index')");
});

it('fully clears the previous account session on logout', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php')
    );

    expect($controller)
        ->toContain("auth()->guard('user')->logout()")
        ->toContain("request()->session()->invalidate()")
        ->toContain("request()->session()->regenerateToken()")
        ->toContain("request()->session()->regenerate()")
        ->toContain("request()->session()->forget('url.intended')");
});

it('uses a safe ACL destination for error and header links', function (): void {
    $errorView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/errors/index.blade.php')
    );
    $headerView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/header/index.blade.php')
    );

    expect($errorView)
        ->toContain('AclLandingPageService::class')
        ->toContain('href="{{ $safeUrl }}"')
        ->not->toContain("href=\"{{ url()->previous() }}\"")
        ->not->toContain("route('admin.dashboard.index')");

    expect($headerView)
        ->toContain('AclLandingPageService::class')
        ->not->toContain("<a href=\"{{ route('admin.dashboard.index') }}\">");
});
