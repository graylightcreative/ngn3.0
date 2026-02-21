<?php
/**
 * Authoritative Image Resolution Helper - Aggressive Discovery
 * Resolves images by searching known compartmentalized directories.
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

        // 2. If we have a filename, try to resolve it directly
        if (!empty($filename) && !str_starts_with($filename, 'http')) {
            $cleanName = basename($filename);
            
            foreach ($dirs as $dir) {
                // Try direct, then try slug subdirectory
                $paths = [
                    $dir . $cleanName,
                    $slug ? $dir . $slug . '/' . $cleanName : null
                ];

                foreach (array_filter($paths) as $relPath) {
                    if (file_exists($projectRoot . '/public' . $relPath) || file_exists($projectRoot . $relPath)) {
                        return $relPath;
                    }
                }
            }
        }

        // 3. AGGRESSIVE DISCOVERY: If not found or no filename, try to find ANY image in the slug directory
        if ($slug) {
            foreach ($dirs as $dir) {
                $checkPaths = [
                    $projectRoot . $dir . $slug,
                    $projectRoot . '/public' . $dir . $slug
                ];

                foreach ($checkPaths as $slugDir) {
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
