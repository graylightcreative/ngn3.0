<?php

use NGN\Lib\Http\{Request, Response, JsonResponse};
use NGN\Lib\DB\Migrator;
use NGN\Lib\Config;
use NGN\Lib\Env;

if (!isset($router) || !isset($config)) {
    // This file should be included from api/v1/index.php where $router and $config are set.
    return;
}

// GET /api/v1/admin/upgrade/checks
$router->get('/admin/upgrade/checks', function (Request $request) use ($config) {
    try {
        $migrator = new Migrator($config);
        $status = $migrator->status();
        $available = $migrator->getAvailableCategorized();

        return new JsonResponse([
            'data' => [
                'checks' => [
                    'db_connection' => 'ok',
                    'migrations' => [
                        'applied' => $status['applied'],
                        'pending' => $status['pending'],
                        'available_categorized' => $available
                    ]
                ]
            ]
        ]);
    } catch (Exception $e) {
        return new JsonResponse(['error' => 'Upgrade checks failed: ' . $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/upgrade/migrate
$router->post('/admin/upgrade/migrate', function (Request $request) use ($config) {
    try {
        $migrator = new Migrator($config);
        $applied = $migrator->applyPending();
        return new JsonResponse(['data' => ['applied' => $applied]]);
    } catch (Exception $e) {
        error_log('Migration failed: ' . $e->getMessage());
        return new JsonResponse(['error' => 'Migration failed: ' . $e->getMessage()], 500);
    }
});

// Placeholder for /api/v1/admin/upgrade/backups
$router->post('/admin/upgrade/backups', function (Request $request) use ($config) {
    return new JsonResponse(['success' => true, 'data' => ['message' => 'Backup simulation complete.']]);
});

// Placeholder for /api/v1/admin/upgrade/verify
$router->get('/admin/upgrade/verify', function (Request $request) use ($config) {
    return new JsonResponse(['success' => true, 'data' => ['verify' => ['etl' => ['status' => 'ok']]]]);
});

// Placeholder for /api/v1/admin/maintenance
$router->put('/admin/maintenance', function (Request $request) use ($config) {
    $body = json_decode($request->body(), true);
    $enabled = $body['enabled'] ?? false;
    return new JsonResponse(['success' => true, 'data' => ['enabled' => $enabled]]);
});

// Placeholder for /api/v1/admin/progress
$router->get('/admin/progress', function (Request $request) use ($config) {
    return new JsonResponse(['success' => true, 'data' => ['milestones' => []]]);
});

// ===== SMR PIPELINE ROUTES =====
// GET /api/v1/admin/smr/pending - Get pending SMR ingestions
$router->get('/admin/smr/pending', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $pending = $smrService->getPending();
        return new JsonResponse(['success' => true, 'data' => $pending]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/smr/upload - Upload and parse SMR file
$router->post('/admin/smr/upload', function (Request $request) use ($config) {
    try {
        if (empty($_FILES['file'])) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }
        $file = $_FILES['file'];
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            return new JsonResponse(['error' => 'Invalid file format. Use CSV or Excel.'], 400);
        }
        if ($file['size'] > 50 * 1024 * 1024) {
            return new JsonResponse(['error' => 'File too large (max 50MB)'], 400);
        }
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/smr/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileHash = hash_file('sha256', $file['tmp_name']);
        $storageName = date('Y-m-d_His') . '_' . pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $ext;
        $filePath = $uploadDir . $storageName;
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return new JsonResponse(['error' => 'Failed to store file'], 500);
        }
        $userId = $request->header('X-User-Id', '1'); 
        $ingestionId = $smrService->storeUpload($file['name'], $filePath, $fileHash, $file['size'], (int)$userId);

        // NGN 2.0.2: Register in content ledger (non-blocking)
        try {
            $ledgerService = new \NGN\Lib\Legal\ContentLedgerService($pdo, $config, new \Monolog\Logger('smr_api'));
            $ledgerRecord = $ledgerService->registerContent(
                ownerId: (int)$userId,
                contentHash: $fileHash,
                uploadSource: 'smr_ingestion',
                metadata: [
                    'title' => 'SMR Data Upload: ' . $file['name'],
                    'artist_name' => '',
                    'credits' => null,
                    'rights_split' => null
                ],
                fileInfo: [
                    'size_bytes' => $file['size'],
                    'mime_type' => $ext === 'csv' ? 'text/csv' : 'application/vnd.ms-excel',
                    'filename' => $file['name']
                ],
                sourceRecordId: $ingestionId
            );

            if (!empty($ledgerRecord['certificate_id'])) {
                $updateStmt = $pdo->prepare("UPDATE smr_ingestions SET certificate_id = ? WHERE id = ?");
                $updateStmt->execute([$ledgerRecord['certificate_id'], $ingestionId]);
            }
        } catch (\Throwable $e) {
            error_log('SMR ledger registration failed: ' . $e->getMessage());
        }

        $records = $smrService->parseFile($filePath);
        $smrService->storeRecords($ingestionId, $records);
        return new JsonResponse(['success' => true, 'data' => ['ingestion_id' => $ingestionId, 'filename' => $file['name'], 'records_parsed' => count($records)]], 201);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// GET /api/v1/admin/smr/:id/unmatched - Get unmatched artists
$router->get('/admin/smr/:id/unmatched', function (Request $request) use ($config) {
    try {
        $ingestionId = $request->param('id');
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $unmatched = $smrService->getUnmatchedArtists((int)$ingestionId);
        return new JsonResponse(['success' => true, 'data' => $unmatched]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/smr/map-identity - Map artist identity
$router->post('/admin/smr/map-identity', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        if (empty($body['ingestion_id']) || empty($body['unmatched']) || empty($body['cdm_artist_id'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $smrService->mapArtistIdentity((int)$body['ingestion_id'], $body['unmatched'], (int)$body['cdm_artist_id']);
        return new JsonResponse(['success' => true, 'message' => 'Artist identity mapped']);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// GET /api/v1/admin/smr/:id/review - Get records for review
$router->get('/admin/smr/:id/review', function (Request $request) use ($config) {
    try {
        $ingestionId = $request->param('id');
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $records = $smrService->getReviewRecords((int)$ingestionId);
        return new JsonResponse(['success' => true, 'data' => $records]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/smr/:id/finalize - Finalize ingestion
$router->post('/admin/smr/:id/finalize', function (Request $request) use ($config) {
    try {
        $ingestionId = $request->param('id');
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);
        $result = $smrService->finalize((int)$ingestionId);
        return new JsonResponse(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== CONTENT LEDGER ROUTES (NGN 2.0.3) =====
$router->get('/admin/content-ledger', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = (int)($queryParams['offset'] ?? 0);
        $ownerId = $queryParams['owner_id'] ?? null;
        $source = $queryParams['source'] ?? null;

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Legal\ContentLedgerService($pdo, $config, new \Monolog\Logger('admin_ledger'));
        
        // Since getRegistry doesn't exist in ContentLedgerService yet, we'll query directly or update service
        // For now, let's assume we'll add getList to the service
        $result = $service->getList($limit, $offset, $ownerId ? (int)$ownerId : null, $source);
        
        return new JsonResponse(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/content-ledger/:id', function (Request $request) use ($config) {
    try {
        $id = $request->param('id');
        $pdo = $config->getDatabase();
        $stmt = $pdo->prepare("SELECT * FROM content_ledger WHERE id = ?");
        $stmt->execute([(int)$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) return new JsonResponse(['error' => 'Not found'], 404);
        
        return new JsonResponse(['success' => true, 'data' => $entry]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/content-ledger/anchor', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Legal\BlockchainAnchoringService($pdo, $config, new \Monolog\Logger('admin_anchoring'));
        $result = $service->anchorPendingEntries();
        
        return new JsonResponse($result);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== RIGHTS LEDGER ROUTES =====
$router->get('/admin/rights-ledger', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $status = $queryParams['status'] ?? null;
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = (int)($queryParams['offset'] ?? 0);
        $pdo = $config->getDatabase();

        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $registry = $service->getRegistry($status, $limit, $offset);
        $summary = $service->getSummary();
        return new JsonResponse(['success' => true, 'data' => ['registry' => $registry, 'summary' => $summary]]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/rights-ledger/disputes', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $status = $queryParams['status'] ?? null;
        
        if (is_array($status)) {
            $status = !empty($status) ? (string)reset($status) : null;
        }
        
        $limit = (int)($queryParams['limit'] ?? 50);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $disputes = $service->getDisputes($status, $limit);
        return new JsonResponse(['success' => true, 'data' => $disputes]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/rights-ledger/:id', function (Request $request) use ($config) {
    try {
        $rightId = $request->param('id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $stmt = $pdo->prepare("SELECT r.*, a.name as artist_name FROM cdm_rights_ledger r LEFT JOIN artists a ON r.artist_id = a.id WHERE r.id = ?");
        $stmt->execute([(int)$rightId]);
        $right = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$right) return new JsonResponse(['error' => 'Not found'], 404);
        $splits = $service->getSplits((int)$rightId);
        return new JsonResponse(['success' => true, 'data' => ['right' => $right, 'splits' => $splits]]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->put('/admin/rights-ledger/:id/status', function (Request $request) use ($config) {
    try {
        $rightId = $request->param('id');
        $body = json_decode($request->body(), true);
        $newStatus = $body['status'] ?? null;
        if (!in_array($newStatus, ['pending', 'verified', 'disputed', 'rejected'])) return new JsonResponse(['error' => 'Invalid status'], 400);
        $pdo = $config->getDatabase();
        if ($newStatus === 'verified') {
            $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
            $service->verify((int)$rightId);
        } else {
            $stmt = $pdo->prepare("UPDATE cdm_rights_ledger SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, (int)$rightId]);
        }
        return new JsonResponse(['success' => true, 'message' => 'Status updated']);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/rights-ledger/:id/resolve-dispute', function (Request $request) use ($config) {
    try {
        $rightId = $request->param('id');
        $body = json_decode($request->body(), true);
        $resolution = $body['resolution'] ?? '';
        $finalStatus = $body['final_status'] ?? 'verified';
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $stmt = $pdo->prepare("SELECT id FROM cdm_rights_disputes WHERE right_id = ? AND status = 'open' LIMIT 1");
        $stmt->execute([(int)$rightId]);
        $dispute = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dispute) return new JsonResponse(['error' => 'No open dispute found'], 404);
        $service->resolveDispute($dispute['id'], $resolution, $finalStatus);
        return new JsonResponse(['success' => true, 'message' => 'Dispute resolved']);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/rights-ledger/:id/splits', function (Request $request) use ($config) {
    try {
        $rightId = $request->param('id');
        $body = json_decode($request->body(), true);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $splitId = $service->addSplit((int)$rightId, (int)($body['contributor_id'] ?? 0), (float)($body['percentage'] ?? 0), $body['role'] ?? null);
        return new JsonResponse(['success' => true, 'data' => ['split_id' => $splitId]], 201);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
});

$router->get('/admin/rights-ledger/:id/certificate', function (Request $request) use ($config) {
    try {
        $rightId = $request->param('id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
        $certificate = $service->generateCertificate((int)$rightId);
        return new JsonResponse(['success' => true, 'data' => $certificate]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== ROYALTY ROUTES =====
$router->get('/admin/royalties/pending-payouts', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $pending = $service->getPendingPayouts();
        return new JsonResponse(['success' => true, 'data' => $pending]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/royalties/process-payout/:id', function (Request $request) use ($config) {
    try {
        $payoutId = $request->param('id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $result = $service->processPayoutRequest((int)$payoutId);
        return new JsonResponse(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/royalties/balance/:id', function (Request $request) use ($config) {
    try {
        $userId = $request->param('id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $balance = $service->getBalance((int)$userId);
        return new JsonResponse(['success' => true, 'data' => $balance]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/royalties/transactions', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $userId = $queryParams['user_id'] ?? null;
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = (int)($queryParams['offset'] ?? 0);
        if (!$userId) return new JsonResponse(['error' => 'user_id is required'], 400);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $transactions = $service->getTransactions((int)$userId, $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => $transactions]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/royalties/create-payout', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $userId = $body['user_id'] ?? null;
        $amount = $body['amount'] ?? null;
        if (!$userId || !$amount) return new JsonResponse(['error' => 'user_id and amount are required'], 400);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $payoutId = $service->createPayout((int)$userId, (float)$amount);
        return new JsonResponse(['success' => true, 'data' => ['payout_id' => $payoutId]], 201);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
});

$router->get('/admin/royalties/eqs-calculate', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $periodStart = $queryParams['start_date'] ?? date('Y-m-01');
        $periodEnd = $queryParams['end_date'] ?? date('Y-m-t');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);
        $result = $service->calculateEQS($periodStart, $periodEnd);
        return new JsonResponse(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== CHART QA ROUTES =====
$router->get('/admin/charts/qa-status', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $ingestionId = $queryParams['ingestion_id'] ?? null;
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);
        $status = $service->getQAStatus($ingestionId ? (int)$ingestionId : null);
        return new JsonResponse(['success' => true, 'data' => $status]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/charts/corrections', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $limit = (int)($queryParams['limit'] ?? 50);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);
        $corrections = $service->getCorrections($limit);
        return new JsonResponse(['success' => true, 'data' => $corrections]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/charts/corrections', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);
        $correctionId = $service->applyCorrection((int)$body['artist_id'], (float)$body['original_score'], (float)$body['corrected_score'], $body['reason'] ?? '', 1, isset($body['ingestion_id']) ? (int)$body['ingestion_id'] : null);
        return new JsonResponse(['success' => true, 'data' => ['correction_id' => $correctionId]], 201);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/charts/disputes', function (Request $request) use ($config) {
    try {
        $queryParams = $request->query();
        $status = $queryParams['status'] ?? null;
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);
        $disputes = $service->getDisputes($status);
        return new JsonResponse(['success' => true, 'data' => $disputes]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/admin/charts/disputes/:id/resolve', function (Request $request) use ($config) {
    try {
        $disputeId = $request->param('id');
        $body = json_decode($request->body(), true);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);
        $service->resolveDispute((int)$disputeId, $body['resolution'] ?? '', $body['status'] ?? 'resolved');
        return new JsonResponse(['success' => true, 'message' => 'Dispute resolved']);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== ENTITY MANAGEMENT ROUTES =====
$router->get('/admin/entities/:type', function (Request $request) use ($config) {
    try {
        $type = $request->param('type');
        $queryParams = $request->query();
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = (int)($queryParams['offset'] ?? 0);
        $search = $queryParams['search'] ?? null;
        
        // Ensure search is a string or null
        if (is_array($search)) {
            $search = !empty($search) ? (string)reset($search) : null;
        }

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);

        $result = $service->getList($type, $limit, $offset, $search);
        
        return new JsonResponse(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/entities/:type/:id', function (Request $request) use ($config) {
    try {
        $type = $request->param('type');
        $id = $request->param('id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);
        $item = $service->get($type, (int)$id);
        if (!$item) return new JsonResponse(['error' => 'Not found'], 404);
        return new JsonResponse(['success' => true, 'data' => $item]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->put('/admin/entities/:type/:id', function (Request $request) use ($config) {
    try {
        $type = $request->param('type');
        $id = $request->param('id');
        $body = json_decode($request->body(), true);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);
        $success = $service->update($type, (int)$id, $body);
        return new JsonResponse(['success' => $success, 'message' => $success ? 'Updated successfully' : 'No changes made']);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== SYSTEM ROUTES =====
$router->get('/admin/system/health', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\SystemHealthService($pdo);
        return new JsonResponse(['success' => true, 'data' => $service->getHealthStatus()]);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ===== ANALYTICS ROUTES =====

$router->get('/admin/analytics/summary', function (Request $request) use ($config) {

    try {

        $pdo = $config->getDatabase();

        $service = new \NGN\Lib\Services\AnalyticsService($pdo);

        return new JsonResponse(['success' => true, 'data' => $service->getSummary()]);

    } catch (Exception $e) {

        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);

    }

});



$router->get('/admin/analytics/trends', function (Request $request) use ($config) {

    try {

        $queryParams = $request->query();

        $days = (int)($queryParams['days'] ?? 30);

        $pdo = $config->getDatabase();

        $service = new \NGN\Lib\Services\AnalyticsService($pdo);

        return new JsonResponse(['success' => true, 'data' => ['revenue' => $service->getRevenueTrends($days), 'engagement' => $service->getEngagementTrends($days)]]);

    } catch (Exception $e) {

        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);

    }

});
