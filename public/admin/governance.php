<?php
/**
 * Directorate Governance Board
 *
 * Management interface for Standardized Input Requests (SIRs) and board operations.
 * Implements governance model per Bible Ch. 25 (Directorate Structure) and Ch. 31 (SIR Protocol).
 *
 * Features:
 * - SIR status board (OPEN, IN REVIEW, RANT PHASE, VERIFIED, CLOSED)
 * - Division of labor registries
 * - Task tracking and assignment
 * - Vote recording and consensus tracking
 * - Board member management
 *
 * Related: Bible Ch. 25 (Directorate), Ch. 31 (SIR Protocol)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config\Config;

// Check authentication (replace with your auth system)
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'director'])) {
//     header('Location: /login');
//     exit;
// }

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Handle SIR status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sir_status') {
    try {
        $sirId = (int)($_POST['sir_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';

        if (!$sirId || !$newStatus) {
            throw new \Exception("Invalid SIR ID or status.");
        }

        $validStatuses = ['open', 'in_review', 'rant_phase', 'verified', 'closed'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \Exception("Invalid status value.");
        }

        $stmt = $pdo->prepare("
            UPDATE ngn_2025.sir_requests
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $sirId]);

        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO ngn_2025.sir_history (sir_id, old_status, new_status, notes, changed_by, changed_at)
            VALUES (?, (SELECT status FROM ngn_2025.sir_requests WHERE id = ?), ?, ?, ?, NOW())
        ");
        $stmt->execute([$sirId, $sirId, $newStatus, $notes, $_SESSION['user_id'] ?? 1]);

        header("Location: governance.php?updated=1");
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        error_log("SIR status update error: " . $e->getMessage());
    }
}

// Handle new SIR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sir') {
    try {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $priority = $_POST['priority'] ?? 'normal';

        if (!$title || !$description || !$category) {
            throw new \Exception("Title, description, and category are required.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO ngn_2025.sir_requests
            (title, description, category, priority, status, submitted_by, submitted_at, updated_at)
            VALUES (?, ?, ?, ?, 'open', ?, NOW(), NOW())
        ");
        $stmt->execute([$title, $description, $category, $priority, $_SESSION['user_id'] ?? 1]);

        header("Location: governance.php?created=1");
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        error_log("SIR creation error: " . $e->getMessage());
    }
}

// Fetch SIR statistics
$sirStats = [
    'total' => 0,
    'open' => 0,
    'in_review' => 0,
    'rant_phase' => 0,
    'verified' => 0,
    'closed' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'open' THEN 1 END) as open,
            COUNT(CASE WHEN status = 'in_review' THEN 1 END) as in_review,
            COUNT(CASE WHEN status = 'rant_phase' THEN 1 END) as rant_phase,
            COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified,
            COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed
        FROM ngn_2025.sir_requests
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sirStats = $row;
    }
} catch (\Throwable $e) {
    error_log("Failed to fetch SIR stats: " . $e->getMessage());
}

// Fetch active SIRs grouped by status
$sirsByStatus = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.title,
            s.description,
            s.category,
            s.priority,
            s.status,
            s.submitted_at,
            s.updated_at,
            u.Name as submitted_by_name
        FROM ngn_2025.sir_requests s
        LEFT JOIN ngn_2025.users u ON u.Id = s.submitted_by
        WHERE s.status != 'closed'
        ORDER BY
            FIELD(s.status, 'open', 'in_review', 'rant_phase', 'verified'),
            s.priority DESC,
            s.submitted_at ASC
        LIMIT 100
    ");
    $stmt->execute();
    $allSirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by status
    foreach ($allSirs as $sir) {
        $status = $sir['status'];
        if (!isset($sirsByStatus[$status])) {
            $sirsByStatus[$status] = [];
        }
        $sirsByStatus[$status][] = $sir;
    }
} catch (\Throwable $e) {
    error_log("Failed to fetch SIRs: " . $e->getMessage());
}

// Fetch board member activity
$boardActivity = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            u.Name,
            COUNT(DISTINCT s.id) as sirs_submitted,
            COUNT(DISTINCT v.id) as votes_cast,
            MAX(s.submitted_at) as last_submission
        FROM ngn_2025.users u
        LEFT JOIN ngn_2025.sir_requests s ON s.submitted_by = u.Id
        LEFT JOIN ngn_2025.sir_votes v ON v.user_id = u.Id
        WHERE u.is_director = 1
        GROUP BY u.Id
        ORDER BY sirs_submitted DESC
        LIMIT 20
    ");
    $stmt->execute();
    $boardActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch board activity: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorate Governance - NextGenNoise Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Directorate Governance Board</h1>
                    <p class="text-gray-400">Standardized Input Request (SIR) tracking and board management</p>
                </div>
                <a href="index.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['created'])): ?>
            <div class="mb-6 p-4 bg-green-900/30 border border-green-500 rounded-lg">
                <p class="text-green-400">✓ SIR created successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="mb-6 p-4 bg-green-900/30 border border-green-500 rounded-lg">
                <p class="text-green-400">✓ SIR status updated successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-900/30 border border-red-500 rounded-lg">
                <p class="text-red-400">✗ Error: <?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-gradient-to-br from-gray-800 to-gray-700 border border-gray-600 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium mb-1">Total SIRs</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['total'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-blue-900/40 to-blue-800/20 border border-blue-700/50 rounded-lg p-4">
                <div class="text-blue-400 text-sm font-medium mb-1">OPEN</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['open'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-yellow-900/40 to-yellow-800/20 border border-yellow-700/50 rounded-lg p-4">
                <div class="text-yellow-400 text-sm font-medium mb-1">IN REVIEW</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['in_review'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-orange-900/40 to-orange-800/20 border border-orange-700/50 rounded-lg p-4">
                <div class="text-orange-400 text-sm font-medium mb-1">RANT PHASE</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['rant_phase'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-green-900/40 to-green-800/20 border border-green-700/50 rounded-lg p-4">
                <div class="text-green-400 text-sm font-medium mb-1">VERIFIED</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['verified'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-gray-700 to-gray-600 border border-gray-500 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium mb-1">CLOSED</div>
                <div class="text-2xl font-bold text-white"><?= $sirStats['closed'] ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-700">
            <div class="flex space-x-4">
                <button onclick="showTab('board')" id="tab-board" class="tab-button px-4 py-2 border-b-2 border-blue-500 text-blue-400 font-medium">
                    SIR Board
                </button>
                <button onclick="showTab('create')" id="tab-create" class="tab-button px-4 py-2 border-b-2 border-transparent text-gray-400 hover:text-gray-300">
                    Create New SIR
                </button>
                <button onclick="showTab('activity')" id="tab-activity" class="tab-button px-4 py-2 border-b-2 border-transparent text-gray-400 hover:text-gray-300">
                    Board Activity
                </button>
            </div>
        </div>

        <!-- SIR Board Tab -->
        <div id="content-board" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
                <!-- OPEN Column -->
                <div class="bg-gray-800 border border-blue-700/50 rounded-lg overflow-hidden">
                    <div class="bg-blue-900/30 border-b border-blue-700/50 p-4">
                        <h3 class="text-lg font-bold text-blue-400">OPEN (<?= count($sirsByStatus['open'] ?? []) ?>)</h3>
                        <p class="text-xs text-gray-400 mt-1">Newly submitted, awaiting review</p>
                    </div>
                    <div class="p-4 space-y-3" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($sirsByStatus['open'])): ?>
                            <div class="text-center text-gray-500 py-8 text-sm">No open SIRs</div>
                        <?php else: ?>
                            <?php foreach ($sirsByStatus['open'] as $sir): ?>
                                <?php include __DIR__ . '/_sir_card.php'; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- IN REVIEW Column -->
                <div class="bg-gray-800 border border-yellow-700/50 rounded-lg overflow-hidden">
                    <div class="bg-yellow-900/30 border-b border-yellow-700/50 p-4">
                        <h3 class="text-lg font-bold text-yellow-400">IN REVIEW (<?= count($sirsByStatus['in_review'] ?? []) ?>)</h3>
                        <p class="text-xs text-gray-400 mt-1">Under directorate consideration</p>
                    </div>
                    <div class="p-4 space-y-3" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($sirsByStatus['in_review'])): ?>
                            <div class="text-center text-gray-500 py-8 text-sm">No SIRs in review</div>
                        <?php else: ?>
                            <?php foreach ($sirsByStatus['in_review'] as $sir): ?>
                                <?php include __DIR__ . '/_sir_card.php'; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RANT PHASE Column -->
                <div class="bg-gray-800 border border-orange-700/50 rounded-lg overflow-hidden">
                    <div class="bg-orange-900/30 border-b border-orange-700/50 p-4">
                        <h3 class="text-lg font-bold text-orange-400">RANT PHASE (<?= count($sirsByStatus['rant_phase'] ?? []) ?>)</h3>
                        <p class="text-xs text-gray-400 mt-1">Veto period, debate active</p>
                    </div>
                    <div class="p-4 space-y-3" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($sirsByStatus['rant_phase'])): ?>
                            <div class="text-center text-gray-500 py-8 text-sm">No SIRs in rant phase</div>
                        <?php else: ?>
                            <?php foreach ($sirsByStatus['rant_phase'] as $sir): ?>
                                <?php include __DIR__ . '/_sir_card.php'; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- VERIFIED Column -->
                <div class="bg-gray-800 border border-green-700/50 rounded-lg overflow-hidden">
                    <div class="bg-green-900/30 border-b border-green-700/50 p-4">
                        <h3 class="text-lg font-bold text-green-400">VERIFIED (<?= count($sirsByStatus['verified'] ?? []) ?>)</h3>
                        <p class="text-xs text-gray-400 mt-1">Approved, ready for implementation</p>
                    </div>
                    <div class="p-4 space-y-3" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($sirsByStatus['verified'])): ?>
                            <div class="text-center text-gray-500 py-8 text-sm">No verified SIRs</div>
                        <?php else: ?>
                            <?php foreach ($sirsByStatus['verified'] as $sir): ?>
                                <?php include __DIR__ . '/_sir_card.php'; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SIR Protocol Reference -->
            <div class="mt-8 bg-gray-800 border border-gray-700 rounded-lg p-6">
                <h3 class="text-lg font-bold text-white mb-4">SIR Protocol Reference (Bible Ch. 31)</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-sm">
                    <div>
                        <div class="font-semibold text-blue-400 mb-2">OPEN</div>
                        <p class="text-gray-400">
                            Newly submitted requests awaiting initial triage and assignment to a review committee.
                        </p>
                    </div>
                    <div>
                        <div class="font-semibold text-yellow-400 mb-2">IN REVIEW</div>
                        <p class="text-gray-400">
                            Under active consideration by the directorate. Discussion, research, and deliberation in progress.
                        </p>
                    </div>
                    <div>
                        <div class="font-semibold text-orange-400 mb-2">RANT PHASE</div>
                        <p class="text-gray-400">
                            7-day veto window. Any director can object and trigger extended debate. Silence implies consent.
                        </p>
                    </div>
                    <div>
                        <div class="font-semibold text-green-400 mb-2">VERIFIED</div>
                        <p class="text-gray-400">
                            Passed all review phases. Approved for implementation. Moves to engineering backlog.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create New SIR Tab -->
        <div id="content-create" class="tab-content hidden">
            <div class="max-w-3xl mx-auto">
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                    <h2 class="text-xl font-bold text-white mb-4">Submit New Standardized Input Request</h2>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_sir">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Title <span class="text-red-400">*</span></label>
                            <input type="text" name="title" required
                                   class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                                   placeholder="Brief summary of the request">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Category <span class="text-red-400">*</span></label>
                            <select name="category" required
                                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Select Category --</option>
                                <option value="feature">Feature Request</option>
                                <option value="policy">Policy Change</option>
                                <option value="technical">Technical Infrastructure</option>
                                <option value="financial">Financial Decision</option>
                                <option value="legal">Legal/Compliance</option>
                                <option value="operational">Operational Change</option>
                                <option value="strategic">Strategic Direction</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                            <select name="priority"
                                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                                <option value="low">Low - Can wait</option>
                                <option value="normal" selected>Normal - Standard timeline</option>
                                <option value="high">High - Needs attention soon</option>
                                <option value="critical">Critical - Urgent action required</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description <span class="text-red-400">*</span></label>
                            <textarea name="description" rows="8" required
                                      class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                                      placeholder="Detailed description of the request, including:
- What problem does this solve?
- Who benefits from this change?
- What resources are required?
- Are there any risks or trade-offs?"></textarea>
                        </div>

                        <button type="submit" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg transition">
                            Submit SIR for Review
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Board Activity Tab -->
        <div id="content-activity" class="tab-content hidden">
            <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h2 class="text-xl font-bold text-white">Board Member Activity</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-900 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Director</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">SIRs Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Votes Cast</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Last Submission</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($boardActivity)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        No board activity recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($boardActivity as $member): ?>
                                    <tr class="hover:bg-gray-750">
                                        <td class="px-4 py-3 text-sm font-medium text-white"><?= htmlspecialchars($member['Name']) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-300"><?= $member['sirs_submitted'] ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-300"><?= $member['votes_cast'] ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-400">
                                            <?= $member['last_submission'] ? date('M j, Y', strtotime($member['last_submission'])) : 'Never' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        function showTab(tabName) {
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Reset all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-400');
                button.classList.add('border-transparent', 'text-gray-400');
            });

            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Highlight selected tab
            const selectedTab = document.getElementById('tab-' + tabName);
            selectedTab.classList.remove('border-transparent', 'text-gray-400');
            selectedTab.classList.add('border-blue-500', 'text-blue-400');
        }

        function toggleSirDetails(sirId) {
            const details = document.getElementById('sir-details-' + sirId);
            const arrow = document.getElementById('sir-arrow-' + sirId);

            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
                arrow.style.transform = 'rotate(90deg)';
            } else {
                details.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>
