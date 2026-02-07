<?php
/**
 * Writer Engine - Editorial Queue
 * Two-column interface for editorial team to claim, review, and approve articles
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

use NGN\Lib\Config;
use NGN\Lib\Writer\ArticleService;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$config = new Config();

try {
    $articleService = new ArticleService($config);
    $pdo = ConnectionFactory::read($config);
} catch (\Throwable $e) {
    die('Failed to initialize services');
}

$pageTitle = 'Writer Engine - Editorial Queue';
$currentPage = 'writer_editorial_queue';
$editorId = $_SESSION['admin_user_id'] ?? 0;

// Handle actions
$message = $messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $articleId = (int)($_POST['article_id'] ?? 0);

    try {
        if ($action === 'claim') {
            $success = $articleService->claimArticle($articleId, $editorId);
            $message = $success ? 'Article claimed' : 'Failed to claim article';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'approve') {
            $success = $articleService->approveArticle($articleId, $editorId);
            $message = $success ? 'Article approved' : 'Failed to approve article';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'reject') {
            $reason = $_POST['reason'] ?? 'Rejected by editor';
            $success = $articleService->rejectArticle($articleId, $editorId, $reason);
            $message = $success ? 'Article rejected' : 'Failed to reject article';
            $messageType = $success ? 'success' : 'error';
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

// Get stats
$stats = $articleService->getStats();

// Get queues
$unclaimedQueue = $articleService->getEditorialQueue(0, 20);
$myWorkspace = $articleService->getEditorWorkspace($editorId, 0, 20);

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
        .queue-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .article-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #007bff; }
        .article-card.flagged { border-left-color: #fd7e14; }
        .article-card.rejected { border-left-color: #dc3545; }
        .safety-badge { font-size: 0.85rem; padding: 4px 8px; border-radius: 4px; }
        .stats-card { background: white; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 15px; }
        .stats-card .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .stats-card .stat-label { color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📝 Writer Engine - Editorial Queue</h1>
            <a href="/admin" class="btn btn-secondary">Back to Admin</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php echo $stats['pending_claimed'] ?? 0; ?></div>
                    <div class="stat-label">Unclaimed Drafts</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php echo $stats['published_today'] ?? 0; ?></div>
                    <div class="stat-label">Published Today</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php echo $stats['rejected_today'] ?? 0; ?></div>
                    <div class="stat-label">Rejected Today</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php echo $stats['flagged_for_safety'] ?? 0; ?></div>
                    <div class="stat-label">Safety Flagged</div>
                </div>
            </div>
        </div>

        <!-- Two-Column Queue -->
        <div class="queue-container">
            <!-- Unclaimed Drafts -->
            <div>
                <h3>📥 Unclaimed Drafts</h3>
                <?php foreach ($unclaimedQueue as $article): ?>
                    <div class="article-card <?php echo $article['safety_status'] === 'flagged' ? 'flagged' : ''; ?>">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5><?php echo htmlspecialchars($article['title']); ?></h5>
                                <p class="text-muted mb-1" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($article['persona_name'] . ' (' . $article['persona_specialty'] . ')'); ?>
                                </p>
                                <p class="text-muted mb-1" style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($article['artist_name']); ?> - <?php echo $article['severity']; ?>
                                </p>
                                <small class="text-muted">Created: <?php echo date('M d, H:i', strtotime($article['created_at'])); ?></small>
                            </div>
                            <div>
                                <?php if ($article['safety_status'] === 'flagged'): ?>
                                    <span class="safety-badge" style="background: #fd7e14; color: white;">
                                        Safety: <?php echo number_format($article['safety_score'], 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="safety-badge" style="background: #28a745; color: white;">Clean</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="/admin/writer/edit-article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-primary">Preview</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="claim">
                                <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">Claim</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($unclaimedQueue)): ?>
                    <div class="alert alert-info">No unclaimed articles in queue</div>
                <?php endif; ?>
            </div>

            <!-- My Workspace -->
            <div>
                <h3>👤 My Workspace</h3>
                <?php foreach ($myWorkspace as $article): ?>
                    <div class="article-card">
                        <div>
                            <h5><?php echo htmlspecialchars($article['title']); ?></h5>
                            <p class="text-muted mb-1" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($article['persona_name']); ?>
                            </p>
                            <small class="text-muted">
                                Status: <strong><?php echo ucfirst($article['status']); ?></strong>
                                | Comments: <?php echo $article['comment_count']; ?>
                            </small>
                        </div>
                        <div class="mt-2">
                            <a href="/admin/writer/edit-article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <?php if ($article['status'] === 'pending_review'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <input type="hidden" name="reason" value="Editorial decision">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this article?')">Reject</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($myWorkspace)): ?>
                    <div class="alert alert-info">No articles in your workspace</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
