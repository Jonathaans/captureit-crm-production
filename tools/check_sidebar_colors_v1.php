<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Webkul\Admin\Services\SidebarThemeService;
use Webkul\Core\Core;

// Isolated configuration/palette checks. No application boot or database writes.
$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Install Composer dependencies before running this check.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';
require_once $root.'/packages/Webkul/Core/src/Http/helpers.php';

$checks = 0;
$check = static function (bool $passed, string $description) use (&$checks): void {
    if (! $passed) {
        throw new RuntimeException($description);
    }

    $checks++;
    fwrite(STDOUT, 'PASS '.$description.PHP_EOL);
};

try {
    $container = new Container;
    Container::setInstance($container);
    $container->instance('config', new Repository);

    $service = new SidebarThemeService;
    $defaults = $service->cssVariables([]);
    $check($defaults['--crm-nav-bg'] === '#385988', 'Default sidebar background');
    $check($defaults['--crm-nav-active'] === '#39255d', 'Default active menu background');
    $check($defaults['--crm-nav-accent'] === '#ffc21c', 'Default accent');

    $configuration = require $root.'/packages/Webkul/Admin/src/Config/core_config.php';
    $group = array_values(array_filter($configuration, fn ($entry) => $entry['key'] === 'general.settings.sidebar_colors'));
    $check(count($group) === 1 && count($group[0]['fields']) === 3, 'Exactly three color settings under General > Settings');
    $check(array_column($group[0]['fields'], 'default', 'name') === SidebarThemeService::DEFAULTS, 'Native configuration defaults match the sidebar defaults');
    $check(array_unique(array_column($group[0]['fields'], 'type')) === ['color'], 'Configuration uses the existing native color picker');

    $core = new class extends Core
    {
        public array $values = [];

        public array $readKeys = [];

        public function __construct() {}

        public function getConfigData(string $field): mixed
        {
            $this->readKeys[] = $field;

            return $this->values[$field] ?? null;
        }
    };
    $container->instance('core', $core);
    $check($service->cssVariables() === $defaults, 'An existing installation without saved colors uses the defaults');

    $core->values = [
        'general.settings.sidebar_colors.background_color' => '#f5f5f5',
        'general.settings.sidebar_colors.active_color' => '#ffd166',
        'general.settings.sidebar_colors.accent_color' => '#6b2136',
        'general.settings.menu_color.brand_color' => '#009999',
    ];
    $stored = $service->cssVariables();
    $check($stored['--crm-nav-bg'] === '#f5f5f5' && $stored['--crm-nav-active'] === '#ffd166' && $stored['--crm-nav-accent'] === '#6b2136', 'All three saved settings are read for the sidebar');
    $check($stored['--crm-nav-text'] === '#000000' && $stored['--crm-nav-active-text'] === '#000000', 'Light backgrounds get dark text');
    $check(! in_array('general.settings.menu_color.brand_color', $core->readKeys, true), 'Sidebar colors are independent of Brand Color');

    $core->values['general.settings.sidebar_colors.background_color'] = '#14213d';
    $check($service->cssVariables()['--crm-nav-bg'] === '#14213d', 'Updated configuration is reflected on the next render');

    $dark = $service->cssVariables(['background_color' => '#000000', 'active_color' => '#111111', 'accent_color' => '#000000']);
    $check($dark['--crm-nav-text'] === '#ffffff' && $dark['--crm-nav-active-text'] === '#ffffff', 'Dark backgrounds get light text');
    $check($dark['--crm-nav-accent-icon'] === '#ffffff' && $dark['--crm-nav-active-icon'] === '#ffffff', 'Icons stay visible when the accent matches the background');
    $check($service->cssVariables(['accent_color' => ' #AaBbCc '])['--crm-nav-accent'] === '#aabbcc', 'Mixed-case hex colors are normalized');

    foreach ([null, '', '#fff', 'red', '#123456; display:none', '</style><script>alert(1)</script>', ['#123456'], 123456] as $invalid) {
        $values = array_fill_keys(array_keys(SidebarThemeService::DEFAULTS), $invalid);
        $check($service->cssVariables($values) === $defaults, 'Invalid color falls back safely: '.json_encode($invalid));
    }

    $partial = $service->cssVariables(['background_color' => '#ffffff', 'active_color' => 'invalid']);
    $check($partial['--crm-nav-bg'] === '#ffffff' && $partial['--crm-nav-active'] === '#39255d', 'One invalid value does not discard the other valid settings');

    fwrite(STDOUT, "{$checks} sidebar color checks passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL '.$exception->getMessage().PHP_EOL);
    exit(1);
}
