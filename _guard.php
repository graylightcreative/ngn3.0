<?php
// Global auto-prepend guard shim
// Some environments set `auto_prepend_file = _guard.php`.
// Ensure our bootstrap runs early for all requests.

// Define the absolute path to the project root.
// This hardcoding is a workaround for potential issues with auto_prepend_file
// and incorrect include_path configurations. It assumes _guard.php is in the project root.
$projectRoot = __DIR__;

// Add the project root to the PHP include path.
// This ensures that relative includes like 'lib/bootstrap.php' will work correctly
// once _guard.php is successfully loaded.
set_include_path($projectRoot . PATH_SEPARATOR . get_include_path());

// Avoid double-loading
if (!defined('NGN_GUARD_BOOTSTRAPPED')) {
    define('NGN_GUARD_BOOTSTRAPPED', true);
    // Use the project root for bootstrap.php
    $root = $projectRoot;
    // Require main bootstrap (handles env loading and maintenance allowlist)
    // This should now be found because $projectRoot is in the include_path
    require_once 'lib/bootstrap.php'; // Relying on include_path
}
