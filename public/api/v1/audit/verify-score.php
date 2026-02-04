<?php

/**
 * NGN Score Verification Endpoint
 * POST /api/v1/audit/verify-score
 * Verifies NGN score calculation from raw data
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Rankings\ScoreVerificationService;
use NGN\Lib\Rankings\NGNScoreAuditService;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

header('Content-Type: application/json');

try {
    // Verify authentication
    $auth = Auth::verify();
    if (!$auth) {
        Response::error('Unauthorized', 401);
        exit;
    }

    $config = Config::getInstance();
    $readConnection = ConnectionFactory::read();

    // Parse request
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? 'verify_period';
    $userId = $auth['user_id'];
    $isAdmin = $auth['role'] === 'admin';

    // Determine whose scores can be verified
    $targetArtistId = null;
    if (isset($input['artist_id'])) {
        $targetArtistId = (int) $input['artist_id'];

        // Check permission: artists can only verify their own, admins can verify anyone
        if (!$isAdmin && $userId != $targetArtistId) {
            // Check if user is label manager
            $stmt = $readConnection->prepare(
                'SELECT id FROM labels WHERE manager_id = ? AND id IN (
                    SELECT label_id FROM artists WHERE id = ?
                ) LIMIT 1'
            );
            $stmt->execute([$userId, $targetArtistId]);
            if (!$stmt->fetch()) {
                Response::error('Forbidden: Cannot verify scores for this artist', 403);
                exit;
            }
        }
    } else if (!$isAdmin) {
        // Non-admin users must specify artist ID
        Response::error('artist_id is required', 400);
        exit;
    }

    $verificationService = new ScoreVerificationService($config);
    $auditService = new NGNScoreAuditService($config);

    $response = null;

    switch ($action) {
        case 'verify_period':
            // Verify scores for a period
            $periodStart = $input['period_start'] ?? date('Y-m-d', strtotime('-7 days'));
            $periodEnd = $input['period_end'] ?? date('Y-m-d');
            $limit = (int) ($input['limit'] ?? 100);

            if (!$targetArtistId && !$isAdmin) {
                Response::error('artist_id required for non-admins', 400);
                exit;
            }

            $result = $verificationService->runBulkVerification($periodStart, $periodEnd, $limit);

            $response = [
                'action' => $action,
                'verification_results' => $result,
                'pass_rate' => $result['total_verified'] > 0 ? round(($result['passed'] / $result['total_verified']) * 100, 2) : 0
            ];
            break;

        case 'verify_score':
            // Verify a specific score
            $historyId = (int) ($input['history_id'] ?? 0);
            if (!$historyId) {
                Response::error('history_id required', 400);
                exit;
            }

            // Check access
            $stmt = $readConnection->prepare('SELECT artist_id FROM ngn_score_history WHERE id = ?');
            $stmt->execute([$historyId]);
            $score = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$score) {
                Response::error('Score not found', 404);
                exit;
            }

            if ($targetArtistId && (int) $score['artist_id'] != $targetArtistId) {
                Response::error('Forbidden', 403);
                exit;
            }

            $result = $verificationService->verifyScore($historyId);
            $response = [
                'action' => $action,
                'verification' => $result
            ];
            break;

        case 'get_history':
            // Get score history for artist
            if (!$targetArtistId) {
                Response::error('artist_id required', 400);
                exit;
            }

            $periodType = $input['period_type'] ?? null;
            $limit = (int) ($input['limit'] ?? 50);

            $history = $auditService->getScoreHistory($targetArtistId, $periodType, $limit);

            $response = [
                'action' => $action,
                'artist_id' => $targetArtistId,
                'scores' => $history,
                'total' => count($history)
            ];
            break;

        case 'get_integrity_metrics':
            // Get integrity metrics for artist
            if (!$targetArtistId) {
                Response::error('artist_id required', 400);
                exit;
            }

            $periodStart = $input['period_start'] ?? date('Y-m-d', strtotime('-90 days'));
            $periodEnd = $input['period_end'] ?? date('Y-m-d');

            $metrics = $auditService->calculateIntegrityMetrics($targetArtistId, $periodStart, $periodEnd);

            $response = [
                'action' => $action,
                'artist_id' => $targetArtistId,
                'metrics' => $metrics
            ];
            break;

        case 'file_dispute':
            // File a score dispute
            if (!$targetArtistId) {
                Response::error('artist_id required', 400);
                exit;
            }

            $historyId = (int) ($input['history_id'] ?? 0);
            $disputeType = $input['dispute_type'] ?? 'calculation_error';
            $description = $input['description'] ?? '';
            $allegedImpact = isset($input['alleged_impact']) ? (float) $input['alleged_impact'] : null;

            if (!$historyId || !$description) {
                Response::error('history_id and description required', 400);
                exit;
            }

            $disputeId = $auditService->createDispute(
                $targetArtistId,
                $historyId,
                $disputeType,
                $description,
                $allegedImpact
            );

            $response = [
                'action' => $action,
                'dispute_id' => $disputeId,
                'status' => 'open'
            ];
            break;

        case 'get_disputes':
            // Get disputes for artist
            if (!$targetArtistId) {
                Response::error('artist_id required', 400);
                exit;
            }

            $status = $input['status'] ?? null;
            $disputes = $auditService->getDisputes($targetArtistId, $status);

            $response = [
                'action' => $action,
                'artist_id' => $targetArtistId,
                'disputes' => $disputes,
                'total' => count($disputes)
            ];
            break;

        default:
            Response::error('Invalid action', 400);
            exit;
    }

    Response::success($response);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
