<?php
/**
 * Venue Dashboard - QR Codes Management
 * (Bible Ch. 7.3 QR Code System & V.2 QR Promotion)
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');

$pageTitle = 'QR Code Management';
$currentPage = 'qr-codes'; // For sidebar highlighting

// Mock data for demonstration (Bible Ch. 7.3 - QR codes for venues)
$mockQrCodes = [
    [
        'id' => 1,
        'entityType' => 'show',
        'entityId' => 101,
        'entityName' => 'Rock Night at The Venue',
        'imageUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://nextgennoise.com/show/rock-night-at-the-venue',
        'downloadUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https://nextgennoise.com/show/rock-night-at-the-venue&download=1',
        'generatedDate' => '2026-01-10',
    ],
    [
        'id' => 2,
        'entityType' => 'event',
        'entityId' => 205,
        'entityName' => 'Summer Music Festival',
        'imageUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://nextgennoise.com/event/summer-music-festival',
        'downloadUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https://nextgennoise.com/event/summer-music-festival&download=1',
        'generatedDate' => '2025-11-20',
    ],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle">View and manage QR codes generated for your shows and events. Perfect for printing at your door or in promotions (Bible V.2)</p>
    </header>

    <div class="page-content">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Generated QR Codes</h2>
            </div>

            <div class="card-content">
                <?php if (empty($mockQrCodes)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    <p><i class="bi bi-qr-code" style="font-size: 2rem; opacity: 0.5;"></i></p>
                    <p>No QR codes generated yet. Generate one from a show details page.</p>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($mockQrCodes as $qrCode): ?>
                    <div class="qr-code-item" style="border: 1px solid var(--border); border-radius: 0.5rem; padding: 1rem; text-align: center;">
                        <img src="<?= htmlspecialchars($qrCode['imageUrl']) ?>" alt="QR Code for <?= htmlspecialchars($qrCode['entityName']) ?>" style="max-width: 150px; height: auto; margin: 0 auto 1rem auto; border: 1px solid #eee;">
                        <h3 style="font-size: 1rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($qrCode['entityName']) ?></h3>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">Generated: <?= htmlspecialchars($qrCode['generatedDate']) ?></p>
                        <a href="<?= htmlspecialchars($qrCode['downloadUrl']) ?>" class="btn btn-secondary btn-sm" download>Download</a>
                        <button class="btn btn-danger btn-sm" style="margin-left: 0.5rem;">Delete</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top: 2rem; text-align: center;">
                    <a href="shows.php" class="btn btn-primary">Generate New QR Code for a Show</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
