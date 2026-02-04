<?php
/**
 * Venue Dashboard - NGN Score
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'NGN Score';
$currentPage = 'score';

$scoreData = ['ranking' => '-', 'score' => 0, 'breakdown' => []];
$connectedPlatforms = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        
        // Get score
        $stmt = $pdo->prepare("SELECT * FROM entity_scores WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $scoreData['ranking'] = $row['ranking'] ?: '-';
            $scoreData['score'] = (int)$row['score'];
            $scoreData['breakdown'] = json_decode($row['breakdown'] ?? '{}', true) ?: [];
        }
        
        // Get connected platforms
        $stmt = $pdo->prepare("SELECT provider FROM oauth_tokens WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connectedPlatforms[] = $r['provider'];
        }
    } catch (PDOException $e) {}
}

// Score factors
$factors = [
    ['name' => 'Profile Completeness', 'icon' => 'bi-person-badge', 'max' => 100, 'current' => $scoreData['breakdown']['profile'] ?? 0],
    ['name' => 'Shows Hosted', 'icon' => 'bi-calendar-event', 'max' => 200, 'current' => $scoreData['breakdown']['shows'] ?? 0],
    ['name' => 'Social Connections', 'icon' => 'bi-share', 'max' => 150, 'current' => $scoreData['breakdown']['social'] ?? 0],
    ['name' => 'Content (Posts/Videos)', 'icon' => 'bi-collection-play', 'max' => 100, 'current' => $scoreData['breakdown']['content'] ?? 0],
    ['name' => 'Engagement', 'icon' => 'bi-heart', 'max' => 150, 'current' => $scoreData['breakdown']['engagement'] ?? 0],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">NGN Score</h1>
        <p class="page-subtitle">Your venue's ranking and performance metrics</p>
    </header>
    
    <div class="page-content">
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your venue profile first. <a href="profile.php">Set up profile →</a></div>
        <?php else: ?>
        
        <!-- Score Overview -->
        <div class="grid grid-2">
            <div class="card" style="text-align: center; padding: 48px;">
                <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">VENUE RANKING</div>
                <div style="font-size: 72px; font-weight: 800; color: var(--brand);">#<?= $scoreData['ranking'] ?></div>
                <div style="font-size: 14px; color: var(--text-muted);">Among all venues</div>
            </div>
            <div class="card" style="text-align: center; padding: 48px;">
                <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">TOTAL SCORE</div>
                <div style="font-size: 72px; font-weight: 800; color: var(--accent);"><?= number_format($scoreData['score']) ?></div>
                <div style="font-size: 14px; color: var(--text-muted);">Points earned</div>
            </div>
        </div>
        
        <!-- Score Breakdown -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">Score Breakdown</h2></div>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($factors as $factor): 
                    $pct = $factor['max'] > 0 ? min(100, ($factor['current'] / $factor['max']) * 100) : 0;
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="<?= $factor['icon'] ?>" style="color: var(--brand);"></i>
                            <span style="font-weight: 500;"><?= $factor['name'] ?></span>
                        </div>
                        <span style="color: var(--text-muted);"><?= $factor['current'] ?> / <?= $factor['max'] ?></span>
                    </div>
                    <div style="height: 8px; background: var(--border); border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $pct ?>%; background: linear-gradient(90deg, var(--brand), var(--accent)); border-radius: 4px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- How to Improve -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">How to Improve Your Score</h2></div>
            <div class="grid grid-2">
                <div style="display: flex; gap: 12px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <i class="bi bi-calendar-plus" style="font-size: 24px; color: var(--brand);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Host More Shows</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Each show adds to your venue's reputation</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <i class="bi bi-share" style="font-size: 24px; color: var(--accent);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Connect Social Accounts</div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php if (empty($connectedPlatforms)): ?>
                            <a href="connections.php">Connect now →</a>
                            <?php else: ?>
                            <?= count($connectedPlatforms) ?> connected
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <i class="bi bi-newspaper" style="font-size: 24px; color: #a855f7;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Post Updates</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Share news and engage your audience</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <i class="bi bi-camera-video" style="font-size: 24px; color: #f59e0b;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Add Videos</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Showcase your venue with video content</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>
</body>
</html>

