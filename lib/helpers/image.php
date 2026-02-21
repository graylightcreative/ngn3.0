<?php
/**
 * Image Resolution Helper
 * Bible Ref: Chapter 10 (Writer Engine) // Image Integrity
 */

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        if (empty($filename)) return '/lib/images/site/default-post.jpg';
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        $projectRoot = dirname(__DIR__, 2);

        // Optimized priority search
        $searchPaths = [
            '/public/uploads/posts/' . $filename,
            '/public/uploads/' . $filename,
            '/lib/images/posts/' . $filename,
            '/public/lib/images/posts/' . $filename
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($projectRoot . $path)) {
                return str_replace('/public', '', $path);
            }
        }

        error_log("[ImageHelper] Failed to resolve post image: " . $filename . " (Checked in " . $projectRoot . ")");
        return '/lib/images/site/default-post.jpg';
    }
}

if (!function_exists('user_image')) {
    function user_image(?string $slug, ?string $filename): string {
        if (empty($filename)) return '/lib/images/site/default-avatar.png';
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        $projectRoot = dirname(__DIR__, 2);

        $searchPaths = [
            "/public/uploads/artists/{$slug}/{$filename}",
            "/public/uploads/users/{$slug}/{$filename}",
            "/public/uploads/labels/{$filename}",
            "/public/uploads/stations/{$filename}",
            "/public/uploads/venues/{$filename}",
            "/public/uploads/{$filename}",
            "/lib/images/users/{$filename}",
            "/lib/images/labels/{$filename}",
            "/public/lib/images/users/{$filename}",
            "/public/lib/images/labels/{$filename}"
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($projectRoot . $path)) {
                return str_replace('/public', '', $path);
            }
        }

        return '/lib/images/site/default-avatar.png';
    }
}
