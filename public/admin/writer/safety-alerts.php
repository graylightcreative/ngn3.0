<?php
/**
 * Writer Engine - Safety Alerts Dashboard
 * Review flagged articles and manage safety overrides
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

use NGN\Lib\Config;
use NGN\Lib\Writer\ArticleService;
use NGN\Lib\Writer\SafetyFilterService;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$config = new Config();

try {
    $articleService = new ArticleService($config);
    $safetyService = new SafetyFilterService($config);
    $pdo = ConnectionFactory::read($config);
} catch (\Throwable $e) {
    die('Failed to initialize services');
}

$pageTitle = 'Safety Alerts - Writer Engine';
$editorId = $_SESSION['admin_user_id'] ?? 0;

// Fetch flagged articles
$sql = "
    SELECT wa.id, wa.title, wa.content, wa.safety_score, wa.safety_flags,
           wa.created_at, wp.name as persona_name
    FROM writer_articles wa
    LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
    WHERE wa.safety_scan_status IN ('flagged', 'rejected')
    ORDER BY wa.safety_score DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$flaggedArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $articleId = (int)($_POST['article_id'] ?? 0);

    try {
        if ($action === 'override') {
            $success = $safetyService->overrideSafetyFlag($articleId, $editorId, $_POST['reason'] ?? 'Admin override');
            $message = $success ? 'Safety flag overridden' : 'Failed to override';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'confirm_reject') {
            // Keep as rejected
            $message = 'Article confirmed rejected';
            $messageType = 'success';
        }

        if ($success) {
            header("Location: ?");
            exit;
        }
    } catch (\Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .alert-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #fd7e14; }
        .score-badge { font-size: 18px; font-weight: bold; padding: 8px 12px; border-radius: 4px; }
        .score-critical { background: #dc3545; color: white; }
        .score-flagged { background: #fd7e14; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>⚠️ Safety Alerts</h1>
            <a href="/admin/writer/editorial-queue.php" class="btn btn-secondary">Back to Queue</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #dc3545;">
                        <?php echo count(array_filter($flaggedArticles, fn($a) => $a['safety_score'] > 0.3)); ?>
                    </div>
                    <small class="text-muted">Rejected (>0.3)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #fd7e14;">
                        <?php echo count(array_filter($flaggedArticles, fn($a) => $a['safety_score'] <= 0.3 && $a['safety_score'] >= 0.1)); ?>
                    </div>
                    <small class="text-muted">Flagged (0.1-0.3)</small>
                </div>
            </div>
        </div>

        <h3>Flagged Articles</h3>
        <?php foreach ($flaggedArticles as $article): ?>
            <div class="alert-card">
                <div class="d-flex justify-content-between">
                    <div style="flex: 1;">
                        <h5><?php echo htmlspecialchars(substr($article['title'], 0, 80)); ?></h5>
                        <p class="text-muted mb-1" style="font-size: 0.9rem;">
                            <?php echo htmlspecialchars($article['persona_name']); ?> • <?php echo date('M d H:i', strtotime($article['created_at'])); ?>
                        </p>
                        <p style="font-size: 0.85rem; max-height: 60px; overflow: hidden;">
                            <?php echo htmlspecialchars(substr($article['content'], 0, 150)) . '...'; ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div class="score-badge <?php echo $article['safety_score'] > 0.3 ? 'score-critical' : 'score-flagged'; ?>">
                            <?php echo number_format($article['safety_score'], 2); ?>
                        </div>
                    </div>
                </div>

                <?php if ($article['safety_flags']): ?>
                    <details style="margin-top: 10px;">
                        <summary>View flags</summary>
                        <pre style="background: #f8f9fa; padding: 10px; margin-top: 10px; border-radius: 4px;">
<?php echo htmlspecialchars(json_encode(json_decode($article['safety_flags']), JSON_PRETTY_PRINT)); ?>
                        </pre>
                    </details>
                <?php endif; ?>

                <div class="mt-2">
                    <a href="/admin/writer/edit-article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                    <?php if ($article['safety_score'] <= 0.3): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="override">
                            <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                            <input type="hidden" name="reason" value="Approved after review">
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Override safety flag?')">Approve</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
