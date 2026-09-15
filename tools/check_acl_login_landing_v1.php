<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK ACL LOGIN LANDING V1';

$root = dirname(__DIR__);
$failures = 0;

function aclLoginCheck(bool $condition, string $message): void
{
    global $failures;

    echo ($condition ? '[PASS] ' : '[FAIL] ').$message.PHP_EOL;

    if (! $condition) {
        $failures++;
    }
}

function aclLoginPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function aclLoginContent(string $root, string $relative): string
{
    $path = aclLoginPath($root, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function aclLoginLint(string $root, string $relative): void
{
    $path = aclLoginPath($root, $relative);

    if (! is_file($path)) {
        aclLoginCheck(false, 'File tersedia: '.$relative);

        return;
    }

    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

    aclLoginCheck($exitCode === 0, 'PHP lint: '.$relative);
}

echo CHECK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(CHECK_TITLE)).PHP_EOL.PHP_EOL;

$service = 'packages/Webkul/Admin/src/Services/AclLandingPageService.php';
$controller = 'packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php';
$forgotPasswordController = 'packages/Webkul/Admin/src/Http/Controllers/User/ForgotPasswordController.php';
$resetPasswordController = 'packages/Webkul/Admin/src/Http/Controllers/User/ResetPasswordController.php';
$errorView = 'packages/Webkul/Admin/src/Resources/views/errors/index.blade.php';
$headerView = 'packages/Webkul/Admin/src/Resources/views/components/layouts/header/index.blade.php';

foreach ([$service, $controller, $forgotPasswordController, $resetPasswordController] as $relative) {
    aclLoginLint($root, $relative);
}

$serviceContent = aclLoginContent($root, $service);
$controllerContent = aclLoginContent($root, $controller);
$forgotPasswordContent = aclLoginContent($root, $forgotPasswordController);
$resetPasswordContent = aclLoginContent($root, $resetPasswordController);
$errorViewContent = aclLoginContent($root, $errorView);
$headerViewContent = aclLoginContent($root, $headerView);

aclLoginCheck(
    str_contains($serviceContent, 'SidebarNavigationService')
        && str_contains($serviceContent, "getItems()->first()?->getUrl()"),
    'Halaman awal memakai menu pertama yang sudah difilter ACL',
);

aclLoginCheck(
    str_contains($controllerContent, '$this->landingPage->getUrl()')
        && ! str_contains($controllerContent, "route('admin.dashboard.index')")
        && ! str_contains($controllerContent, 'redirect()->intended('),
    'Login tidak lagi memaksa semua role ke admin/dashboard',
);

aclLoginCheck(
    str_contains($forgotPasswordContent, '$this->landingPage->getUrl()')
        && str_contains($resetPasswordContent, '$this->landingPage->getUrl()')
        && ! str_contains($forgotPasswordContent, "route('admin.dashboard.index')")
        && ! str_contains($resetPasswordContent, "route('admin.dashboard.index')"),
    'Lupa/reset password juga kembali ke halaman yang diizinkan ACL',
);

aclLoginCheck(
    str_contains($controllerContent, "request()->session()->regenerate()")
        && str_contains($controllerContent, "request()->session()->forget('url.intended')"),
    'Login membuat session baru tanpa tujuan akun sebelumnya',
);

aclLoginCheck(
    str_contains($controllerContent, "request()->session()->invalidate()")
        && str_contains($controllerContent, "request()->session()->regenerateToken()"),
    'Logout membersihkan session dan token akun sebelumnya',
);

aclLoginCheck(
    substr_count($errorViewContent, 'href="{{ $safeUrl }}"') === 2
        && ! str_contains($errorViewContent, "url()->previous()")
        && ! str_contains($errorViewContent, "route('admin.dashboard.index')"),
    'Tautan halaman error kembali ke halaman yang diizinkan ACL',
);

aclLoginCheck(
    str_contains($headerViewContent, 'AclLandingPageService::class')
        && ! str_contains($headerViewContent, "<a href=\"{{ route('admin.dashboard.index') }}\">"),
    'Logo header menuju halaman yang diizinkan ACL',
);

aclLoginCheck(
    ! is_file(aclLoginPath($root, 'database/migrations/2026_09_15_160000_acl_login_landing.php')),
    'Perbaikan tidak membutuhkan migration database',
);

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] '.$failures.' pemeriksaan belum lulus.'.PHP_EOL;
    exit(1);
}

echo '[PASS] 8 pemeriksaan ACL login landing lulus.'.PHP_EOL;
