<?php
/**
 * Artist Dashboard - NGN Score / Prove It Page
 * Itemized breakdown of all ranking factors
 * (Bible Ch. 7 - A.3 Investor Status: 1.05x Influence Weighting for investors)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'NGN Score';
$currentPage = 'score';

// Fetch actual data for score calculation
$pdo = dashboard_pdo();
$artistId = $entity['id'] ?? 0;
$legacyUserId = $user['Id'] ?? 0;

// Check if user is an investor
$isInvestor = false;
try {
    $stmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`investments` WHERE user_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$legacyUserId]);
    if ($stmt->fetch()) {
        $isInvestor = true;
    }
} catch (PDOException $e) {}

// Get ranking data
$rankData = ['score' => 0, 'rank' => null, 'prev_rank' => null];
try {
    $stmt = $pdo->prepare("SELECT ri.score, ri.rank, ri.prev_rank
        FROM `ngn_rankings_2025`.`ranking_items` ri
        JOIN `ngn_rankings_2025`.`ranking_windows` rw ON rw.id = ri.window_id
        WHERE ri.entity_type = 'artist' AND ri.entity_id = ?
        ORDER BY rw.window_start DESC LIMIT 1");
    $stmt->execute([$artistId]);
    $rankData = $stmt->fetch(PDO::FETCH_ASSOC) ?: $rankData;
} catch (PDOException $e) {}

// Get content counts
$releasesCount = $videosCount = $showsCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`releases` WHERE artist_id = ?");
    $stmt->execute([$artistId]);
    $releasesCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`videos` WHERE entity_type = 'artist' AND entity_id = ?");
    $stmt->execute([$artistId]);
    $videosCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE entity_type = 'artist' AND entity_id = ?");
    $stmt->execute([$artistId]);
    $showsCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

// Calculate profile completeness
$profileFields = ['name', 'bio', 'image_url', 'website', 'facebook_url', 'instagram_url', 'spotify_url'];
$filledFields = 0;
foreach ($profileFields as $field) {
    if (!empty($entity[$field])) $filledFields++;
}
$profileCompleteness = round(($filledFields / count($profileFields)) * 100);

// Check connected accounts
$hasFacebook = !empty($user['FacebookId']);
$hasInstagram = !empty($user['InstagramId']);
$hasTiktok = !empty($user['TiktokId']);
$hasYoutube = !empty($user['YoutubeId']);
$hasSpotify = !empty($user['SpotifyId']);

// Score breakdown categories with real data
$scoreBreakdown = [
    'radio' => [
        'label' => 'Radio Spins',
        'icon' => 'bi-broadcast',
        'color' => '#1DB954',
        'weight' => 0.30,
        'items' => [
            ['name' => 'NGN Station Spins', 'value' => 0, 'max' => 100, 'points' => 0],
            ['name' => 'SMR Chart Spins', 'value' => 0, 'max' => 100, 'points' => 0],
        ]
    ],
    'social' => [
        'label' => 'Social Media',
        'icon' => 'bi-share',
        'color' => '#00d4ff',
        'weight' => 0.25,
        'items' => [
            ['name' => 'Facebook Connected', 'value' => $hasFacebook ? 100 : 0, 'max' => 100, 'points' => $hasFacebook ? 50 : 0],
            ['name' => 'Instagram Connected', 'value' => $hasInstagram ? 100 : 0, 'max' => 100, 'points' => $hasInstagram ? 50 : 0],
            ['name' => 'TikTok Connected', 'value' => $hasTiktok ? 100 : 0, 'max' => 100, 'points' => $hasTiktok ? 50 : 0],
            ['name' => 'YouTube Connected', 'value' => $hasYoutube ? 100 : 0, 'max' => 100, 'points' => $hasYoutube ? 50 : 0],
        ]
    ],
    'streaming' => [
        'label' => 'Streaming',
        'icon' => 'bi-spotify',
        'color' => '#a855f7',
        'weight' => 0.25,
        'items' => [
            ['name' => 'Spotify Connected', 'value' => $hasSpotify ? 100 : 0, 'max' => 100, 'points' => $hasSpotify ? 100 : 0],
            ['name' => 'Spotify Monthly Listeners', 'value' => 0, 'max' => 100, 'points' => 0],
            ['name' => 'Spotify Followers', 'value' => 0, 'max' => 100, 'points' => 0],
        ]
    ],
    'content' => [
        'label' => 'Content & Activity',
        'icon' => 'bi-collection',
        'color' => '#f59e0b',
        'weight' => 0.15,
        'items' => [
            ['name' => 'Releases (Albums/EPs)', 'value' => min($releasesCount, 20), 'max' => 20, 'points' => $releasesCount * 25],
            ['name' => 'videos', 'value' => min($videosCount, 20), 'max' => 20, 'points' => $videosCount * 15],
            ['name' => 'Shows/Events', 'value' => min($showsCount, 20), 'max' => 20, 'points' => $showsCount * 10],
            ['name' => 'Profile Completeness', 'value' => $profileCompleteness, 'max' => 100, 'points' => $profileCompleteness],
        ]
    ],
    'engagement' => [
        'label' => 'NGN Engagement',
        'icon' => 'bi-heart',
        'color' => '#ef4444',
        'weight' => 0.05,
        'items' => [
            ['name' => 'Profile Views', 'value' => 0, 'max' => 100, 'points' => 0],
            ['name' => 'Mentions in Posts', 'value' => 0, 'max' => 50, 'points' => 0],
            ['name' => 'Claimed Profile', 'value' => !empty($entity['claimed']) ? 100 : 0, 'max' => 100, 'points' => !empty($entity['claimed']) ? 1000 : 0],
        ]
    ],
];

// Calculate totals
$totalScore = 0;
$maxScore = 0;
foreach ($scoreBreakdown as $cat) {
    foreach ($cat['items'] as $item) {
        $totalScore += $item['points'];
        $maxScore += $item['max'] * $cat['weight'];
    }
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">NGN Score Breakdown</h1>
        <p class="page-subtitle">See exactly how your ranking is calculated</p>
    </header>
    
    <div class="page-content">
        <!-- Overall Score -->
        <div class="card" style="text-align: center; padding: 40px;">
            <div style="font-size: 14px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">Your NGN Score</div>
            <div style="font-family: 'Space Grotesk', sans-serif; font-size: 72px; font-weight: 700; color: var(--brand);">
                <?= number_format($totalScore) ?>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary); margin-top: 8px;">
                Current Rank: <strong style="color: var(--text-primary);"><?= $rankData['rank'] ? '#' . number_format($rankData['rank']) : 'Unranked' ?></strong> on Weekly Chart
                <?php if ($rankData['prev_rank'] && $rankData['rank']):
                    $delta = $rankData['prev_rank'] - $rankData['rank'];
                ?>
                    <?php if ($delta > 0): ?>
                        <span style="color: #22c55e; margin-left: 8px;"><i class="bi bi-arrow-up"></i> <?= $delta ?></span>
                    <?php elseif ($delta < 0): ?>
                        <span style="color: #ef4444; margin-left: 8px;"><i class="bi bi-arrow-down"></i> <?= abs($delta) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Score Bar -->
            <div style="max-width: 400px; margin: 24px auto 0;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">
                    <span>0</span>
                    <span>Potential: <?= number_format($maxScore) ?></span>
                </div>
                <div style="height: 12px; background: var(--bg-primary); border-radius: 6px; overflow: hidden;">
                    <div style="height: 100%; width: <?= $maxScore > 0 ? ($totalScore / $maxScore * 100) : 0 ?>%; background: linear-gradient(90deg, var(--brand), var(--accent)); border-radius: 6px;"></div>
                </div>
            </div>
        </div>
        
        <?php if ($isInvestor): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 2em;">🚀</div>
            <div>
                <strong>Investor Perk Unlocked:</strong> As an NGN investor, your score receives a <strong>1.05x Influence Weighting</strong> during final chart calculations. Thank you for your support!
            </div>
        </div>
        <div class="card" style="margin-top: 1rem; padding: 2rem; background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(34, 197, 94, 0.05) 100%); border: 2px solid var(--brand);">
            <h3 style="margin-top: 0; margin-bottom: 1.5rem; color: var(--brand); display: flex; align-items: center; gap: 0.5rem;">
                <i class="bi bi-percent" style="font-size: 1.3em;"></i>
                How Your Investor Status Boosts Your Score
            </h3>

            <!-- Interactive Score Comparison -->
            <div style="display: grid; grid-template-columns: 1fr 0.5fr 1fr; gap: 2rem; margin-bottom: 2rem; align-items: center;">
                <!-- Before -->
                <div style="text-align: center;">
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 1.5rem; text-transform: uppercase;">Base Score</p>
                    <div style="position: relative; height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; margin-bottom: 1rem;">
                        <div style="width: 100%; background: linear-gradient(180deg, rgba(156, 163, 175, 0.5) 0%, rgba(156, 163, 175, 0.3) 100%); border-radius: 8px 8px 0 0; height: 120px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-weight: 600; font-size: 1.3em;">
                            <?= number_format($totalScore) ?>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Your current score</div>
                </div>

                <!-- Multiplier -->
                <div style="text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 200px;">
                    <div style="font-size: 2.5em; font-weight: 700; color: var(--brand); line-height: 1;">×</div>
                    <div style="font-size: 1.3em; font-weight: 600; color: var(--brand); margin-top: 0.5rem;">1.05</div>
                </div>

                <!-- After (with boost) -->
                <div style="text-align: center;">
                    <p style="font-size: 0.85rem; color: var(--brand); font-weight: 600; margin-bottom: 1.5rem; text-transform: uppercase;">Boosted Score</p>
                    <div style="position: relative; height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; margin-bottom: 1rem;">
                        <div style="width: 100%; background: linear-gradient(180deg, var(--brand) 0%, rgba(34, 197, 94, 0.7) 100%); border-radius: 8px 8px 0 0; height: 100%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 1.3em;">
                            <?= number_format((int)($totalScore * 1.05)) ?>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">With investor boost</div>
                </div>
            </div>

            <!-- Boost Amount Highlight -->
            <div style="background: var(--bg-primary); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; text-align: center; border: 2px dashed var(--brand);">
                <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Bonus Points</p>
                <p style="margin: 0; font-size: 2em; font-weight: 700; color: var(--brand); font-family: 'Space Grotesk', monospace;">
                    +<?= number_format((int)($totalScore * 0.05)) ?> <span style="font-size: 0.6em; color: var(--text-secondary);">pts</span>
                </p>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">5% additional score applied to all rankings</p>
            </div>

            <!-- Explanation -->
            <div style="background: var(--bg-primary); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid var(--brand);">
                <p style="margin: 0; font-size: 0.95rem; color: var(--text-primary);">
                    <strong>📊 How this works:</strong> Your 1.05x weighting is applied to your final NGN Score across all ranking windows (daily, weekly, monthly). This gives you a consistent 5% advantage in the discovery algorithm, helping your music reach more listeners and climb the charts faster.
                </p>
            </div>

            <!-- Benefits Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div style="padding: 1.5rem; background: var(--bg-primary); border-radius: 8px; border-top: 3px solid var(--brand);">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0 0 0.5rem 0; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">📈 Ranking Advantage</p>
                    <p style="margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--brand);">Higher Visibility</p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">Better positioning in all NGN charts and discovery features</p>
                </div>
                <div style="padding: 1.5rem; background: var(--bg-primary); border-radius: 8px; border-top: 3px solid var(--accent);">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0 0 0.5rem 0; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">⭐ Growth Accelerator</p>
                    <p style="margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--accent);">Consistent Impact</p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">5% boost compounds with your organic growth over time</p>
                </div>
                <div style="padding: 1.5rem; background: var(--bg-primary); border-radius: 8px; border-top: 3px solid #f59e0b;">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0 0 0.5rem 0; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">🎯 Long-term Value</p>
                    <p style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #f59e0b;">Permanent Benefit</p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">Your investor status keeps boosting your score indefinitely</p>
                </div>
            </div>

            <div style="background: linear-gradient(90deg, rgba(29, 185, 84, 0.1) 0%, rgba(34, 197, 94, 0.05) 100%); padding: 1.5rem; border-radius: 8px; margin-top: 2rem; text-align: center;">
                <p style="margin: 0; font-size: 0.9rem; color: var(--text-primary);">
                    <i class="bi bi-heart-fill" style="color: var(--brand); margin-right: 0.5rem;"></i>
                    <strong>Thank you for believing in NextGenNoise.</strong> Your investment helps us build better tools for creators like you.
                </p>
            </div>
        </div>
        <?php else: ?>
        <div class="alert" style="background: linear-gradient(135deg, rgba(0, 212, 255, 0.1) 0%, rgba(102, 126, 234, 0.1) 100%); border: 2px dashed var(--accent); display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.8em;">💡</div>
            <div>
                <strong>Become an Investor:</strong> Support NextGenNoise and unlock a 1.05x Influence Weighting on your NGN Score. <a href="/investments" style="color: var(--brand); font-weight: 600; text-decoration: none;">Learn more about investing →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category Breakdown -->
        <div style="display: grid; gap: 24px;">
            <?php foreach ($scoreBreakdown as $key => $category): 
                $catTotal = array_sum(array_column($category['items'], 'points'));
                $catMax = array_sum(array_column($category['items'], 'max')) * $category['weight'];
            ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="<?= $category['icon'] ?>" style="color: <?= $category['color'] ?>;"></i>
                        <?= $category['label'] ?>
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 8px;">
                            (<?= $category['weight'] * 100 ?>% of total)
                        </span>
                    </h2>
                    <div style="font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700; color: <?= $category['color'] ?>;">
                        <?= number_format($catTotal) ?> pts
                    </div>
                </div>
                
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($category['items'] as $item): 
                        $pct = $item['max'] > 0 ? ($item['value'] / $item['max'] * 100) : 0;
                    ?>
                    <div style="display: flex; align-items: center; gap: 16px; padding: 12px; background: var(--bg-primary); border-radius: 8px;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <span style="font-size: 13px; font-weight: 500;"><?= $item['name'] ?></span>
                                <span style="font-size: 13px; color: var(--text-muted);">
                                    <?= number_format($item['value']) ?> / <?= number_format($item['max']) ?>
                                </span>
                            </div>
                            <div style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $pct ?>%; background: <?= $category['color'] ?>; border-radius: 3px;"></div>
                            </div>
                        </div>
                        <div style="width: 60px; text-align: right; font-weight: 600; color: <?= $category['color'] ?>;">
                            +<?= number_format($item['points']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- How to Improve -->
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%); border-color: var(--brand);">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-lightbulb" style="color: var(--warning);"></i> How to Improve Your Score</h2>
            </div>
            
            <div class="grid grid-2">
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--text-primary);">Quick Wins</h4>
                    <ul style="list-style: none; font-size: 13px; color: var(--text-secondary);">
                        <li style="margin-bottom: 8px;"><i class="bi bi-check-circle" style="color: var(--brand);"></i> Complete your profile (bio, image, links)</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-check-circle" style="color: var(--brand);"></i> Connect your social media accounts</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-check-circle" style="color: var(--brand);"></i> Add your releases and songs</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-check-circle" style="color: var(--brand);"></i> Link your Spotify artist profile</li>
                    </ul>
                </div>
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--text-primary);">Long-term Growth</h4>
                    <ul style="list-style: none; font-size: 13px; color: var(--text-secondary);">
                        <li style="margin-bottom: 8px;"><i class="bi bi-arrow-up-circle" style="color: var(--accent);"></i> Get played on NGN partner stations</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-arrow-up-circle" style="color: var(--accent);"></i> Grow your Spotify monthly listeners</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-arrow-up-circle" style="color: var(--accent);"></i> Post consistently on social media</li>
                        <li style="margin-bottom: 8px;"><i class="bi bi-arrow-up-circle" style="color: var(--accent);"></i> Book and promote live shows</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

