<?php
/**
 * Authoritative Image Resolution Helper - Absolute Robustness
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

        // 1. Define SEARCH MATRIX (Absolute paths for file_exists)
        $searchMatrix = [
            'post' => [
                '/public/lib/images/posts/',
                '/lib/images/posts/',
                '/public/uploads/posts/',
                '/storage/uploads/posts/'
            ],
            'user' => [
                '/public/lib/images/users/',
                '/lib/images/users/',
                '/public/lib/images/labels/',
                '/public/lib/images/stations/',
                '/public/lib/images/artists/',
                '/lib/images/artists/'
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
                $candidates = [
                    $projectRoot . $dir . $cleanName,
                    $slug ? $projectRoot . $dir . $slug . '/' . $cleanName : null
                ];
                foreach (array_filter($candidates) as $absPath) {
                    if (file_exists($absPath)) {
                        // Return the web-relative path (remove project root and /public prefix)
                        $webPath = str_replace([$projectRoot . '/public', $projectRoot], '', $absPath);
                        return $webPath;
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
                            return str_replace([$projectRoot . '/public', $projectRoot], '', $absPath);
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
