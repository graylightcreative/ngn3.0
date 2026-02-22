<?php
/**
 * NextGen Noise - Subdomain Bootstrap File
 *
 * This file should be deployed as index.php to subdomain public directories
 * to enable unified Fleet architecture across all NGN domains.
 *
 * Example deployment:
 *   cp public/bootstrap-subdomain.php /www/wwwroot/beta.nextgennoise.com/public/index.php
 *
 * Reference: MASTERS-GUIDE.md - "Graylight Unified Multi-Domain Platform"
 */

// Bootstrap to main NextGen Noise application
// Allows subdomains to inherit core routing, services, and Fleet integration
// Correct Path: ../../nextgennoise/public/index.php
$mainRoot = dirname(__DIR__, 2) . '/nextgennoise/public/index.php';
if (!file_exists($mainRoot)) {
    // Fallback if the folder structure is different on some nodes
    $mainRoot = dirname(__DIR__, 2) . '/index.php';
}
require $mainRoot;
