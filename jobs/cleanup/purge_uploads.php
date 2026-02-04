<?php
// Dev placeholder: purge uploaded files older than UPLOAD_RETENTION_DAYS.
// Usage (CLI): php jobs/cleanup/purge_uploads.php

use NGN\Lib\Env;
use NGN\Lib\Config;

require_once __DIR__.'/../../../lib/bootstrap.php';

$root = realpath(__DIR__.'/../../..');
if (!class_exists(Env::class)) { ngn_autoload_diagnostics($root, true); exit(3); }
Env::load($root);
$config = new Config();

$dir = rtrim($config->uploadDir(), '/');
$retentionDays = $config->uploadRetentionDays();
$cutoff = time() - ($retentionDays * 86400);

if (!is_dir($dir)) {
    fwrite(STDOUT, "Upload dir not found: $dir\n");
    exit(0);
}

$deleted = 0;
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iter as $file) {
    $path = $file->getPathname();
    if (strpos($path, '/ledger/') !== false) {
        // Ledger file cleanup can be handled separately; keep for now
        continue;
    }
    if ($file->isFile()) {
        if ($file->getMTime() < $cutoff) {
            @unlink($path);
            $deleted++;
        }
    } else {
        // Attempt to remove empty directories (previews/dated folders)
        @rmdir($path);
    }
}

fwrite(STDOUT, "Deleted $deleted files older than $retentionDays days from $dir\n");
