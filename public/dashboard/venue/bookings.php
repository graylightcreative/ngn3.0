<?php
/**
 * Venue Dashboard - Booking Requests Management
 * (Bible Ch. 11 - Booking Workflows: Artist-venue booking negotiation)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Bookings';
$currentPage = 'bookings';

$action = $_GET['action'] ?? 'list';
$bookingId = $_GET['id'] ?? null;
$tab = $_GET['tab'] ?? 'pending';
$success = $error = null;
$bookings = [];
$booking = null;
$messages = [];

// Fetch bookings for this venue
if ($entity) {
    try {
        $pdo = dashboard_pdo();

        // Load all bookings for venue with filter
        $statuses = [];
        switch ($tab) {
            case 'pending':
                $statuses = ['pending'];
                break;
            case 'negotiating':
                $statuses = ['negotiating'];
                break;
            case 'accepted':
                $statuses = ['accepted'];
                break;
            case 'confirmed':
                $statuses = ['confirmed'];
                break;
            default:
                $statuses = ['pending', 'negotiating', 'accepted', 'confirmed'];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge([$entity['id']], $statuses);

        $stmt = $pdo->prepare("
            SELECT br.*, a.name as artist_name
            FROM ngn_2025.booking_requests br
            LEFT JOIN ngn_2025.cdm_artists a ON a.id = br.artist_id
            WHERE br.venue_id = ? AND br.status IN ($placeholders)
            ORDER BY br.created_at DESC
        ");
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON fields
        foreach ($bookings as &$b) {
            $b['alternative_dates'] = json_decode($b['alternative_dates'] ?? '[]', true);
            $b['metadata'] = json_decode($b['metadata'] ?? '{}', true);
        }

        // Load single booking if requested
        if ($bookingId && $action === 'view') {
            $stmt = $pdo->prepare("
                SELECT br.*, a.name as artist_name, a.id as artist_id
                FROM ngn_2025.booking_requests br
                LEFT JOIN ngn_2025.cdm_artists a ON a.id = br.artist_id
                WHERE br.id = ? AND br.venue_id = ?
            ");
            $stmt->execute([$bookingId, $entity['id']]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking) {
                $booking['alternative_dates'] = json_decode($booking['alternative_dates'] ?? '[]', true);
                $booking['metadata'] = json_decode($booking['metadata'] ?? '{}', true);

                // Load messages
                $stmt = $pdo->prepare("
                    SELECT * FROM ngn_2025.booking_messages
                    WHERE booking_request_id = ?
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$bookingId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($messages as &$msg) {
                    $msg['counter_offer_json'] = json_decode($msg['counter_offer_json'] ?? '{}', true);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Booking fetch error: ' . $e->getMessage());
    }
}

// Handle booking actions (accept, reject, counter)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action']) && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $bid = $_POST['booking_id'] ?? null;
        $bookingAction = $_POST['booking_action'];

        if ($bid) {
            try {
                $pdo = dashboard_pdo();

                if ($bookingAction === 'accept') {
                    $response = $_POST['response_message'] ?? null;

                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.booking_requests
                        SET status = 'accepted', response_message = ?, responded_at = NOW()
                        WHERE id = ? AND venue_id = ?
                    ");
                    $stmt->execute([$response, $bid, $entity['id']]);
                    $success = 'Booking accepted!';

                } elseif ($bookingAction === 'reject') {
                    $reason = $_POST['rejection_reason'] ?? null;

                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.booking_requests
                        SET status = 'rejected', rejection_reason = ?, responded_at = NOW()
                        WHERE id = ? AND venue_id = ?
                    ");
                    $stmt->execute([$reason, $bid, $entity['id']]);
                    $success = 'Booking rejected.';

                } elseif ($bookingAction === 'counter') {
                    $message = $_POST['counter_message'] ?? null;
                    $counterDate = $_POST['counter_date'] ?? null;
                    $counterGuarantee = $_POST['counter_guarantee'] ?? null;
                    $counterSplit = $_POST['counter_split'] ?? null;

                    $counterOffer = [];
                    if ($counterDate) $counterOffer['date'] = $counterDate;
                    if ($counterGuarantee) $counterOffer['guarantee'] = (int)$counterGuarantee;
                    if ($counterSplit) $counterOffer['split'] = (float)$counterSplit;

                    // Update booking to negotiating
                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.booking_requests
                        SET status = 'negotiating'
                        WHERE id = ? AND venue_id = ?
                    ");
                    $stmt->execute([$bid, $entity['id']]);

                    // Send counter message
                    $stmt = $pdo->prepare("
                        INSERT INTO ngn_2025.booking_messages (
                            booking_request_id, sender_type, sender_id,
                            message, is_counter_offer, counter_offer_json
                        ) VALUES (?, 'venue', ?, ?, 1, ?)
                    ");
                    $stmt->execute([$bid, $entity['id'], $message, json_encode($counterOffer)]);
                    $success = 'Counter-offer sent!';
                }

                // Reload booking
                $stmt = $pdo->prepare("
                    SELECT br.*, a.name as artist_name
                    FROM ngn_2025.booking_requests br
                    LEFT JOIN ngn_2025.cdm_artists a ON a.id = br.artist_id
                    WHERE br.id = ? AND br.venue_id = ?
                ");
                $stmt->execute([$bid, $entity['id']]);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($booking) {
                    $booking['alternative_dates'] = json_decode($booking['alternative_dates'] ?? '[]', true);
                    $booking['metadata'] = json_decode($booking['metadata'] ?? '{}', true);
                }

                // Reload messages
                $stmt = $pdo->prepare("SELECT * FROM ngn_2025.booking_messages WHERE booking_request_id = ? ORDER BY created_at ASC");
                $stmt->execute([$bid]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($messages as &$msg) {
                    $msg['counter_offer_json'] = json_decode($msg['counter_offer_json'] ?? '{}', true);
                }
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$csrf = dashboard_csrf_token();

// Helper for safe date parsing
function safeStrtotime($dateStr) {
    if (empty($dateStr)) return false;
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

// Count bookings by status
$pendingCount = 0;
$negotiatingCount = 0;
$acceptedCount = 0;
$confirmedCount = 0;

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        foreach (['pending', 'negotiating', 'accepted', 'confirmed'] as $s) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM ngn_2025.booking_requests WHERE venue_id = ? AND status = ?");
            $stmt->execute([$entity['id'], $s]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            ${$s . 'Count'} = $r['c'] ?? 0;
        }
    } catch (PDOException $e) {}
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Booking Requests</h1>
        <p class="page-subtitle">Manage artist booking inquiries and confirmations</p>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Booking Detail View -->
        <?php if ($action === 'view' && $booking): ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <!-- Left: Booking Details & Messages -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <?= htmlspecialchars($booking['artist_name']) ?> ▸ <?= date('M j, Y', strtotime($booking['requested_date'])) ?>
                        </h2>
                    </div>
                    <div style="padding: 20px; display: grid; gap: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Status</div>
                                <div style="font-weight: 600; text-transform: capitalize;">
                                    <span class="badge" style="background: var(--primary); color: white; padding: 4px 8px; border-radius: 3px;">
                                        <?= htmlspecialchars($booking['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Initiator</div>
                                <div style="font-weight: 600; text-transform: capitalize;">
                                    <?= htmlspecialchars($booking['requesting_party']) ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Offer Type</div>
                                <div style="font-weight: 600; text-transform: capitalize;">
                                    <?= htmlspecialchars($booking['offer_type']) ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Expected Attendance</div>
                                <div style="font-weight: 600;">
                                    <?= $booking['expected_attendance'] ? number_format($booking['expected_attendance']) : '—' ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($booking['guarantee_amount_cents']): ?>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Guarantee</div>
                            <div style="font-weight: 600;">$<?= number_format($booking['guarantee_amount_cents'] / 100, 2) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($booking['door_split_percentage']): ?>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Door Split</div>
                            <div style="font-weight: 600;"><?= $booking['door_split_percentage'] ?>% (artist)</div>
                        </div>
                        <?php endif; ?>

                        <?php if ($booking['request_message']): ?>
                        <div style="border-top: 1px solid var(--border-color); padding-top: 16px;">
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Initial Message</div>
                            <div style="padding: 12px; background: var(--bg-primary); border-radius: 6px; font-size: 14px;">
                                <?= htmlspecialchars($booking['request_message']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages Thread -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2 class="card-title">Message Thread (<?= count($messages) ?>)</h2>
                    </div>
                    <div style="padding: 20px; display: grid; gap: 12px; max-height: 400px; overflow-y: auto;">
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg): ?>
                            <div style="padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <div style="font-weight: 600; text-transform: capitalize;">
                                        <?= htmlspecialchars($msg['sender_type']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?= date('M j, g:i a', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                                <div style="font-size: 14px; color: var(--text-primary);">
                                    <?= htmlspecialchars($msg['message']) ?>
                                </div>
                                <?php if ($msg['is_counter_offer'] && !empty($msg['counter_offer_json'])): ?>
                                <div style="margin-top: 8px; padding: 8px; background: rgba(var(--primary-rgb), 0.1); border-radius: 4px; font-size: 12px;">
                                    <strong>Counter-Offer:</strong>
                                    <?php if (!empty($msg['counter_offer_json']['date'])): ?>
                                    · Date: <?= htmlspecialchars($msg['counter_offer_json']['date']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['counter_offer_json']['guarantee'])): ?>
                                    · Guarantee: $<?= number_format($msg['counter_offer_json']['guarantee'] / 100, 2) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['counter_offer_json']['split'])): ?>
                                    · Split: <?= $msg['counter_offer_json']['split'] ?>%
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 20px;">No messages yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Actions -->
            <div>
                <?php if ($booking['status'] === 'pending' || $booking['status'] === 'negotiating'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Actions</h2>
                    </div>
                    <form method="POST" style="padding: 20px; display: grid; gap: 12px;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']) ?>">

                        <!-- Accept Button -->
                        <button type="submit" name="booking_action" value="accept" class="btn btn-primary" onclick="return confirm('Accept this booking?')">
                            <i class="bi bi-check"></i> Accept Booking
                        </button>

                        <!-- Counter Offer Form -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 12px;">
                            <button type="button" class="btn btn-secondary" style="width: 100%; margin-bottom: 12px;" onclick="document.getElementById('counter-form').style.display = document.getElementById('counter-form').style.display === 'none' ? 'grid' : 'none';">
                                <i class="bi bi-chat-dots"></i> Send Counter-Offer
                            </button>

                            <div id="counter-form" style="display: none; grid-template-columns: 1fr; gap: 12px;">
                                <textarea name="counter_message" placeholder="Your message..." style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;"></textarea>

                                <div style="display: grid; gap: 8px;">
                                    <label style="font-size: 12px;">Alternative Date (optional)</label>
                                    <input type="date" name="counter_date" style="padding: 6px; border: 1px solid var(--border-color); border-radius: 4px;">
                                </div>

                                <div style="display: grid; gap: 8px;">
                                    <label style="font-size: 12px;">New Guarantee $ (optional)</label>
                                    <input type="number" name="counter_guarantee" placeholder="0.00" step="0.01" style="padding: 6px; border: 1px solid var(--border-color); border-radius: 4px;">
                                </div>

                                <button type="submit" name="booking_action" value="counter" class="btn btn-primary" style="margin-top: 8px;">
                                    <i class="bi bi-send"></i> Send Counter
                                </button>
                            </div>
                        </div>

                        <!-- Reject Button -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 12px;">
                            <button type="button" class="btn btn-danger" style="width: 100%; margin-bottom: 12px;" onclick="document.getElementById('reject-form').style.display = document.getElementById('reject-form').style.display === 'none' ? 'grid' : 'none';">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>

                            <div id="reject-form" style="display: none; grid-template-columns: 1fr; gap: 12px;">
                                <textarea name="rejection_reason" placeholder="Why are you rejecting?" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;"></textarea>
                                <button type="submit" name="booking_action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this booking?')">
                                    <i class="bi bi-check"></i> Confirm Reject
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2 class="card-title">Details</h2>
                    </div>
                    <div style="padding: 20px; display: grid; gap: 12px; font-size: 13px;">
                        <div>
                            <div style="color: var(--text-muted); margin-bottom: 4px;">Created</div>
                            <div><?= date('M j, Y g:i a', strtotime($booking['created_at'])) ?></div>
                        </div>
                        <div>
                            <div style="color: var(--text-muted); margin-bottom: 4px;">Requested Date</div>
                            <div><?= date('M j, Y', strtotime($booking['requested_date'])) ?></div>
                        </div>
                        <?php if ($booking['preferred_door_time']): ?>
                        <div>
                            <div style="color: var(--text-muted); margin-bottom: 4px;">Door Time</div>
                            <div><?= htmlspecialchars($booking['preferred_door_time']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="bookings.php" class="btn btn-secondary" style="width: 100%; margin-top: 20px;">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- Bookings List View -->
        <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
            <a href="bookings.php?tab=pending" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 8px 12px;">
                Pending <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 4px;"><?= $pendingCount ?></span>
            </a>
            <a href="bookings.php?tab=negotiating" class="btn <?= $tab === 'negotiating' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 8px 12px;">
                Negotiating <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 4px;"><?= $negotiatingCount ?></span>
            </a>
            <a href="bookings.php?tab=accepted" class="btn <?= $tab === 'accepted' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 8px 12px;">
                Accepted <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 4px;"><?= $acceptedCount ?></span>
            </a>
            <a href="bookings.php?tab=confirmed" class="btn <?= $tab === 'confirmed' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 8px 12px;">
                Confirmed <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 4px;"><?= $confirmedCount ?></span>
            </a>
        </div>

        <?php if ($bookings): ?>
        <div class="card">
            <div style="display: grid; gap: 8px; padding: 12px;">
                <?php foreach ($bookings as $b): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 500; margin-bottom: 4px;">
                            <?= htmlspecialchars($b['artist_name'] ?? 'Unknown Artist') ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?= date('M j, Y', strtotime($b['requested_date'])) ?>
                            <?php if ($b['offering_type'] === 'guarantee'): ?>
                            · Guarantee: $<?= number_format($b['guarantee_amount_cents'] / 100, 0) ?>
                            <?php elseif ($b['offer_type'] === 'door_split'): ?>
                            · Door Split: <?= $b['door_split_percentage'] ?>%
                            <?php endif; ?>
                            · <span style="text-transform: capitalize;"><?= htmlspecialchars($b['status']) ?></span>
                        </div>
                    </div>
                    <a href="bookings.php?action=view&id=<?= htmlspecialchars($b['id']) ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                        <i class="bi bi-arrow-right"></i> View
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <p style="color: var(--text-muted);">No bookings in this category yet.</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
