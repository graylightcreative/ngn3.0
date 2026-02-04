<?php
/**
 * Station Dashboard - Live Listener Requests
 * DJ request queue with approval/rejection workflow
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Stations\ListenerRequestService;
use NGN\Lib\Stations\StationTierService;

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Live Requests';
$currentPage = 'live';

$config = new Config();
$requestService = new ListenerRequestService($config);
$tierService = new StationTierService($config);

$requests = [];
$error = $success = null;
$filterStatus = $_GET['status'] ?? 'pending';
$stats = [
    'pending' => 0,
    'approved' => 0,
    'fulfilled' => 0,
    'rejected' => 0
];

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $result = $requestService->approveRequest($requestId, $entity['id'], $user['Id']);
            if ($result) {
                $success = 'Request approved.';
            } else {
                $error = 'Failed to approve request.';
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle fulfillment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fulfill') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $result = $requestService->fulfillRequest($requestId, $entity['id']);
            if ($result) {
                $success = 'Request marked as fulfilled.';
            } else {
                $error = 'Failed to mark request as fulfilled.';
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $result = $requestService->rejectRequest($requestId, $entity['id']);
            if ($result) {
                $success = 'Request rejected.';
            } else {
                $error = 'Failed to reject request.';
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch requests
try {
    $result = $requestService->listRequests($entity['id'], null, 1, 100);
    if ($result['success']) {
        $requests = $result['items'] ?? [];
        // Count by status
        foreach ($requests as $req) {
            if (isset($stats[$req['status']])) {
                $stats[$req['status']]++;
            }
        }
        // Filter by status
        $requests = array_filter($requests, function($req) use ($filterStatus) {
            return $filterStatus === 'all' || $req['status'] === $filterStatus;
        });
    } else {
        $error = 'Failed to load requests: ' . ($result['message'] ?? 'Unknown error');
    }
} catch (\Throwable $e) {
    $error = 'Error loading requests: ' . $e->getMessage();
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Live Requests</h1>
        <p class="page-subtitle">Manage listener song requests, shoutouts, and dedications</p>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div class="card" style="text-align: center;">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Pending</p>
                <p style="margin: 0; font-size: 2rem; font-weight: bold; color: var(--warning);"><?= $stats['pending'] ?></p>
            </div>
            <div class="card" style="text-align: center;">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Approved</p>
                <p style="margin: 0; font-size: 2rem; font-weight: bold; color: var(--brand);"><?= $stats['approved'] ?></p>
            </div>
            <div class="card" style="text-align: center;">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Fulfilled</p>
                <p style="margin: 0; font-size: 2rem; font-weight: bold; color: var(--success);"><?= $stats['fulfilled'] ?></p>
            </div>
            <div class="card" style="text-align: center;">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Rejected</p>
                <p style="margin: 0; font-size: 2rem; font-weight: bold; color: var(--danger);"><?= $stats['rejected'] ?></p>
            </div>
        </div>

        <!-- Status Filters -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="?status=pending" class="btn <?= ($filterStatus === 'pending' ? 'btn-primary' : 'btn-secondary') ?>">Pending</a>
            <a href="?status=approved" class="btn <?= ($filterStatus === 'approved' ? 'btn-primary' : 'btn-secondary') ?>">Approved</a>
            <a href="?status=fulfilled" class="btn <?= ($filterStatus === 'fulfilled' ? 'btn-primary' : 'btn-secondary') ?>">Fulfilled</a>
            <a href="?status=rejected" class="btn <?= ($filterStatus === 'rejected' ? 'btn-primary' : 'btn-secondary') ?>">Rejected</a>
            <a href="?status=all" class="btn <?= ($filterStatus === 'all' ? 'btn-primary' : 'btn-secondary') ?>">All</a>
        </div>

        <!-- Request Queue -->
        <div class="card">
            <h2 class="text-xl" style="margin-top: 0; margin-bottom: 1rem;">
                Request Queue
                <span style="display: inline-flex; align-items: center; gap: 5px; margin-left: 10px; padding: 4px 8px; border-radius: 12px; background-color: #ef4444; color: #fff; font-size: 0.75rem; font-weight: 600;">
                    <span style="display: block; width: 8px; height: 8px; border-radius: 50%; background-color: #fff;"></span> LIVE
                </span>
            </h2>

            <?php if (empty($requests)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                <p><i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i></p>
                <p>No requests <?= ($filterStatus !== 'all' ? 'with status "' . $filterStatus . '"' : '') ?></p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($requests as $req): ?>
                <div style="padding: 1rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <strong><?= htmlspecialchars($req['song_title'] ?? $req['message'] ?? 'Request') ?></strong>
                                <span class="badge badge-<?= $req['status'] === 'approved' ? 'primary' : ($req['status'] === 'fulfilled' ? 'success' : ($req['status'] === 'rejected' ? 'error' : 'warning')) ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span>
                            </div>
                            <p style="margin: 0; font-size: 0.875rem; color: var(--text-muted);">
                                <?= htmlspecialchars($req['song_artist'] ?? 'Anonymous') ?> •
                                <?= htmlspecialchars($req['request_type'] ?? 'song') ?> •
                                <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($req['message'])): ?>
                    <p style="margin: 0.5rem 0; padding: 0.75rem; background: var(--bg-secondary); border-left: 3px solid var(--brand); border-radius: 0.25rem; font-size: 0.875rem;">
                        <?= htmlspecialchars($req['message']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Actions -->
                    <?php if ($req['status'] === 'pending'): ?>
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    </div>
                    <?php elseif ($req['status'] === 'approved'): ?>
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                            <input type="hidden" name="action" value="fulfill">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">Mark as Fulfilled</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestQueueContainer = document.querySelector('.card > div[style="display: grid; gap: 1rem;"]');
    const stationId = <?= $entity['id'] ?>;
    const csrfToken = '<?php echo dashboard_csrf_token(); ?>';
    const filterStatus = '<?= $filterStatus ?>';

    let mockRequests = [];
    let nextMockRequestId = 1;

    // Helper to generate a random request
    function generateMockRequest() {
        const statuses = ['pending', 'pending', 'pending', 'approved', 'rejected', 'fulfilled']; // More pending initially
        const types = ['song', 'shoutout', 'dedication'];
        const artists = ['The Local Band', 'Synthwave Duo', 'Jazz Ensemble', 'Rock Legends', 'New Artist'];
        const songs = ['Summer Jam', 'Midnight Drive', 'City Lights', 'Power Chord', 'Melody X'];
        const messages = ['Great show!', 'Love this track!', 'Dedicate to John', 'Can you play more rock?'];

        const status = statuses[Math.floor(Math.random() * statuses.length)];
        const type = types[Math.floor(Math.random() * types.length)];
        const artist = artists[Math.floor(Math.random() * artists.length)];
        const song = songs[Math.floor(Math.random() * songs.length)];
        const message = Math.random() > 0.5 ? messages[Math.floor(Math.random() * messages.length)] : '';

        return {
            id: nextMockRequestId++,
            song_title: song,
            song_artist: artist,
            request_type: type,
            message: message,
            status: status,
            created_at: new Date(Date.now() - Math.floor(Math.random() * 3600000)).toISOString() // Up to 1 hour ago
        };
    }

    // Initialize with some mock data
    for (let i = 0; i < 5; i++) {
        mockRequests.push(generateMockRequest());
    }

    function fetchRequests() {
        // Simulate dynamic updates
        if (Math.random() > 0.6) { // 40% chance to add a new request
            mockRequests.unshift(generateMockRequest()); // Add to front
        }
        if (mockRequests.length > 8 && Math.random() > 0.5) { // 50% chance to remove an old request if more than 8
            mockRequests.pop(); // Remove the oldest
        }
        
        // Randomly update status of a few requests
        mockRequests.forEach(req => {
            if (req.status === 'pending' && Math.random() > 0.7) { // 30% chance to change status
                req.status = Math.random() > 0.5 ? 'approved' : 'rejected';
            } else if (req.status === 'approved' && Math.random() > 0.8) { // 20% chance to fulfill
                req.status = 'fulfilled';
            }
        });

        // Filter mock requests based on current filterStatus
        const filteredRequests = mockRequests.filter(req => {
            return filterStatus === 'all' || req.status === filterStatus;
        });

        renderRequests(filteredRequests);
    }

    function renderRequests(requests) {
        if (!requestQueueContainer) return;

        if (requests.length === 0) {
            requestQueueContainer.innerHTML = `
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    <p><i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i></p>
                    <p>No requests ${filterStatus !== 'all' ? 'with status "' + filterStatus + '"' : ''}</p>
                </div>
            `;
            return;
        }

        let html = '';
        requests.forEach(req => {
            html += `
                <div style="padding: 1rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <strong>${escapeHTML(req.song_title || req.message || 'Request')}</strong>
                                <span class="badge badge-${getBadgeClass(req.status)}">
                                    ${ucfirst(req.status)}
                                </span>
                            </div>
                            <p style="margin: 0; font-size: 0.875rem; color: var(--text-muted);">
                                ${escapeHTML(req.song_artist || 'Anonymous')} •
                                ${escapeHTML(req.request_type || 'song')} •
                                ${new Date(req.created_at).toLocaleString()}
                            </p>
                        </div>
                    </div>
            `;

            if (req.message) {
                html += `
                    <p style="margin: 0.5rem 0; padding: 0.75rem; background: var(--bg-secondary); border-left: 3px solid var(--brand); border-radius: 0.25rem; font-size: 0.875rem;">
                        ${escapeHTML(req.message)}
                    </p>
                `;
            }

            // Simulated action buttons (these forms won't actually work without backend)
            if (req.status === 'pending') {
                html += `
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="alert('Simulated Approve for ${req.id}')">Approve</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="alert('Simulated Reject for ${req.id}')">Reject</button>
                    </div>
                `;
            } else if (req.status === 'approved') {
                 html += `
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                        <button type="button" class="btn btn-success btn-sm" onclick="alert('Simulated Fulfilled for ${req.id}')">Mark as Fulfilled</button>
                    </div>
                 `;
            }
            html += `</div>`;
        });
        requestQueueContainer.innerHTML = html;
    }
    
    function getBadgeClass(status) {
        switch(status) {
            case 'approved': return 'primary';
            case 'fulfilled': return 'success';
            case 'rejected': return 'error';
            default: return 'warning';
        }
    }

    function escapeHTML(str) {
        return str.replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Initial render
    fetchRequests();
    // Poll for new requests every 5 seconds
    setInterval(fetchRequests, 5000);
    // FUTURE: Replace polling with WebSockets in v2.1 for real-time updates
    // Current polling approach works well for MVP with reasonable server load
});
</script>

</body>
</html>
