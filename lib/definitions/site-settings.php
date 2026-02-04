<?php
namespace NGN\Lib;

use PDO;
use DateTimeZone;
use DateTime;

// Check if Env class is already declared. If so, assume bootstrap has run and skip re-inclusion.
// This guard prevents re-including bootstrap and thus re-declaring Env if it's already loaded.
if (!class_exists('NGN\Lib\Env')) {
    // Load NGN bootstrap (Composer + defensive autoload)
    $docRoot = dirname(__DIR__, 2);
    require_once $docRoot . '/lib/bootstrap.php';
}

// load dotenv
// This line should be safe now, as bootstrap.php should have handled Env class loading and dotenv.
// If bootstrap.php does not load dotenv, we might need to reconsider this.
// For now, we assume bootstrap handles it.
// $dotenv = Dotenv\Dotenv::createImmutable($docRoot);
// $dotenv->load();

// Set default timezone to EST
date_default_timezone_set('America/New_York');


// SHOW ERRORS IF DEV
if (\NGN\Lib\Env::get('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('error_reporting', E_ALL);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED); // Production error reporting
}

// Initialize Config
$config = new Config();

// Create the PDO connection
try {
    $GLOBALS['pdo'] = \NGN\Lib\DB\ConnectionFactory::write($config);

    // Legacy database connection
    // Assuming DB_READ_HOST/USER/PASS are for the main read replica or a specifically named 'read' connection.
    $GLOBALS['ngn_pdo'] = \NGN\Lib\DB\ConnectionFactory::read($config);

    // SMR 2025 database connection
    $GLOBALS['smr_pdo'] = \NGN\Lib\DB\ConnectionFactory::named($config, 'SMR2025');

    // Spins 2025 database connection
    $GLOBALS['spins_pdo'] = \NGN\Lib\DB\ConnectionFactory::named($config, 'SPINS2025');

} catch (PDOException $e) {
    // Log the database connection error and display a user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    // You might want to display a static error page or a simple message instead of crashing
    die("A critical error occurred. Please try again later.");
}


// If SESSION is NOT set, set it
if (!isset($_SESSION)) {
    session_start();
}

// If the ngn session variable is not included, reset session
if (!isset($_SESSION['ngn'])) {
    session_destroy();
    session_start();
    $_SESSION['ngn'] = true;
    $_SESSION['logged_in'] = 0;
}

// User authentication status
$authenticated = false;
$fan = $admin = $moderator = $agent = $reader = $VIP = $label = $writer = $radioStation = false;

if (isset($_SESSION['LoggedIn']) && $_SESSION['LoggedIn'] === 1) {
    $authenticated = true;
    switch ($_SESSION['User']['RoleId']) {
        case 1: $admin = true; break;
        case 2: $moderator = true; break;
        case 3: $fan = true; break;
        case 4: $agent = true; break;
        case 5: $reader = true; break;
        case 6: $VIP = true; break;
        case 7: $label = true; break;
        case 8: $writer = true; break;
        case 9: $radioStation = true; break;
    }
}

// Initialize baseurl from environment
$baseurl = Env::get('BASEURL', 'https://nextgennoise.com/');

// Default Meta and Theme Settings
$d = [];
$d['Title'] = 'NextGen Noise | The Future of Music Discovery';
$d['Author'] = 'NextGen Noise';
$d['Tags'] = 'Music Charts, Artist Discovery, Music Industry, Data-Driven Insights, Emerging Artists, Rock, Metal, Alternative, Indie, Music News, Fan Engagement, NextGen Noise, NGN Charts, Music Technology, Music Community, Artist Empowerment, Music Promotion, Music Trends, Music Reviews, Music Interviews, Music Blogs, Music Podcasts, Music Videos';
$d['Summary'] = "NextGen Noise is revolutionizing the music industry with cutting-edge charts, artist spotlights, and in-depth analysis. Join the movement and discover the future of music.";
$d['Image'] = $baseurl . 'lib/images/default.jpg';
$d['Baseurl'] = $baseurl;
$GLOBALS['Default'] = $d;

// Theme Colors
$primaryColor = $_ENV['THEME_COLOR_PRIMARY'] ?? '#46980a'; // Default green
$GLOBALS['theme']['color'] = $primaryColor;
$GLOBALS['theme']['primary'] = $primaryColor;
$GLOBALS['theme']['secondary'] = $_ENV['THEME_COLOR_SECONDARY'] ?? '#367208';
$GLOBALS['theme']['dark'] = $_ENV['THEME_COLOR_DARK'] ?? '#2A5707';
$GLOBALS['theme']['danger'] = $_ENV['THEME_COLOR_DANGER'] ?? '#ff4949';
$GLOBALS['theme']['warning'] = $_ENV['THEME_COLOR_WARNING'] ?? '#ffcc00';
$GLOBALS['theme']['success'] = $_ENV['THEME_COLOR_SUCCESS'] ?? '#3fa307';
$GLOBALS['theme']['info'] = $_ENV['THEME_COLOR_INFO'] ?? '#4CAF50';
// Revert to original call as the function is defined locally in site-settings.php itself
$GLOBALS['theme']['light'] = !isDarkOutside();
$GLOBALS['theme']['rgb'] = hexToRgb($primaryColor); // Assuming hexToRgb() is a defined function
$GLOBALS['theme']['shades'] = getShades($primaryColor); // Assuming getShades() is a defined function

$track = true; // This variable's purpose is unclear without more context.

function hexToRgb($hex)
{
    // Remove the '#' if present
    $hex = str_replace('#', '', $hex);

    // Ensure the hex code is 6 characters long (add leading zeros if needed)
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    // Extract RGB components
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return array($r, $g, $b); // Return as an array [r, g, b]
}
function getShades($hex)
{
    // Remove the '#' if present
    $hex = str_replace('#', '', $hex);

    // Ensure the hex code is 6 characters long (add leading zeros if needed)
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    // Extract RGB components
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Adjustment factor for the shade (adjust as needed)
    $adjustmentFactor = 20;

    // Calculate darker shade
    $darkerR = max($r - $adjustmentFactor, 0);
    $darkerG = max($g - $adjustmentFactor, 0);
    $darkerB = max($b - $adjustmentFactor, 0);

    // Calculate lighter shade
    $lighterR = min($r + $adjustmentFactor, 255);
    $lighterG = min($g + $adjustmentFactor, 255);
    $lighterB = min($b + $adjustmentFactor, 255);

    // Convert RGB back to hex
    $darkerHex = '#' . sprintf('%02x%02x%02x', $darkerR, $darkerG, $darkerB);
    $lighterHex = '#' . sprintf('%02x%02x%02x', $lighterR, $lighterG, $lighterB);

    return array('darker' => $darkerHex, 'lighter' => $lighterHex);
}
function isDarkOutside() {
    $timezone = new DateTimeZone(date_default_timezone_get());
    $now = new DateTime('now', $timezone);

    $latitude = 39.9612;
    $longitude = -82.9988;

    // Use date_sun_info to get sunrise/sunset times
    $sunInfo = date_sun_info($now->getTimestamp(), $latitude, $longitude);

    $sunrise = clone $now;
    $sunrise->setTimestamp($sunInfo['sunrise']);

    $sunset = clone $now;
    $sunset->setTimestamp($sunInfo['sunset']);

    return ($now < $sunrise || $now > $sunset);
}
?>