<?php
/**
 * Image Resolution Helper - High Integrity
 * Bible Ref: Chapter 10 (Writer Engine) // Image Integrity
 */

if (!function_exists('post_image')) {
    function post_image(?string $filename): string {
        $default = '/lib/images/site/2026/NGN-Emblem-Light.png';
        if (empty($filename)) return $default;
        if (str_starts_with($filename, 'http')) return $filename;

        // Strip redundant prefix if DB has 'posts/filename.jpg'
        $cleanName = str_replace('posts/', '', $filename);
        $cleanName = ltrim($cleanName, '/');

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
        if (str_starts_with($filename, 'http')) return $filename;

        $projectRoot = dirname(__DIR__, 2);

        // NGN LOGIC: Many DB paths are hardcoded to /uploads/users/
        // but files reside in /lib/images/users/ due to compartmentalization.
        $cleanName = str_replace(['/uploads/users/', '/uploads/'], '', $filename);
        $cleanName = ltrim($cleanName, '/');

        $candidates = [
            "/lib/images/users/{$slug}/" . basename($cleanName),
            "/lib/images/users/" . $cleanName,
            "/lib/images/users/" . basename($cleanName),
            "/uploads/artists/{$slug}/" . basename($cleanName),
            "/uploads/users/{$slug}/" . basename($cleanName),
            "/lib/images/labels/" . basename($cleanName),
            "/lib/images/stations/" . basename($cleanName),
            "/lib/images/venues/" . basename($cleanName)
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
        if (str_starts_with($filename, 'http')) return $filename;

        $cleanName = ltrim($filename, '/');
        $projectRoot = dirname(__DIR__, 2);

        $candidates = [
            '/uploads/releases/' . basename($cleanName),
            '/lib/images/releases/' . basename($cleanName),
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
