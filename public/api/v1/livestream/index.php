<?php
/**
 * NGN Venue Livestream API v1
 * Orchestrates PPV ingestion, playback, and live chat.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Media\LiveStreamService;
use NGN\Lib\Services\Social\LiveChatService;

header('Content-Type: application/json');

// Check authentication
@session_start();
$userId = $_SESSION['user_id'] ?? 0;

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$config = new Config();
$streamService = new LiveStreamService($config);
$chatService = new LiveChatService($config);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'status';
$eventId = $_GET['event_id'] ?? '';

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid event_id required']);
    exit;
}

try {
    switch ($action) {
        case 'ingest':
            // Only accessible via Authorized Venue/Admin (simplified here)
            echo json_encode([
                'status' => 'success',
                'ingest_url' => $streamService->getIngestUrl($eventId),
                'stream_key' => $streamService->getStreamKey($eventId)
            ]);
            break;

        case 'playback':
            $details = $streamService->getPlaybackDetails($eventId, $userId);
            echo json_encode([
                'status' => 'success',
                'data' => $details
            ]);
            break;

        case 'chat':
            if ($method === 'GET') {
                $messages = $chatService->getMessages($eventId);
                echo json_encode(['status' => 'success', 'messages' => $messages]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $msg = $input['message'] ?? '';
                if (!$msg) throw new \Exception("Message cannot be empty");
                
                $chatService->sendMessage($eventId, $userId, $msg);
                echo json_encode(['status' => 'success', 'message' => 'Sent']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
