<?php

declare(strict_types=1);

namespace NGN\Lib\Theme;

/**
 * 🎵 Spotify Killer Theme - Admin-Only Feature Flag
 * 
 * Controls visibility and activation of the new 2.0 theme.
 * Theme is admin-only until Phase 6 rollout.
 * 
 * Usage:
 *   <?php if (SpotifyKillerTheme::isEnabled()): ?>
 *     <link rel="stylesheet" href="/frontend/src/spotify-killer-theme.css">
 *     <body class="sk-theme">
 *   <?php endif; ?>
 */
class SpotifyKillerTheme
{
    /** @var string Theme version */
    public const VERSION = '1.0.0';

    /** @var string CSS file path */
    public const CSS_PATH = '/frontend/src/spotify-killer-theme.css';

    /** @var string Cookie name for theme preference */
    private const COOKIE_NAME = 'ngn_sk_theme';

    /** @var int Cookie expiry (30 days) */
    private const COOKIE_EXPIRY = 2592000;

    /**
     * Check if theme should be enabled for current user
     * Admin-only during development phases 1-5
     */
    public static function isEnabled(): bool
    {
        // Check if user has admin role
        if (!self::isAdmin()) {
            return false;
        }

        // Check cookie preference (admin can toggle)
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            return $_COOKIE[self::COOKIE_NAME] === '1';
        }

        // Default: enabled for admins
        return true;
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool
    {
        // Check session for admin role
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check various admin indicators
        if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
            return true;
        }

        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            return true;
        }

        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return true;
        }

        // Check for JWT-based admin (from API auth)
        if (isset($_SERVER['HTTP_X_USER_ROLE']) && $_SERVER['HTTP_X_USER_ROLE'] === 'admin') {
            return true;
        }

        return false;
    }

    /**
     * Toggle theme on/off for admin
     */
    public static function toggle(bool $enabled): void
    {
        if (!self::isAdmin()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $enabled ? '1' : '0',
            time() + self::COOKIE_EXPIRY,
            '/',
            '',
            true, // Secure
            true  // HttpOnly
        );
    }

    /**
     * Get CSS link tag if theme is enabled
     */
    public static function getCssLink(): string
    {
        if (!self::isEnabled()) {
            return '';
        }

        $version = self::VERSION;
        return '<link rel="stylesheet" href="' . self::CSS_PATH . '?v=' . $version . '">';
    }

    /**
     * Get body class if theme is enabled
     */
    public static function getBodyClass(): string
    {
        return self::isEnabled() ? 'sk-theme' : '';
    }

    /**
     * Get theme toggle button HTML (admin only)
     */
    public static function getToggleButton(): string
    {
        if (!self::isAdmin()) {
            return '';
        }

        $enabled = self::isEnabled();
        $icon = $enabled ? '🌙' : '☀️';
        $label = $enabled ? 'SK Theme ON' : 'SK Theme OFF';
        $action = $enabled ? '0' : '1';

        return <<<HTML
        <form method="POST" action="/admin/toggle-theme.php" style="display:inline;">
            <input type="hidden" name="sk_theme" value="{$action}">
            <button type="submit" class="sk-theme-toggle" title="Toggle Spotify Killer Theme">
                <span class="sk-theme-toggle-icon">{$icon}</span>
                <span class="sk-theme-toggle-label">{$label}</span>
            </button>
        </form>
        <style>
            .sk-theme-toggle {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: linear-gradient(135deg, #1DB954 0%, #0d7377 100%);
                border: none;
                border-radius: 20px;
                color: white;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .sk-theme-toggle:hover {
                transform: scale(1.05);
                box-shadow: 0 0 15px rgba(29,185,84,0.4);
            }
            .sk-theme-toggle-icon {
                font-size: 14px;
            }
        </style>
        HTML;
    }

    /**
     * Get Google Fonts link for theme typography
     */
    public static function getFontsLink(): string
    {
        if (!self::isEnabled()) {
            return '';
        }

        return '<link rel="preconnect" href="https://fonts.googleapis.com">' .
               '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
               '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;900&display=swap" rel="stylesheet">';
    }

    /**
     * Output all required head tags for theme
     */
    public static function renderHead(): string
    {
        if (!self::isEnabled()) {
            return '';
        }

        return self::getFontsLink() . "\n" . self::getCssLink();
    }
}
