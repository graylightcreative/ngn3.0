<?php
/**
 * Authoritative Image Resolution Helper
 * Resolves images by searching known compartmentalized directories.
 */

if (!function_exists('resolve_ngn_image')) {
    /**
     * Core resolution logic for all images
     */
    function resolve_ngn_image(?string $filename, string $type = 'post', ?string $slug = null): string {
        $defaults = [
            'post'    => '/lib/images/site/2026/NGN-Emblem-Light.png',
            'user'    => '/lib/images/site/2026/default-avatar.png',
            'release' => '/lib/images/site/2026/default-avatar.png'
        ];
        
        $default = $defaults[$type] ?? $defaults['post'];
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http')) return $filename;

        // 1. Extract the core filename (strip all legacy path noise)
        $cleanName = basename($filename);
        $projectRoot = dirname(__DIR__, 2);

        // 2. Define search priority based on type
        $searchMatrix = [
            'post' => [
                '/lib/images/posts/' . $cleanName,
                '/uploads/posts/' . $cleanName,
                '/uploads/' . $cleanName
            ],
            'user' => [
                $slug ? "/lib/images/users/{$slug}/{$cleanName}" : null,
                "/lib/images/users/{$cleanName}",
                "/lib/images/labels/{$cleanName}",
                "/lib/images/stations/{$cleanName}",
                "/lib/images/venues/{$cleanName}",
                $slug ? "/uploads/users/{$slug}/{$cleanName}" : null,
                "/uploads/{$cleanName}"
            ],
            'release' => [
                $slug ? "/lib/images/releases/{$slug}/{$cleanName}" : null,
                "/lib/images/releases/{$cleanName}",
                "/uploads/releases/{$cleanName}",
                "/uploads/{$cleanName}"
            ]
        ];

        $candidates = array_filter($searchMatrix[$type] ?? []);

        // 3. Physical check in public/ directory
        foreach ($candidates as $relPath) {
            if (file_exists($projectRoot . '/public' . $relPath)) {
                return $relPath;
            }
        }

        return $default;
    }
}

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        return resolve_ngn_image($filename, 'post');
    }
}

if (!function_exists('user_image')) {
    function user_image(?string $slug, ?string $filename): string {
        return resolve_ngn_image($filename, 'user', $slug);
    }
}

if (!function_exists('release_image')) {
    function release_image(?string $filename, ?string $artistSlug = null): string {
        return resolve_ngn_image($filename, 'release', $artistSlug);
    }
}
