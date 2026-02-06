<?php
/**
 * API: Sign Agreement
 * 
 * Securely records a user's digital signature for an agreement.
 */

require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$templateSlug = $_POST['template_slug'] ?? '';
$redirectUrl = $_POST['redirect_url'] ?? '/';
$confirm = $_POST['confirm_agreement'] ?? '';

if (empty($templateSlug) || $confirm !== 'on') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'error' => 'Missing agreement or confirmation']);
    exit;
}

try {
    $config = new Config();
    $db = ConnectionFactory::write($config);
    $service = new AgreementService($db);

    $userId = (int)$_SESSION['user_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $result = $service->signAgreement($userId, $templateSlug, $ipAddress, $userAgent);

    // Redirect back or return JSON
    if (isset($_POST['redirect_url'])) {
        header("Location: {$redirectUrl}?signed=" . $result['signature_id']);
    } else {
        echo json_encode(['success' => true, 'signature' => $result]);
    }

} catch (\Exception $e) {
    error_log("Agreement signing error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => 'Failed to record signature']);
}
