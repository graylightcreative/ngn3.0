<?php
/**
 * Content Reporting API
 *
 * Endpoint: POST /api/v1/content/report
 *
 * Allows users to report content for admin review.
 */

$root = dirname(__DIR__, 3);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Editorial\ContentReportingService;
use NGN\Lib\Auth\TokenService;

$request = Request::capture();
$pdo = NGN\Lib\DB\ConnectionFactory::write($config);
$tokenSvc = new TokenService($config);

// 1. Authentication Check
$user = getCurrentUser($tokenSvc, $request);
if (!$user) {
    (new JsonResponse(['success' => false, 'message' => 'Authentication required'], 401))->send();
    exit;
}

if ($request->method() !== 'POST') {
    (new JsonResponse(['success' => false, 'message' => 'Method not allowed'], 405))->send();
    exit;
}

$data = $request->json();
$entityType = $data['entity_type'] ?? '';
$entityId = (int)($data['entity_id'] ?? 0);
$reason = $data['reason'] ?? '';
$details = $data['details'] ?? null;

if (!$entityType || !$entityId || !$reason) {
    (new JsonResponse(['success' => false, 'message' => 'Missing required fields'], 400))->send();
    exit;
}

try {
    $service = new ContentReportingService($pdo);
    $reportId = $service->submitReport($user['id'], $entityType, $entityId, $reason, $details);

    (new JsonResponse([
        'success' => true,
        'message' => 'Content reported successfully. Our team will review it shortly.',
        'data' => ['report_id' => $reportId]
    ], 201))->send();
} catch (Exception $e) {
    (new JsonResponse([
        'success' => false,
        'message' => 'Error submitting report: ' . $e->getMessage()
    ], 500))->send();
}
