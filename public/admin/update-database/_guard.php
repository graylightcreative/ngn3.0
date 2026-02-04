<?php
// Directory-scoped auto-prepend shim for admin/update-database
if (!defined('NGN_GUARD_BOOTSTRAPPED')) {
    define('NGN_GUARD_BOOTSTRAPPED', true);
    $bootstrap = dirname(__DIR__, 2) . '/lib/bootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }
}
