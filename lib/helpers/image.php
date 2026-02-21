<?php
/**
 * Image Resolution Helper - High Integrity
 * Bible Ref: Chapter 10 (Writer Engine) // Image Integrity
 */

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        $default = '/lib/images/site/2026/NGN-Emblem-Light.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        // Strip redundant prefix if DB has 'posts/filename.jpg'
        $cleanName = str_replace('posts/', '', $filename);

        $projectRoot = dirname(__DIR__, 2);

        $candidates = [
            '/uploads/posts/' . $cleanName,
            '/lib/images/posts/' . $cleanName,
            '/uploads/' . $cleanName
        ];

        foreach ($candidates as $relPath) {
            if (file_exists($projectRoot . '/public' . $relPath)) {
                return $relPath;
            }
        }

        return $default;
    }
}

if (!function_exists('user_image')) {
    function user_image(?string $slug, ?string $filename): string {
        $default = '/lib/images/site/2026/default-avatar.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        $projectRoot = dirname(__DIR__, 2);

        $candidates = [
            "/lib/images/users/{$slug}/{$filename}",
            "/lib/images/users/{$filename}",
            "/uploads/artists/{$slug}/{$filename}",
            "/uploads/users/{$slug}/{$filename}",
            "/uploads/{$filename}",
            "/lib/images/labels/{$filename}",
            "/lib/images/stations/{$filename}",
            "/lib/images/venues/{$filename}"
        ];

        foreach ($candidates as $relPath) {
            if (file_exists($projectRoot . '/public' . $relPath)) {
                return $relPath;
            }
        }

        return $default;
    }
}

if (!function_exists('release_image')) {
    function release_image(?string $filename): string {
        $default = '/lib/images/site/2026/default-avatar.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        $projectRoot = dirname(__DIR__, 2);

        $candidates = [
            '/uploads/releases/' . $filename,
            '/lib/images/releases/' . $filename,
            '/uploads/' . $filename
        ];

        foreach ($candidates as $relPath) {
            if (file_exists($projectRoot . '/public' . $relPath)) {
                return $relPath;
            }
        }

        return $default;
    }
}
