<?php
/**
 * Admin v2 Workflow Test Suite
 * Tests SMR Pipeline and Rights Ledger workflows
 *
 * Usage: php setup/test_admin_workflows.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$config = new NGN\Lib\Config();
$pdo = $config->getDatabase();

// Reset test data
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("TRUNCATE smr_ingestions");
$pdo->exec("TRUNCATE smr_records");
$pdo->exec("TRUNCATE cdm_chart_entries");
$pdo->exec("TRUNCATE cdm_rights_ledger");
$pdo->exec("TRUNCATE cdm_rights_splits");
$pdo->exec("TRUNCATE cdm_rights_disputes");
$pdo->exec("TRUNCATE cdm_royalty_transactions");
$pdo->exec("TRUNCATE cdm_payout_requests");
$pdo->exec("TRUNCATE ngn_score_corrections");
$pdo->exec("TRUNCATE ngn_score_disputes");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// Ensure test data exists
$pdo->exec("INSERT IGNORE INTO users (id, email, display_name, role_id, status) VALUES (1, 'admin@example.com', 'Admin User', 1, 'active')");
$pdo->exec("INSERT IGNORE INTO artists (id, slug, name, status) VALUES (1, 'test-artist', 'Test Artist', 'active')");
$pdo->exec("INSERT IGNORE INTO ngn_score_history (id, artist_id, score_value, period_type, period_start, period_end, final_score, calculation_method, formula_used, calculated_at) VALUES (1, 1, 85.5, 'weekly', CURDATE(), CURDATE(), 85.5, 'system', '{}', NOW())");

// Use ID 1 for history_id in service/tests
$historyId = 1;

echo "🧪 Testing Admin v2 Workflows\n";
echo str_repeat("=", 60) . "\n\n";

$passed = 0;
$failed = 0;

// Helper function
function test($name, $callback) {
    global $passed, $failed;
    try {
        $callback();
        echo "✅ $name\n";
        $passed++;
        return true;
    } catch (Exception $e) {
        echo "❌ $name\n   Error: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

// ============================================================================
// PART 1: DATABASE TABLES EXIST
// ============================================================================
echo "📦 DATABASE TABLES\n";
echo str_repeat("-", 60) . "\n";

test("Table: smr_ingestions exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM smr_ingestions");
    $stmt->execute();
});

test("Table: smr_records exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM smr_records");
    $stmt->execute();
});

test("Table: cdm_rights_ledger exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM cdm_rights_ledger");
    $stmt->execute();
});

test("Table: cdm_rights_splits exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM cdm_rights_splits");
    $stmt->execute();
});

test("Table: cdm_rights_disputes exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM cdm_rights_disputes");
    $stmt->execute();
});

// ============================================================================
// PART 2: SMR PIPELINE WORKFLOW
// ============================================================================
echo "\n🎵 SMR PIPELINE\n";
echo str_repeat("-", 60) . "\n";

$smrService = new \NGN\Lib\Services\SMRService($pdo);

// Test 1: Create ingestion
$ingestionId = null;
test("Create SMR ingestion record", function() use ($smrService, &$ingestionId) {
    $ingestionId = $smrService->storeUpload(
        'test_upload.csv',
        '/tmp/test.csv',
        hash('sha256', 'test_' . uniqid()),
        1024,
        1
    );
    if (!$ingestionId || $ingestionId <= 0) {
        throw new Exception("Invalid ingestion ID: $ingestionId");
    }
});

// Test 2: Store sample records
test("Store SMR records", function() use ($smrService, $ingestionId) {
    if (!$ingestionId) throw new Exception("No ingestion ID");

    $records = [
        ['artist_name' => 'The Beatles', 'track_title' => 'Let It Be', 'spin_count' => 150, 'add_count' => 8, 'isrc' => 'GBUM71505208', 'station_id' => 0],
        ['artist_name' => 'Metalica', 'track_title' => 'Enter Sandman', 'spin_count' => 120, 'add_count' => 5, 'isrc' => '', 'station_id' => 0],
        ['artist_name' => 'Pink Floyd', 'track_title' => 'Comfortably Numb', 'spin_count' => 95, 'add_count' => 3, 'isrc' => '', 'station_id' => 0],
    ];

    $smrService->storeRecords($ingestionId, $records);
});

// Test 3: Get unmatched artists
$unmatchedArtists = [];
test("Get unmatched artists", function() use ($smrService, $ingestionId, &$unmatchedArtists) {
    if (!$ingestionId) throw new Exception("No ingestion ID");

    $unmatchedArtists = $smrService->getUnmatchedArtists($ingestionId);
    if (empty($unmatchedArtists)) {
        throw new Exception("No unmatched artists found");
    }
});

// Test 4: Map identity (simulate fixing typo)
test("Map artist identity", function() use ($smrService, $ingestionId) {
    if (!$ingestionId) throw new Exception("No ingestion ID");

    // Assume Metallica has ID 1 in CDM
    $smrService->mapArtistIdentity($ingestionId, 'Metalica', 1);
});

// Test 5: Get review records
test("Get review records", function() use ($smrService, $ingestionId) {
    if (!$ingestionId) throw new Exception("No ingestion ID");

    $records = $smrService->getReviewRecords($ingestionId);
    if (empty($records)) {
        throw new Exception("No records to review");
    }
});

// Test 6: Finalize ingestion
test("Finalize SMR ingestion", function() use ($smrService, $ingestionId) {
    if (!$ingestionId) throw new Exception("No ingestion ID");

    $result = $smrService->finalize($ingestionId);
    if (!$result['success']) {
        throw new Exception("Finalize failed");
    }
});

// ============================================================================
// PART 3: RIGHTS LEDGER WORKFLOW
// ============================================================================
echo "\n⚖️  RIGHTS LEDGER\n";
echo str_repeat("-", 60) . "\n";

$rightsService = new \NGN\Lib\Services\RightsLedgerService($pdo);

// Test 1: Create registration
$rightId = null;
test("Create rights registration", function() use ($rightsService, &$rightId) {
    $rightId = $rightsService->createRegistration(
        artistId: 1,
        trackId: null,
        isrc: 'GBUM71505208',
        ownerId: 1
    );
    if (!$rightId || $rightId <= 0) {
        throw new Exception("Invalid right ID: $rightId");
    }
});

// Test 2: Get registry
test("Get rights registry", function() use ($rightsService) {
    $registry = $rightsService->getRegistry();
    if (empty($registry)) {
        throw new Exception("Registry empty");
    }
});

// Test 3: Get summary
test("Get registry summary", function() use ($rightsService) {
    $summary = $rightsService->getSummary();
    if (!isset($summary['pending'])) {
        throw new Exception("Summary missing pending count");
    }
});

// Test 4: Add split
test("Add ownership split", function() use ($rightsService, $rightId) {
    if (!$rightId) throw new Exception("No right ID");

    $splitId = $rightsService->addSplit(
        rightId: $rightId,
        contributorId: 1,
        percentage: 100,
        role: 'Artist'
    );
    if (!$splitId) {
        throw new Exception("Failed to add split");
    }
});

// Test 5: Get splits
test("Get ownership splits", function() use ($rightsService, $rightId) {
    if (!$rightId) throw new Exception("No right ID");

    $splits = $rightsService->getSplits($rightId);
    if (empty($splits)) {
        throw new Exception("No splits found");
    }
});

// Test 6: Verify ISRC
test("Verify ISRC format", function() use ($rightsService) {
    $valid = $rightsService->verifyISRC('GBUM71505208');
    if (!$valid) {
        throw new Exception("ISRC verification failed");
    }
});

// Test 7: Verify right
test("Verify right registration", function() use ($rightsService, $rightId) {
    if (!$rightId) throw new Exception("No right ID");

    $rightsService->verify($rightId);
});

// Test 8: Mark disputed
test("Mark right as disputed", function() use ($rightsService, $rightId) {
    if (!$rightId) throw new Exception("No right ID");

    $disputeId = $rightsService->markDisputed($rightId, 'Test dispute for validation');
    if (!$disputeId) {
        throw new Exception("Failed to create dispute");
    }
});

// Test 9: Get disputes
test("Get disputes list", function() use ($rightsService) {
    $disputes = $rightsService->getDisputes('open');
    if (empty($disputes)) {
        throw new Exception("No disputes found");
    }
});

// Test 10: Generate certificate
test("Generate Digital Safety Seal", function() use ($rightsService, $rightId) {
    if (!$rightId) throw new Exception("No right ID");

    $cert = $rightsService->generateCertificate($rightId);
    if (empty($cert['certificate_id'])) {
        throw new Exception("Certificate generation failed");
    }
});

// ============================================================================
// PART 4: ROYALTY WORKFLOW
// ============================================================================
echo "\n💰 ROYALTY WORKFLOW\n";
echo str_repeat("-", 60) . "\n";

$royaltyService = new \NGN\Lib\Services\RoyaltyService($pdo);

// Test 1: Add transaction
test("Add royalty transaction", function() use ($royaltyService) {
    $txId = $royaltyService->addTransaction(
        userId: 1,
        amount: 150.75,
        type: 'eqs_distribution',
        periodStart: '2026-01-01',
        periodEnd: '2026-01-31'
    );
    if (!$txId) throw new Exception("Failed to add transaction");
});

// Test 2: Get balance
test("Get user balance", function() use ($royaltyService) {
    $balance = $royaltyService->getBalance(1);
    if ($balance['current_balance'] < 150.75) {
        throw new Exception("Balance mismatch: " . $balance['current_balance']);
    }
});

// Test 3: Create payout request
$payoutId = null;
test("Create payout request", function() use ($royaltyService, &$payoutId) {
    $payoutId = $royaltyService->createPayout(1, 50.00);
    if (!$payoutId) throw new Exception("Failed to create payout");
});

// Test 4: Get pending payouts
test("Get pending payouts", function() use ($royaltyService) {
    $pending = $royaltyService->getPendingPayouts();
    if (empty($pending)) throw new Exception("No pending payouts found");
});

// Test 5: Process payout
test("Process payout request", function() use ($royaltyService, $payoutId) {
    if (!$payoutId) throw new Exception("No payout ID");
    
    // We need to ensure user has a stripe_account_id for this to work
    // In our test DB, we'll just set it
    global $pdo;
    $pdo->exec("UPDATE users SET stripe_account_id = 'acct_test' WHERE id = 1");
    
    $result = $royaltyService->processPayoutRequest($payoutId);
    if (!$result['success']) throw new Exception("Payout processing failed");
});

// Test 6: Verify balance after payout
test("Verify balance after payout", function() use ($royaltyService) {
    $balance = $royaltyService->getBalance(1);
    // 150.75 - 50.00 = 100.75
    if (abs($balance['current_balance'] - 100.75) > 0.01) {
        throw new Exception("Balance after payout mismatch: " . $balance['current_balance']);
    }
});

// ============================================================================
// PART 5: CHART QA WORKFLOW
// ============================================================================
echo "\n📉 CHART QA WORKFLOW\n";
echo str_repeat("-", 60) . "\n";

$qaService = new \NGN\Lib\Services\ChartQAService($pdo);

// Test 1: Get QA Status
test("Get QA Gate statuses", function() use ($qaService) {
    $status = $qaService->getQAStatus();
    if (!isset($status['gates']) || count($status['gates']) !== 4) {
        throw new Exception("Invalid gate status response");
    }
});

// Test 2: Apply correction
$correctionId = null;
test("Apply score correction", function() use ($qaService, &$correctionId) {
    $correctionId = $qaService->applyCorrection(
        artistId: 1,
        originalScore: 85.5,
        correctedScore: 92.0,
        reason: 'Manual audit fix',
        adminId: 1
    );
    if (!$correctionId) throw new Exception("Failed to apply correction");
});

// Test 3: Get corrections
test("Get corrections list", function() use ($qaService) {
    $list = $qaService->getCorrections();
    if (empty($list)) throw new Exception("Corrections list empty");
});

// Test 4: Create dispute
test("Create score dispute", function() use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO ngn_score_disputes (artist_id, history_id, dispute_type, description, status, created_at)
        VALUES (1, 1, 'manual', 'Inaccurate spin count reported', 'open', NOW())
    ");
    $stmt->execute();
    if (!$pdo->lastInsertId()) throw new Exception("Failed to create dispute");
});

// Test 5: Get disputes
$disputeId = null;
test("Get disputes list", function() use ($qaService, &$disputeId) {
    $list = $qaService->getDisputes('open');
    if (empty($list)) throw new Exception("Disputes list empty");
    $disputeId = $list[0]['id'];
});

// Test 6: Resolve dispute
test("Resolve score dispute", function() use ($qaService, $disputeId) {
    if (!$disputeId) throw new Exception("No dispute ID");
    $qaService->resolveDispute($disputeId, 'Verified and fixed', 'resolved');
    
    $list = $qaService->getDisputes('resolved');
    $found = false;
    foreach ($list as $d) {
        if ($d['id'] == $disputeId) $found = true;
    }
    if (!$found) throw new Exception("Dispute not found in resolved list");
});

// ============================================================================
// PART 6: ENTITY MANAGEMENT WORKFLOW
// ============================================================================
echo "\n👥 ENTITY MANAGEMENT WORKFLOW\n";
echo str_repeat("-", 60) . "\n";

$entityService = new \NGN\Lib\Services\EntityService($pdo);

// Test 1: Get Artist List
test("Get Artists List", function() use ($entityService) {
    $result = $entityService->getList('artists');
    if ($result['total'] < 1) throw new Exception("Artist list empty");
    if (!isset($result['items'][0]['name'])) throw new Exception("Invalid list format");
});

// Test 2: Search Artists
test("Search Artists", function() use ($entityService) {
    // Assuming "Test Artist" exists from setup
    $result = $entityService->getList('artists', 10, 0, 'Test');
    if ($result['total'] < 1) throw new Exception("Search returned no results");
    if (strpos($result['items'][0]['name'], 'Test') === false) throw new Exception("Search result mismatch");
});

// Test 3: Get Single Artist
test("Get Single Artist", function() use ($entityService) {
    $artist = $entityService->get('artists', 1);
    if (!$artist) throw new Exception("Artist not found");
    if ($artist['id'] != 1) throw new Exception("ID mismatch");
});

// Test 4: Update Artist Status
test("Update Artist Status", function() use ($entityService) {
    $success = $entityService->update('artists', 1, ['status' => 'inactive']);
    if (!$success) throw new Exception("Update failed");
    
    $artist = $entityService->get('artists', 1);
    if ($artist['status'] !== 'inactive') throw new Exception("Status not updated");
    
    // Revert
    $entityService->update('artists', 1, ['status' => 'active']);
});

// ============================================================================
// PART 7: SYSTEM OPERATIONS WORKFLOW
// ============================================================================
echo "\n⚙️  SYSTEM OPERATIONS WORKFLOW\n";
echo str_repeat("-", 60) . "\n";

$healthService = new \NGN\Lib\Services\SystemHealthService($pdo);

// Test 1: Get Health Status
test("Get System Health", function() use ($healthService) {
    $status = $healthService->getHealthStatus();
    if (!isset($status['database']) || $status['database']['status'] !== 'ok') {
        throw new Exception("Database health check failed");
    }
    if (!isset($status['disk_space'])) throw new Exception("Disk check failed");
});

// ============================================================================
// RESULTS
// ============================================================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 TEST RESULTS\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "📈 Success Rate: " . round(($passed / ($passed + $failed)) * 100, 1) . "%\n";
echo str_repeat("=", 60) . "\n";

if ($failed === 0) {
    echo "\n🎉 ALL TESTS PASSED! Admin v2 is ready to use.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Review errors above.\n";
    exit(1);
}
