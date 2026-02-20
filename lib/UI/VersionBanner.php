<?php
namespace NGN\Lib\UI;

/**
 * Version Banner Component
 * Displays version and environment information in a vertical side tab
 * Ref: Bible Ch. 4 (Visual DNA)
 */
class VersionBanner
{
    /**
     * Render version banner HTML
     *
     * @param string $version e.g., "2.1.0"
     * @param string $environment e.g., "production", "beta", "staging"
     * @param string $releaseDate e.g., "2026-02-19"
     * @return string HTML snippet
     */
    public static function render(string $version, string $environment, string $releaseDate): string
    {
        $envLabel = strtoupper($environment);
        $brandColor = '#FF5F1F'; // Electric Orange (Foundry Standard)

        return <<<HTML
<div class="ngn-version-tab" onclick="this.classList.toggle('expanded')" style="
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 999999;
    background: #0A0A0A;
    color: #ffffff;
    padding: 12px 6px;
    border: 1px solid rgba(255, 95, 31, 0.3);
    border-right: none;
    border-radius: 8px 0 0 8px;
    box-shadow: -4px 0 20px rgba(0,0,0,0.5);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    writing-mode: vertical-rl;
    text-orientation: mixed;
    letter-spacing: 0.1em;
    user-select: none;
">
    <div style="display: flex; align-items: center; gap: 8px;">
        <span style="color: {$brandColor};">NGN v{$version}</span>
        <span style="width: 4px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 50%;"></span>
        <span style="opacity: 0.6;">{$envLabel}</span>
    </div>
    
    <div class="details" style="
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: all 0.3s ease;
        font-size: 9px;
        color: #888;
        padding-top: 0;
    ">
        RELEASED: {$releaseDate}
    </div>
</div>

<style>
    .ngn-version-tab:hover {
        background: #111;
        border-color: #FF5F1F;
        padding-right: 10px;
    }
    .ngn-version-tab.expanded {
        padding-left: 15px;
    }
    .ngn-version-tab.expanded .details {
        max-height: 100px;
        opacity: 1;
        padding-top: 10px;
    }
    @media print {
        .ngn-version-tab { display: none !important; }
    }
</style>
HTML;
    }
}
