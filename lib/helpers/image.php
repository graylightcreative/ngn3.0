<?php
/**
 * Authoritative Image Resolution Helper
 * Maps legacy DB paths to the compartmentalized lib/images architecture.
 */

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        $default = '/lib/images/site/2026/NGN-Emblem-Light.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http')) return $filename;

        $clean = str_replace('posts/', '', $filename);
        $clean = ltrim($clean, '/');

        // Target: /lib/images/posts/
        return '/lib/images/posts/' . $clean;
    }
}

if (!function_exists('user_image')) {
    function user_image(?string $slug, ?string $filename): string {
        $default = '/lib/images/site/2026/default-avatar.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http')) return $filename;

        // Strip legacy prefixes
        $clean = str_replace(['/uploads/users/', '/uploads/', 'users/'], '', $filename);
        $clean = ltrim($clean, '/');
        
        $base = basename($clean);

        // Logic: Try slug subdirectory, then root users directory
        $projectRoot = dirname(__DIR__, 2);
        
        if ($slug && file_exists($projectRoot . "/lib/images/users/{$slug}/{$base}")) {
            return "/lib/images/users/{$slug}/{$base}";
        }
        
        if (file_exists($projectRoot . "/lib/images/users/{$base}")) {
            return "/lib/images/users/{$base}";
        }

        // Fallback to exactly what was requested but in lib/images
        return "/lib/images/users/" . $clean;
    }
}

if (!function_exists('release_image')) {
    function release_image(?string $filename, ?string $artistSlug = null): string {
        $default = '/lib/images/site/2026/default-avatar.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http')) return $filename;

        $clean = str_replace(['/uploads/releases/', '/uploads/'], '', $filename);
        $clean = ltrim($clean, '/');
        $base = basename($clean);

        $projectRoot = dirname(__DIR__, 2);

        // Try artist subdirectory if provided
        if ($artistSlug && file_exists($projectRoot . "/lib/images/releases/{$artistSlug}/{$base}")) {
            return "/lib/images/releases/{$artistSlug}/{$base}";
        }

        return "/lib/images/releases/" . $clean;
    }
}
