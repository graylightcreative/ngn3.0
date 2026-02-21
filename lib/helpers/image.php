<?php
/**
 * Image Resolution Helper
 * Bible Ref: Chapter 10 (Writer Engine) // Image Integrity
 */

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        if (empty($filename)) return '/lib/images/site/default-post.jpg';
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        // Optimized priority search
        $searchPaths = [
            '/uploads/posts/' . $filename,
            '/uploads/' . $filename,
            '/lib/images/posts/' . $filename
        ];

        foreach ($searchPaths as $path) {
            if (file_exists(dirname(__DIR__, 2) . '/public' . $path)) {
                return $path;
            }
        }

        return '/lib/images/site/default-post.jpg';
    }
}

if (!function_exists('user_image')) {
    function user_image(?string $slug, ?string $filename): string {
        if (empty($filename)) return '/lib/images/site/default-avatar.png';
        if (str_starts_with($filename, 'http') || str_starts_with($filename, '/')) return $filename;

        $searchPaths = [
            "/uploads/artists/{$slug}/{$filename}",
            "/uploads/users/{$slug}/{$filename}",
            "/uploads/labels/{$filename}",
            "/uploads/stations/{$filename}",
            "/uploads/venues/{$filename}",
            "/uploads/{$filename}"
        ];

        foreach ($searchPaths as $path) {
            if (file_exists(dirname(__DIR__, 2) . '/public' . $path)) {
                return $path;
            }
        }

        return '/lib/images/site/default-avatar.png';
    }
}
