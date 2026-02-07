<?php
namespace NGN\Lib\UI;

/**
 * Version Banner Component
 * Displays version and environment information in fixed header bar
 */
class VersionBanner
{
    /**
     * Render version banner HTML
     *
     * @param string $version e.g., "2.0.1", "2.0.2"
     * @param string $environment e.g., "production", "beta", "staging"
     * @param string $releaseDate e.g., "2026-01-30"
     * @return string HTML snippet
     */
    public static function render(string $version, string $environment, string $releaseDate): string
    {
        $colors = [
            'production' => ['bg' => '#10b981', 'text' => '#ffffff'],  // Green
            'beta' => ['bg' => '#f59e0b', 'text' => '#000000'],        // Orange
            'staging' => ['bg' => '#3b82f6', 'text' => '#ffffff'],     // Blue
            'dev' => ['bg' => '#8b5cf6', 'text' => '#ffffff'],         // Purple
        ];

        $color = $colors[$environment] ?? $colors['dev'];
        $envLabel = strtoupper($environment);

        return <<<HTML
<div class="ngn-version-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: {$color['bg']};
    color: {$color['text']};
    padding: 8px 16px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
    border-bottom: 2px solid rgba(0,0,0,0.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
">
    <span style="opacity: 0.9;">NGN {$version}</span>
    <span style="
        display: inline-block;
        margin: 0 12px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.6;
        vertical-align: middle;
    "></span>
    <span style="opacity: 0.8;">{$envLabel}</span>
    <span style="
        display: inline-block;
        margin: 0 12px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.6;
        vertical-align: middle;
    "></span>
    <span style="opacity: 0.7; font-size: 11px;">Released {$releaseDate}</span>
</div>
<style>
    body { margin-top: 40px !important; }
    @media print {
        .ngn-version-banner { display: none !important; }
        body { margin-top: 0 !important; }
    }
</style>
HTML;
    }
}
