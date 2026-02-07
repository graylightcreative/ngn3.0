<?php

// Include necessary NGN bootstrap and configurations
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json};
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Fans\GamificationService; // Not directly used here, but included for context
use PDO;
use DateTime;

// Basic page setup for Admin theme
$config = new Config();
$pageTitle = 'Editorial Dashboard';

$currentUserId = null;
$userRoleId = null;
$isEditorialWriter = false;
$isAdmin = false;
$editorialWriterRoleId = null;

// --- Authentication and Authorization ---
try {
    // Check session and roles
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

    $currentUser = $_SESSION['User'] ?? null;
    if ($currentUser) {
        $currentUserId = $currentUser['Id'] ?? null;
        $userRoleId = (int)($currentUser['RoleId'] ?? 0);

        // Determine if user is Admin
        $adminRoleIds = $config->legacyAdminRoleIds();
        $isAdmin = in_array($userRoleId, array_map('intval', $adminRoleIds), true);

        // Determine if user is an Editorial Writer
        // Fetch the Role ID for 'Editorial Writer' from roles table if it exists
        $editorialWriterRoleId = null;
        if (class_exists('NGN\Lib\DB\ConnectionFactory')) {
            try {
                $pdo = ConnectionFactory::read($config);
                $stmtRole = $pdo->prepare("SELECT id FROM `ngn_2025`.`roles` WHERE name = ? LIMIT 1");
                $stmtRole->execute(['Editorial Writer']);
                $editorialWriterRoleId = $stmtRole->fetchColumn();
            } catch (\Throwable $e) {
                error_log("Could not fetch 'Editorial Writer' role ID: " . $e->getMessage());
            }
        }

        $isEditorialWriter = ($userRoleId !== false && $editorialWriterRoleId !== null && $userRoleId === $editorialWriterRoleId);
    }

    // Access Control: Redirect or die if not Admin or Editorial Writer
    if (!$isEditorialWriter && !$isAdmin) {
        http_response_code(403); // Forbidden
        die('Access Denied: Editorial dashboard requires Administrator or Editorial Writer privileges.');
    }

} catch (\Throwable $e) {
    error_log("Editorial Dashboard Auth Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    die('An error occurred during authentication check.');
}

// --- Fetch Posts ---
$pendingPosts = [];
$claimedPosts = [];
$totalPending = 0;
$totalClaimed = 0;

try {
    $pdo = ConnectionFactory::read($config);

    // Fetch pending posts (status IN 'draft' or 'pending_review', and editor_id IS NULL)
    $stmtPending = $pdo->prepare(
        "SELECT p.id, p.title, p.status, p.created_at, p.editor_id, u.display_name AS author_name, u.username AS author_slug
         FROM `ngn_2025`.`posts` p
         LEFT JOIN `ngn_2025`.`users` u ON p.author_id = u.id
         WHERE p.status IN ('draft', 'pending_review') AND p.editor_id IS NULL
         ORDER BY p.created_at DESC"
    );
    $stmtPending->execute();
    $pendingPosts = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
    $totalPending = count($pendingPosts);

    // Fetch claimed posts (status IN 'draft' or 'pending_review', and editor_id = currentUserId)
    $stmtClaimed = $pdo->prepare(
        "SELECT p.id, p.title, p.status, p.created_at, p.editor_id, u.display_name AS author_name, u.username AS author_slug
         FROM `ngn_2025`.`posts` p
         LEFT JOIN `ngn_2025`.`users` u ON p.author_id = u.id
         WHERE p.status IN ('draft', 'pending_review') AND p.editor_id = :editorId
         ORDER BY p.created_at DESC"
    );
    $stmtClaimed->execute([':editorId' => $currentUserId]);
    $claimedPosts = $stmtClaimed->fetchAll(PDO::FETCH_ASSOC);
    $totalClaimed = count($claimedPosts);

} catch (\Throwable $e) {
    error_log("Editorial Dashboard: Error fetching posts - " . $e->getMessage());
    // Optionally display an error to the user on the page
    $errorMessage = "Error loading posts: " . $e->getMessage();
}

?>
<!-- Include Admin theme header partial -->
<?php require __DIR__ . '/../_header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Editorial Dashboard Content -->
    <div class="row">
        <!-- Unclaimed Queue Column -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Unclaimed Drafts</h6>
                    <span class="text-muted"><?php echo $totalPending; ?> pending posts</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="unclaimedPostsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pendingPosts)): ?>
                                    <?php foreach ($pendingPosts as $post):
                                        $editUrl = '/admin/posts/edit.php?id=' . $post['id'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></td>
                                        <td><?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($post['status'] ?? 'draft')) ?></td>
                                        <td>
                                            <a href="<?= $editUrl ?>" class="btn btn-sm btn-info mr-1"><i class="bi-pencil"></i> Edit</a>
                                            <button class="btn btn-sm btn-success claim-post-btn" data-post-id="<?= $post['id'] ?>" data-post-title="<?= urlencode($post['title'] ?? 'Untitled') ?>"><i class="bi-person-plus"></i> Claim</button>
                                            <button class="btn btn-sm btn-danger reject-post-btn" data-post-id="<?= $post['id'] ?>" data-post-title="<?= urlencode($post['title'] ?? 'Untitled') ?>"><i class="bi-x-octagon"></i> Reject</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No unclaimed posts available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Workspace Column -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Workspace</h6>
                    <span class="text-muted"><?php echo $totalClaimed; ?> posts assigned to you</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="myWorkspaceTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($claimedPosts)): ?>
                                    <?php foreach ($claimedPosts as $post):
                                        $editUrl = '/admin/posts/edit.php?id=' . $post['id'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></td>
                                        <td><?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($post['status'] ?? 'draft')) ?></td>
                                        <td>
                                            <a href="<?= $editUrl ?>" class="btn btn-sm btn-info mr-1"><i class="bi-pencil"></i> Edit</a>
                                            <button class="btn btn-sm btn-success publish-post-btn" data-post-id="<?= $post['id'] ?>" data-post-title="<?= urlencode($post['title'] ?? 'Untitled') ?>"><i class="bi-check-lg"></i> Publish</button>
                                            <button class="btn btn-sm btn-danger reject-post-btn" data-post-id="<?= $post['id'] ?>" data-post-title="<?= urlencode($post['title'] ?? 'Untitled') ?>"><i class="bi-trash"></i> Reject</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">You have no claimed posts. Claim one from the queue!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle AJAX simulation for actions (Claim, Publish, Reject)
        // In a real application, these would make API calls.

        // Claim Post action
        document.querySelectorAll('.claim-post-btn').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                const postTitle = decodeURIComponent(this.getAttribute('data-post-title'));
                if (!postId) return;

                if (confirm(`Are you sure you want to claim post: \"${postTitle}\"?`)) {
                    // Simulate API call: POST /api/v1/admin/claim-post
                    console.log(`Simulating claim for post ID: ${postId}`);
                    alert(`Post \"${postTitle}\" claimed successfully! (Simulated)
`);
                    // Refresh the page to reflect the change
                    window.location.reload(); 
                }
            });
        });

        // Reject Post action
        document.querySelectorAll('.reject-post-btn').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                const postTitle = decodeURIComponent(this.getAttribute('data-post-title'));
                if (!postId) return;

                if (confirm(`Are you sure you want to reject post: \"${postTitle}\"? This action cannot be undone.`)) {
                    // Simulate API call: POST /api/v1/admin/reject-post
                    console.log(`Simulating reject for post ID: ${postId}`);
                    alert(`Post \"${postTitle}\" rejected. (Simulated)
`);
                    // Remove the post row from the UI
                    this.closest('tr').remove(); 
                }
            });
        });

        // Publish Post action
        document.querySelectorAll('.publish-post-btn').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                const postTitle = decodeURIComponent(this.getAttribute('data-post-title'));
                if (!postId) return;

                if (confirm(`Are you sure you want to publish post: \"${postTitle}\"?`)) {
                    // Simulate API call: POST /api/v1/admin/publish-post
                    console.log(`Simulating publish for post ID: ${postId}`);
                    alert(`Post \"${postTitle}\" published successfully! (Simulated)
`);
                    // Remove the post row from the UI
                    this.closest('tr').remove();
                }
            });
        });
    });
</script>

<!-- Include Admin theme footer partial -->
<?php require __DIR__ . '/../_footer.php'; ?>