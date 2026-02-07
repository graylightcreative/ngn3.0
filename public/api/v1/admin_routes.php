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

        Response::json([
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
        // Use the correct static Response::json method
        Response::json(['error' => 'Upgrade checks failed: ' . $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/upgrade/migrate
$router->post('/admin/upgrade/migrate', function (Request $request) use ($config) {
    try {
        $migrator = new Migrator($config);
        $applied = $migrator->applyPending();
        Response::json(['data' => ['applied' => $applied]]);
    } catch (Exception $e) {
        error_log('Migration failed: ' . $e->getMessage());
        Response::json(['error' => 'Migration failed: ' . $e->getMessage()], 500);
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
    // In a real app, this would update a persistent config.
    // For now, we just acknowledge the request.
    $body = json_decode($request->body(), true);
    $enabled = $body['enabled'] ?? false;
    return new JsonResponse(['success' => true, 'data' => ['enabled' => $enabled]]);
});

// Placeholder for /api/v1/admin/progress
$router->get('/admin/progress', function (Request $request) use ($config) {
    // Return a simplified version of progress.json for the UI to consume
    return new JsonResponse(['success' => true, 'data' => ['milestones' => []]]);
});

// ===== SMR PIPELINE ROUTES =====
// GET /api/v1/admin/smr/pending - Get pending SMR ingestions
$router->get('/admin/smr/pending', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        $pending = $smrService->getPending();

        Response::json([
            'success' => true,
            'data' => $pending
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/smr/upload - Upload and parse SMR file
$router->post('/admin/smr/upload', function (Request $request) use ($config) {
    try {
        // Check for file upload
        if (empty($_FILES['file'])) {
            return Response::json(['error' => 'No file provided'], 400);
        }

        $file = $_FILES['file'];
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        // Validate file
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            return Response::json(['error' => 'Invalid file format. Use CSV or Excel.'], 400);
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            return Response::json(['error' => 'File too large (max 50MB)'], 400);
        }

        // Store file
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/smr/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileHash = hash_file('sha256', $file['tmp_name']);
        $storageName = date('Y-m-d_His') . '_' . pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $ext;
        $filePath = $uploadDir . $storageName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return Response::json(['error' => 'Failed to store file'], 500);
        }

        // Create ingestion record
        $userId = $request->header('X-User-Id', 1);
        $ingestionId = $smrService->storeUpload(
            $file['name'],
            $filePath,
            $fileHash,
            $file['size'],
            (int)$userId
        );

        // Parse file
        $records = $smrService->parseFile($filePath);
        $smrService->storeRecords($ingestionId, $records);

        Response::json([
            'success' => true,
            'data' => [
                'ingestion_id' => $ingestionId,
                'filename' => $file['name'],
                'records_parsed' => count($records)
            ]
        ], 201);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/smr/{id}/unmatched - Get unmatched artists
$router->get('/admin/smr/(\d+)/unmatched', function (Request $request, $ingestionId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        $unmatched = $smrService->getUnmatchedArtists((int)$ingestionId);

        Response::json([
            'success' => true,
            'data' => $unmatched
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/smr/map-identity - Map artist identity
$router->post('/admin/smr/map-identity', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);

        if (empty($body['ingestion_id']) || empty($body['unmatched']) || empty($body['cdm_artist_id'])) {
            return Response::json(['error' => 'Missing required fields'], 400);
        }

        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        $smrService->mapArtistIdentity(
            (int)$body['ingestion_id'],
            $body['unmatched'],
            (int)$body['cdm_artist_id']
        );

        Response::json([
            'success' => true,
            'message' => 'Artist identity mapped'
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/smr/{id}/review - Get records for review
$router->get('/admin/smr/(\d+)/review', function (Request $request, $ingestionId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        $records = $smrService->getReviewRecords((int)$ingestionId);

        Response::json([
            'success' => true,
            'data' => $records
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/smr/{id}/finalize - Finalize ingestion
$router->post('/admin/smr/(\d+)/finalize', function (Request $request, $ingestionId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $smrService = new \NGN\Lib\Services\SMRService($pdo);

        $result = $smrService->finalize((int)$ingestionId);

        Response::json([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ===== RIGHTS LEDGER ROUTES =====
// GET /api/v1/admin/rights-ledger - Get rights registry
$router->get('/admin/rights-ledger', function (Request $request) use ($config) {
    try {
        $status = $request->query('status');
        $limit = (int)($request->query('limit') ?? 50);
        $offset = (int)($request->query('offset') ?? 0);

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        $registry = $service->getRegistry($status, $limit, $offset);
        $summary = $service->getSummary();

        Response::json([
            'success' => true,
            'data' => [
                'registry' => $registry,
                'summary' => $summary
            ]
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/rights-ledger/disputes - Get disputes
$router->get('/admin/rights-ledger/disputes', function (Request $request) use ($config) {
    try {
        $status = $request->query('status');
        $limit = (int)($request->query('limit') ?? 50);

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        $disputes = $service->getDisputes($status, $limit);

        Response::json([
            'success' => true,
            'data' => $disputes
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/rights-ledger/{id} - Get single right with splits
$router->get('/admin/rights-ledger/(\d+)$', function (Request $request, $rightId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        // Get the right
        $stmt = $pdo->prepare("
            SELECT r.*, a.name as artist_name
            FROM cdm_rights_ledger r
            LEFT JOIN artists a ON r.artist_id = a.id
            WHERE r.id = ?
        ");
        $stmt->execute([(int)$rightId]);
        $right = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$right) {
            return Response::json(['error' => 'Not found'], 404);
        }

        // Get splits
        $splits = $service->getSplits((int)$rightId);

        Response::json([
            'success' => true,
            'data' => [
                'right' => $right,
                'splits' => $splits
            ]
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ===== ROYALTY ROUTES =====

// GET /api/v1/admin/royalties/pending-payouts - Get pending payouts
$router->get('/admin/royalties/pending-payouts', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);

        $pending = $service->getPendingPayouts();

        Response::json([
            'success' => true,
            'data' => $pending
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/royalties/process-payout/{id} - Process a payout request
$router->post('/admin/royalties/process-payout/(\d+)', function (Request $request, $payoutId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);

        $result = $service->processPayoutRequest((int)$payoutId);

        Response::json([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/royalties/balance/{userId} - Get user balance
$router->get('/admin/royalties/balance/(\d+)', function (Request $request, $userId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);

        $balance = $service->getBalance((int)$userId);

        Response::json([
            'success' => true,
            'data' => $balance
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/royalties/transactions - Get all transactions (paginated)
$router->get('/admin/royalties/transactions', function (Request $request) use ($config) {
    try {
        $userId = $request->query('user_id');
        $limit = (int)($request->query('limit') ?? 50);
        $offset = (int)($request->query('offset') ?? 0);

        if (!$userId) {
            return Response::json(['error' => 'user_id is required'], 400);
        }

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);

        $transactions = $service->getTransactions((int)$userId, $limit, $offset);

        Response::json([
            'success' => true,
            'data' => $transactions
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/royalties/create-payout - Create a manual payout request
$router->post('/admin/royalties/create-payout', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $userId = $body['user_id'] ?? null;
        $amount = $body['amount'] ?? null;

        if (!$userId || !$amount) {
            return Response::json(['error' => 'user_id and amount are required'], 400);
        }

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RoyaltyService($pdo);

        $payoutId = $service->createPayout((int)$userId, (float)$amount);

        Response::json([
            'success' => true,
            'data' => ['payout_id' => $payoutId]
        ], 201);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
});

// ===== CHART QA ROUTES =====

// GET /api/v1/admin/charts/qa-status - Get QA gate statuses
$router->get('/admin/charts/qa-status', function (Request $request) use ($config) {
    try {
        $ingestionId = $request->query('ingestion_id');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);

        $status = $service->getQAStatus($ingestionId ? (int)$ingestionId : null);

        Response::json([
            'success' => true,
            'data' => $status
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/charts/corrections - Get manual corrections
$router->get('/admin/charts/corrections', function (Request $request) use ($config) {
    try {
        $limit = (int)($request->query('limit') ?? 50);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);

        $corrections = $service->getCorrections($limit);

        Response::json([
            'success' => true,
            'data' => $corrections
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/charts/corrections - Apply a correction
$router->post('/admin/charts/corrections', function (Request $request) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $adminId = (int)$request->header('X-User-Id', 1);

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);

        $correctionId = $service->applyCorrection(
            (int)$body['artist_id'],
            (float)$body['original_score'],
            (float)$body['corrected_score'],
            $body['reason'] ?? '',
            $adminId,
            isset($body['ingestion_id']) ? (int)$body['ingestion_id'] : null
        );

        Response::json([
            'success' => true,
            'data' => ['correction_id' => $correctionId]
        ], 201);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/charts/disputes - Get score disputes
$router->get('/admin/charts/disputes', function (Request $request) use ($config) {
    try {
        $status = $request->query('status');
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);

        $disputes = $service->getDisputes($status);

        Response::json([
            'success' => true,
            'data' => $disputes
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/charts/disputes/{id}/resolve - Resolve a dispute
$router->post('/admin/charts/disputes/(\d+)/resolve', function (Request $request, $disputeId) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $resolution = $body['resolution'] ?? '';
        $status = $body['status'] ?? 'resolved';

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\ChartQAService($pdo);

        $service->resolveDispute((int)$disputeId, $resolution, $status);

        Response::json([
            'success' => true,
            'message' => 'Dispute resolved'
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ===== ENTITY MANAGEMENT ROUTES =====

// GET /api/v1/admin/entities/{type} - Get list
$router->get('/admin/entities/([a-z]+)', function (Request $request, $type) use ($config) {
    try {
        $limit = (int)($request->query('limit') ?? 50);
        $offset = (int)($request->query('offset') ?? 0);
        $search = $request->query('search');

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);

        $result = $service->getList($type, $limit, $offset, $search);

        Response::json([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/entities/{type}/{id} - Get single
$router->get('/admin/entities/([a-z]+)/(\d+)', function (Request $request, $type, $id) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);

        $item = $service->get($type, (int)$id);

        if (!$item) {
            return Response::json(['error' => 'Not found'], 404);
        }

        Response::json([
            'success' => true,
            'data' => $item
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// PUT /api/v1/admin/entities/{type}/{id} - Update
$router->put('/admin/entities/([a-z]+)/(\d+)', function (Request $request, $type, $id) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\EntityService($pdo);

        $success = $service->update($type, (int)$id, $body);

        Response::json([
            'success' => $success,
            'message' => $success ? 'Updated successfully' : 'No changes made or invalid fields'
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ===== SYSTEM ROUTES =====

// GET /api/v1/admin/system/health
$router->get('/admin/system/health', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\SystemHealthService($pdo);

        Response::json([
            'success' => true,
            'data' => $service->getHealthStatus()
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ===== ANALYTICS ROUTES =====

// GET /api/v1/admin/analytics/summary
$router->get('/admin/analytics/summary', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\AnalyticsService($pdo);

        Response::json([
            'success' => true,
            'data' => $service->getSummary()
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/v1/admin/analytics/trends
$router->get('/admin/analytics/trends', function (Request $request) use ($config) {
    try {
        $days = (int)($request->query('days') ?? 30);
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\AnalyticsService($pdo);

        Response::json([
            'success' => true,
            'data' => [
                'revenue' => $service->getRevenueTrends($days),
                'engagement' => $service->getEngagementTrends($days)
            ]
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// PUT /api/v1/admin/rights-ledger/{id}/status - Update status
$router->put('/admin/rights-ledger/(\d+)/status', function (Request $request, $rightId) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $newStatus = $body['status'] ?? null;

        if (!in_array($newStatus, ['pending', 'verified', 'disputed', 'rejected'])) {
            return Response::json(['error' => 'Invalid status'], 400);
        }

        $pdo = $config->getDatabase();

        if ($newStatus === 'verified') {
            $service = new \NGN\Lib\Services\RightsLedgerService($pdo);
            $service->verify((int)$rightId);
        } else {
            $stmt = $pdo->prepare("UPDATE cdm_rights_ledger SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, (int)$rightId]);
        }

        Response::json([
            'success' => true,
            'message' => 'Status updated'
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/rights-ledger/{id}/resolve-dispute - Resolve dispute
$router->post('/admin/rights-ledger/(\d+)/resolve-dispute', function (Request $request, $rightId) use ($config) {
    try {
        $body = json_decode($request->body(), true);
        $resolution = $body['resolution'] ?? '';
        $finalStatus = $body['final_status'] ?? 'verified';

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        // Get dispute
        $stmt = $pdo->prepare("SELECT id FROM cdm_rights_disputes WHERE right_id = ? AND status = 'open' LIMIT 1");
        $stmt->execute([(int)$rightId]);
        $dispute = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dispute) {
            return Response::json(['error' => 'No open dispute found'], 404);
        }

        $service->resolveDispute($dispute['id'], $resolution, $finalStatus);

        Response::json([
            'success' => true,
            'message' => 'Dispute resolved'
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// POST /api/v1/admin/rights-ledger/{id}/splits - Add split
$router->post('/admin/rights-ledger/(\d+)/splits', function (Request $request, $rightId) use ($config) {
    try {
        $body = json_decode($request->body(), true);

        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        $splitId = $service->addSplit(
            (int)$rightId,
            (int)($body['contributor_id'] ?? 0),
            (float)($body['percentage'] ?? 0),
            $body['role'] ?? null
        );

        Response::json([
            'success' => true,
            'data' => ['split_id' => $splitId]
        ], 201);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
});

// GET /api/v1/admin/rights-ledger/{id}/certificate - Generate certificate
$router->get('/admin/rights-ledger/(\d+)/certificate', function (Request $request, $rightId) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\RightsLedgerService($pdo);

        $certificate = $service->generateCertificate((int)$rightId);

        Response::json([
            'success' => true,
            'data' => $certificate
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

