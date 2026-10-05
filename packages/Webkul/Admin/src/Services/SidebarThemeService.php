<?php

namespace Webkul\Admin\Services;

class SidebarThemeService
{
    public const DEFAULTS = [
        'background_color' => '#385988',
        'active_color' => '#39255d',
        'accent_color' => '#ffc21c',
    ];

    /**
     * Return only validated or computed values for the sidebar's CSS variables.
     */
    public function cssVariables(?array $settings = null): array
    {
        $colors = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $settings === null
                ? core()->getConfigData('general.settings.sidebar_colors.'.$key)
                : ($settings[$key] ?? null);

            $value = is_string($value) ? trim($value) : '';
            $colors[$key] = preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1
                ? strtolower($value)
                : $default;
        }

        $background = $colors['background_color'];
        $active = $colors['active_color'];
        $accent = $colors['accent_color'];
        $text = $this->foreground($background);
        $activeText = $this->foreground($active);

        return [
            '--crm-nav-bg' => $background,
            '--crm-nav-text' => $text,
            '--crm-nav-ink' => implode(', ', $this->rgb($text)),
            '--crm-nav-muted' => $text,
            '--crm-nav-active' => $active,
            '--crm-nav-active-text' => $activeText,
            '--crm-nav-accent' => $accent,
            '--crm-nav-accent-rgb' => implode(', ', $this->rgb($accent)),
            '--crm-nav-accent-icon' => $this->contrast($accent, $background) >= 3 ? $accent : $text,
            '--crm-nav-active-icon' => $this->contrast($accent, $active) >= 3 ? $accent : $activeText,
        ];
    }

    private function foreground(string $background): string
    {
        return $this->contrast('#ffffff', $background) >= $this->contrast('#000000', $background)
            ? '#ffffff'
            : '#000000';
    }

    private function rgb(string $hex): array
    {
        return array_map(hexdec(...), str_split(substr($hex, 1), 2));
    }

    private function luminance(string $hex): float
    {
        $rgb = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $this->rgb($hex));

        return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
    }

    private function contrast(string $first, string $second): float
    {
        $luminance = [$this->luminance($first), $this->luminance($second)];

        return (max($luminance) + 0.05) / (min($luminance) + 0.05);
    }
}
