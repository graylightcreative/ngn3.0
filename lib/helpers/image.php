<?php
/**
 * Authoritative Image Resolution Helper - Absolute Robustness v2
 * Bible Ref: Chapter 10 (Writer Engine) // Image Integrity
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

        // 1. Define SEARCH MATRIX (Relative to project root)
        $searchMatrix = [
            'post' => [
                '/public/lib/images/posts/',
                '/lib/images/posts/',
                '/public/uploads/posts/',
                '/storage/uploads/posts/',
                '/public/uploads/',
                '/storage/uploads/'
            ],
            'user' => [
                '/public/lib/images/users/',
                '/lib/images/users/',
                '/public/lib/images/labels/',
                '/lib/images/labels/',
                '/public/lib/images/stations/',
                '/lib/images/stations/',
                '/public/lib/images/venues/',
                '/lib/images/venues/',
                '/public/lib/images/artists/',
                '/lib/images/artists/',
                '/public/uploads/users/',
                '/storage/uploads/users/'
            ],
            'release' => [
                '/public/lib/images/releases/',
                '/lib/images/releases/',
                '/public/uploads/releases/',
                '/storage/uploads/releases/'
            ]
        ];

        $dirs = $searchMatrix[$type] ?? $searchMatrix['post'];

        // 2. FILENAME RESOLUTION
        if (!empty($filename) && !str_starts_with($filename, 'http')) {
            $cleanName = basename($filename);
            foreach ($dirs as $dir) {
                $candidates = [];
                if ($slug) $candidates[] = $projectRoot . $dir . $slug . '/' . $cleanName;
                $candidates[] = $projectRoot . $dir . $cleanName;

                foreach ($candidates as $absPath) {
                    if (file_exists($absPath)) {
                        $webPath = str_replace([$projectRoot . '/public', $projectRoot], '', $absPath);
                        return '/' . ltrim($webPath, '/');
                    }
                }
            }
        }

        // 3. SLUG-BASED DISCOVERY (FALLBACK)
        if ($slug) {
            foreach ($dirs as $dir) {
                $slugDir = $projectRoot . $dir . $slug;
                if (is_dir($slugDir)) {
                    $files = scandir($slugDir);
                    foreach ($files as $f) {
                        if ($f[0] !== '.' && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
                            $absPath = $slugDir . '/' . $f;
                            $webPath = str_replace([$projectRoot . '/public', $projectRoot], '', $absPath);
                            return '/' . ltrim($webPath, '/');
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
