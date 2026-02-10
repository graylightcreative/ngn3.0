<?php
/**
 * Writer Engine - Article Editor
 * Full article editor with markdown support and approval workflow
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

$articleId = (int)($_GET['id'] ?? 0);
$editorId = $_SESSION['admin_user_id'] ?? 0;
$message = $messageType = '';

// Load article
$article = $articleService->getArticleForEdit($articleId);
if (!$article) {
    die('Article not found');
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'save_draft') {
            $changes = [
                'title' => $_POST['title'] ?? null,
                'excerpt' => $_POST['excerpt'] ?? null,
                'content' => $_POST['content'] ?? null,
                'review_notes' => $_POST['review_notes'] ?? null,
            ];
            $success = $articleService->updateArticle($articleId, $editorId, array_filter($changes));
            $message = $success ? 'Draft saved' : 'Failed to save draft';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'approve') {
            $success = $articleService->approveArticle($articleId, $editorId);
            $message = $success ? 'Article approved' : 'Failed to approve';
            $messageType = $success ? 'success' : 'error';
            if ($success) {
                header("Location: /admin/writer/editorial-queue.php");
                exit;
            }
        } elseif ($action === 'reject') {
            $success = $articleService->rejectArticle(
                $articleId,
                $editorId,
                $_POST['rejection_reason'] ?? 'Editorial decision'
            );
            $message = $success ? 'Article rejected' : 'Failed to reject';
            $messageType = $success ? 'success' : 'error';
            if ($success) {
                header("Location: /admin/writer/editorial-queue.php");
                exit;
            }
        } elseif ($action === 'override_safety') {
            $success = $safetyService->overrideSafetyFlag(
                $articleId,
                $editorId,
                $_POST['override_reason'] ?? 'Editor override'
            );
            $message = $success ? 'Safety flag overridden' : 'Failed to override';
            $messageType = $success ? 'success' : 'error';
        }

        if ($success) {
            $article = $articleService->getArticleForEdit($articleId);
        }
    } catch (\Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = 'Edit Article: ' . $article['title'];
$currentPage = 'writer_edit_article';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <style>
        body { background: #f8f9fa; }
        .editor-container { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        .editor-panel { background: white; border-radius: 8px; padding: 20px; }
        .sidebar-panel { background: white; border-radius: 8px; padding: 20px; }
        textarea.editor { font-family: 'Courier New', monospace; height: 400px; }
        .metadata { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .safety-alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .safety-alert.flagged { background: #fff3cd; border-left: 4px solid #fd7e14; }
        .safety-alert.rejected { background: #f8d7da; border-left: 4px solid #dc3545; }
        .safety-alert.approved { background: #d4edda; border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>✏️ Edit Article</h1>
            <a href="/admin/writer/editorial-queue.php" class="btn btn-secondary">Back to Queue</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="editor-container">
            <!-- Left: Editor -->
            <form method="POST" class="editor-panel">
                <h3><?php echo htmlspecialchars($article['title']); ?></h3>

                <div class="metadata">
                    <p><strong>Persona:</strong> <?php echo htmlspecialchars($article['persona_name']); ?></p>
                    <p><strong>Status:</strong> <?php echo ucfirst($article['status']); ?></p>
                    <p><strong>Detection:</strong> <?php echo htmlspecialchars($article['detection_type']); ?> (<?php echo $article['severity']; ?>)</p>
                    <p><strong>Story Value:</strong> <?php echo number_format($article['magnitude'], 1); ?>x baseline</p>
                </div>

                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($article['title']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($article['excerpt']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Article Content (Markdown)</label>
                    <textarea name="content" class="form-control editor"><?php echo htmlspecialchars($article['content']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Review Notes</label>
                    <textarea name="review_notes" class="form-control" rows="3"><?php echo htmlspecialchars($article['review_notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="save_draft" class="btn btn-primary">Save Draft</button>
                    <?php if ($article['status'] === 'draft' || $article['status'] === 'pending_review'): ?>
                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve & Publish</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this article?')">Reject</button>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Right: Sidebar -->
            <div class="sidebar-panel">
                <h4>📋 Article Info</h4>

                <!-- Safety Status -->
                <?php
                $safetyClass = match ($article['safety_scan_status']) {
                    'approved' => 'approved',
                    'flagged' => 'flagged',
                    'rejected' => 'rejected',
                    default => 'pending'
                };
                ?>
                <div class="safety-alert <?php echo $safetyClass; ?>">
                    <strong>Safety Status: <?php echo ucfirst($article['safety_scan_status']); ?></strong>
                    <?php if ($article['safety_score']): ?>
                        <p>Score: <?php echo number_format($article['safety_score'], 2); ?>/1.0</p>
                    <?php endif; ?>
                    <?php if ($article['safety_flags']): ?>
                        <details>
                            <summary>View flags</summary>
                            <pre><?php echo htmlspecialchars(json_encode(json_decode($article['safety_flags']), JSON_PRETTY_PRINT)); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>

                <?php if ($article['safety_scan_status'] === 'flagged'): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="action" value="override_safety">
                        <textarea name="override_reason" class="form-control mb-2" rows="2" placeholder="Reason for override..."></textarea>
                        <button type="submit" class="btn btn-sm btn-warning">Override Safety Flag</button>
                    </form>
                <?php endif; ?>

                <hr>

                <h5>Metrics</h5>
                <p><small>
                    Generation: <?php echo $article['generation_time_ms']; ?>ms<br>
                    Tokens: <?php echo $article['prompt_tokens'] . '+' . $article['completion_tokens']; ?><br>
                    Cost: $<?php echo number_format($article['generation_cost_usd'], 4); ?><br>
                    Persona: <?php echo htmlspecialchars($article['persona_name']); ?>
                </small></p>

                <hr>

                <h5>Story Details</h5>
                <p><small>
                    Artist: <?php echo htmlspecialchars($article['artist_name']); ?><br>
                    Type: <?php echo htmlspecialchars($article['detection_type']); ?><br>
                    Magnitude: <?php echo number_format($article['magnitude'], 1); ?>x<br>
                    Severity: <?php echo ucfirst($article['severity']); ?>
                </small></p>

                <hr>

                <p><small class="text-muted">
                    Created: <?php echo $article['created_at']; ?><br>
                    Claimed: <?php echo $article['claimed_at'] ?? 'Not claimed'; ?>
                </small></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
</body>
</html>
