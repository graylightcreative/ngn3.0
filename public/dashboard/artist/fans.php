<?php

/**
 * Artist Dashboard - Fan Management
 * (Bible Ch. 7 - C.4/C.6 Subscriptions & Tipping: Fan engagement and Spark tipping)
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\Request;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Fans\SubscriptionService;
use PDO;
use DateTime;

// Basic page setup for Admin theme
$config = new Config();
$pageTitle = 'Fan Management';

$totalSubscribers = 0;
$monthlyRecurringRevenue = 0.00; // In dollars
$subscribersList = [];
$artistId = null;

// --- Fetch Artist ID and Data ---
try {
    // Assume the logged-in user context provides the artist ID.
    // For this example, we'll use a placeholder for the current artist ID.
    // In a real application, this would be derived from authentication or URL parameters.
    // If the user is an artist, their ID is the artist ID.
    // If it's an admin viewing an artist, the artist ID would need to be passed.

    // Placeholder: Assuming the current user IS the artist and their ID is available.
    // In a real application, this would be resolved via authentication.
    $currentUserId = 1; // Placeholder for the logged-in artist's ID
    $artistId = $currentUserId; // Assuming the user is an artist

    if ($artistId) {
        $subSvc = new SubscriptionService($config);
        // Fetch all related subscriptions for this artist.
        // Join with users to get subscriber name and email,
        // and with fan_subscription_tiers to get tier name and price.
        // JOIN with user_fan_scores to get fan scores.
        // Order by score descending.

        $stmtFans = $subSvc->pdoFanSubs->prepare(
            "SELECT 
                u.Id AS user_id, u.FirstName, u.LastName, u.Email, u.created_at AS join_date,
                fst.name AS tier_name, fst.price_monthly,
                ufs.status,
                COALESCE(ufs_score.score, 0) AS fan_score 
             FROM `ngn_2025`.`user_fan_subscriptions` ufs
             JOIN `ngn_2025`.`users` u ON ufs.user_id = u.id
             JOIN `ngn_2025`.`fan_subscription_tiers` fst ON ufs.tier_id = fst.id
             LEFT JOIN `ngn_2025`.`user_fan_scores` ufs_score ON ufs.user_id = ufs_score.user_id AND ufs.artist_id = ufs_score.artist_id
             WHERE ufs.artist_id = :artistId
             ORDER BY ufs_score.score DESC, ufs.created_at DESC"
        );
        $stmtFans->execute([':artistId' => $artistId]);
        $subscribersList = $stmtFans->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $totalSubscribers = count($subscribersList);
        foreach ($subscribersList as $subscriber) {
            // Only count active subscriptions for MRR
            if ($subscriber['status'] === 'active') {
                // price_monthly is already in dollars according to the migration schema.
                $monthlyRecurringRevenue += (float)$subscriber['price_monthly'];
            }
        }
    }

} catch (Throwable $e) {
    error_log("Error fetching fan data for artist dashboard: " . $e->getMessage());
    // Handle errors appropriately, e.g., display an error message on the page.
}

?>
<!-- Include Admin theme header partial -->
<?php require __DIR__ . '/../_header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <a href="tiers.php" class="btn btn-primary"><i class="bi bi-gem"></i> Manage Tiers</a>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Fans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($totalSubscribers); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-primary-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Monthly Revenue (MRR)</div>
                            <div class="h5 mb-0 font-weight-bold text-success-800">
                                <?php echo '$' . number_format($monthlyRecurringRevenue, 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-success-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscribers Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="fansTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Tier</th>
                            <th>Join Date</th>
                            <th>Status</th>
                            <th>Fan Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subscribersList)): ?>
                            <?php 
                            $rank = 1;
                            foreach ($subscribersList as $subscriber):
                                // Determine badge based on rank
                                $rankBadge = '';
                                if ($rank == 1) $rankBadge = '🥇';
                                elseif ($rank == 2) $rankBadge = '🥈';
                                elseif ($rank == 3) $rankBadge = '🥉';
                            ?>
                            <tr>
                                <td><?php echo $rankBadge ? '<span class="badge badge-warning">' . $rankBadge . '</span>' : ($rank <= 3 ? '<span class="badge badge-warning">' . $rankBadge . '</span>' : $rank) ; ?></td>
                                <td><?php echo htmlspecialchars($subscriber['display_name']); ?></td>
                                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                <td><?php echo htmlspecialchars($subscriber['tier_name'] ?? 'N/A'); ?></td>
                                <td><?php echo (new DateTime($subscriber['join_date']))->format('Y-m-d'); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch ($subscriber['status']) {
                                                case 'active': echo 'badge-success'; break;
                                                case 'cancelled': echo 'badge-danger'; break;
                                                case 'expired': echo 'badge-warning'; break;
                                                default: echo 'badge-secondary'; break;
                                            }
                                        ?>"> 
                                    <?php echo ucfirst($subscriber['status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($rank <= 3):
                                        echo '<span class="badge badge-primary">' . $subscriber['fan_score'] . '</span>';
                                    else:
                                        echo $subscriber['fan_score'];
                                    endif;
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="messageFan(<?php echo $subscriber['user_id']; ?>)">Message Fan</button>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No fan subscribers found for your artist yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    function messageFan(userId) {
        // Placeholder function for messaging a fan.
        // In a real implementation, this would open a modal, redirect to a chat, etc.
        alert('Messaging functionality for user ID: ' + userId + ' would go here.');
    }
</script>

<!-- Include Admin theme footer partial -->
<?php require __DIR__ . '/../_footer.php'; ?>
