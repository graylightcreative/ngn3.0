<?php
/**
 * Authoritative Image Resolution Helper - Aggressive Discovery v3
 * Resolves images by searching known compartmentalized directories.
 * This version prioritizes SLUG discovery if image_url is empty.
 */

if (!function_exists('resolve_ngn_image')) {
    function resolve_ngn_image(?string $filename, string $type = 'post', ?string $slug = null): string {
        $defaults = [
            'post'    => '/lib/images/site/2026/NGN-Emblem-Light.png',
            'user'    => '/lib/images/site/2026/default-avatar.png',
            'release' => '/lib/images/site/2026/default-avatar.png'
        ];
        
        $default = $defaults[$type] ?? $defaults['post'];
        $projectRoot = dirname(__DIR__, 2);

        // 1. Define base directories to search
        $baseDirs = [
            'post'    => ['/lib/images/posts/', '/uploads/posts/'],
            'user'    => ['/lib/images/users/', '/lib/images/labels/', '/lib/images/stations/', '/lib/images/venues/'],
            'release' => ['/lib/images/releases/', '/uploads/releases/']
        ];

        $dirs = $baseDirs[$type] ?? $baseDirs['post'];

        // 2. FILENAME RESOLUTION (If provided)
        if (!empty($filename) && !str_starts_with($filename, 'http')) {
            $cleanName = basename($filename);
            
            foreach ($dirs as $dir) {
                $paths = [];
                if ($slug) $paths[] = $dir . $slug . '/' . $cleanName;
                $paths[] = $dir . $cleanName;

                foreach ($paths as $relPath) {
                    if (file_exists($projectRoot . '/public' . $relPath) || file_exists($projectRoot . $relPath)) {
                        return $relPath;
                    }
                }
            }
        }

        // 3. AGGRESSIVE SLUG DISCOVERY (Fallback or Primary if filename empty)
        // If DB has no image_url, we scan the slug directory for ANY valid image.
        if ($slug) {
            foreach ($dirs as $dir) {
                $slugDir = $projectRoot . $dir . $slug;
                if (!is_dir($slugDir)) {
                    $slugDir = $projectRoot . '/public' . $dir . $slug;
                }

                if (is_dir($slugDir)) {
                    $files = scandir($slugDir);
                    foreach ($files as $f) {
                        if ($f[0] !== '.' && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
                            return $dir . $slug . '/' . $f;
                        }
                    }
                }
            }
        }

        return $default;
    }
}

if (!function_exists('post_image')) { function post_image(?string $filename): string { return resolve_ngn_image($filename, 'post'); } }
if (!function_exists('user_image')) { function user_image(?string $slug, ?string $filename): string { return resolve_ngn_image($filename, 'user', $slug); } }
if (!function_exists('release_image')) { function release_image(?string $filename, ?string $artistSlug = null): string { return resolve_ngn_image($filename, 'release', $artistSlug); } }
