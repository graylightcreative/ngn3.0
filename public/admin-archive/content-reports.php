<?php
/**
 * Content Report Management
 *
 * Admin interface for reviewing and resolving user content reports.
 */

$root = dirname(__DIR__, 1);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Editorial\ContentReportingService;
use NGN\Lib\DB\ConnectionFactory;

$pdo = ConnectionFactory::write($config);
$service = new ContentReportingService($pdo);

// Handle Resolution
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $reportId = (int)$_POST['report_id'];
    $resolution = $_POST['resolution']; // 'action_taken' or 'dismissed'
    $notes = $_POST['admin_notes'] ?? '';
    
    // Assume admin ID 1 for now or get from session
    if ($service->resolveReport($reportId, 1, $resolution, $notes)) {
        $message = '<div class="alert alert-success">Report #' . $reportId . ' resolved.</div>';
    } else {
        $message = '<div class="alert alert-danger">Failed to resolve report.</div>';
    }
}

$reports = $service->getPendingReports();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Report Queue | NGN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .report-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border-left: 4px solid #dee2e6; }
        .report-card.status-pending { border-left-color: #ffc107; }
        .report-card.status-reviewing { border-left-color: #0dcaf0; }
        .badge-type { text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1>Content Report Queue</h1>
        <p class="text-muted">Review and resolve items reported for Public Integrity violations.</p>
        
        <?= $message ?>

        <div class="mt-4">
            <?php if (empty($reports)): ?>
                <div class="alert alert-info">No pending reports found. The queue is clear!</div>
            <?php endif; ?>

            <?php foreach ($reports as $r): ?>
            <div class="report-card status-<?= $r['status'] ?> p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-dark badge-type mb-2"><?= $r['entity_type'] ?> #<?= $r['entity_id'] ?></span>
                        <h5 class="mb-1">Reason: <?= ucfirst($r['reason']) ?></h5>
                        <p class="text-muted small">Reported by <strong><?= htmlspecialchars($r['reporter_name']) ?></strong> on <?= date('M j, Y H:i', strtotime($r['created_at'])) ?></p>
                    </div>
                    <span class="badge bg-warning text-dark"><?= strtoupper($r['status']) ?></span>
                </div>
                
                <div class="bg-light p-3 rounded mb-3">
                    <p class="mb-0">"<?= htmlspecialchars($r['details'] ?: 'No additional details provided.') ?>"</p>
                </div>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <div class="col-md-8">
                        <input type="text" name="admin_notes" class="form-control" placeholder="Internal notes (optional)...">
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="submit" name="resolution" value="dismissed" class="btn btn-outline-secondary">Dismiss</button>
                        <button type="submit" name="resolution" value="action_taken" class="btn btn-danger">Take Action</button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
