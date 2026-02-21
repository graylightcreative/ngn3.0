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

        // Use absolute filesystem root for existence check
        $projectRoot = dirname(__DIR__, 2);

        // Targeted search paths (relative to web root)
        $candidates = [
            '/uploads/posts/' . $filename,
            '/lib/images/posts/' . $filename,
            '/uploads/' . $filename
        ];

        foreach ($candidates as $relPath) {
            // Check in public/ (where the web server looks)
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
            "/uploads/artists/{$slug}/{$filename}",
            "/uploads/users/{$slug}/{$filename}",
            "/uploads/labels/{$filename}",
            "/uploads/stations/{$filename}",
            "/uploads/venues/{$filename}",
            "/uploads/{$filename}",
            "/lib/images/users/{$filename}",
            "/lib/images/labels/{$filename}"
        ];

        foreach ($candidates as $relPath) {
            if (file_exists($projectRoot . '/public' . $relPath)) {
                return $relPath;
            }
        }

        return $default;
    }
}
