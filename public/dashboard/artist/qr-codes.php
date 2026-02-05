<?php
/**
 * Artist Dashboard - QR Codes Management
 * (Bible Ch. 7.3 QR Code System: Generate QR codes for artist profile and releases)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');

$pageTitle = 'QR Code Management';
$currentPage = 'qr-codes';

// Mock data for demonstration (Bible Ch. 7.3 - QR codes for artists and releases)
$mockQrCodes = [
    [
        'id' => 1,
        'entityType' => 'artist',
        'entityId' => $entity['id'] ?? 1,
        'entityName' => $entity['name'] ?? 'My Artist Profile',
        'imageUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://nextgennoise.com/artists/' . ($entity['slug'] ?? 'artist'),
        'downloadUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https://nextgennoise.com/artists/' . ($entity['slug'] ?? 'artist') . '&download=1',
        'generatedDate' => date('Y-m-d'),
    ],
    [
        'id' => 2,
        'entityType' => 'release',
        'entityId' => 101,
        'entityName' => 'My Latest Album',
        'imageUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://nextgennoise.com/releases/my-latest-album',
        'downloadUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https://nextgennoise.com/releases/my-latest-album&download=1',
        'generatedDate' => date('Y-m-d', strtotime('-7 days')),
    ],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle">Generate and manage QR codes for your profile, releases, and music. Share on social media, print for promotional materials, or link in emails.</p>
    </header>

    <div class="page-content">
        <!-- Quick Generate Section -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Generate New QR Code</h2>
            </div>

            <div class="card-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="#" class="btn btn-primary">
                        <i class="bi bi-person-fill"></i> QR for My Profile
                    </a>
                    <a href="releases.php" class="btn btn-secondary">
                        <i class="bi bi-disc-fill"></i> QR for Release
                    </a>
                </div>
            </div>
        </div>

        <!-- Existing QR Codes -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your QR Codes</h2>
            </div>

            <div class="card-content">
                <?php if (empty($mockQrCodes)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    <p><i class="bi bi-qr-code" style="font-size: 2rem; opacity: 0.5;"></i></p>
                    <p>No QR codes generated yet. Create one above to get started.</p>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($mockQrCodes as $qrCode): ?>
                    <div class="qr-code-item" style="border: 1px solid var(--border); border-radius: 0.5rem; padding: 1rem; text-align: center;">
                        <img src="<?= htmlspecialchars($qrCode['imageUrl']) ?>" alt="QR Code for <?= htmlspecialchars($qrCode['entityName']) ?>" style="max-width: 150px; height: auto; margin: 0 auto 1rem auto; border: 1px solid #eee;">
                        <h3 style="font-size: 1rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($qrCode['entityName']) ?></h3>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= ucfirst($qrCode['entityType']) ?></p>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">Generated: <?= htmlspecialchars($qrCode['generatedDate']) ?></p>
                        <a href="<?= htmlspecialchars($qrCode['downloadUrl']) ?>" class="btn btn-secondary btn-sm" download>Download</a>
                        <button class="btn btn-danger btn-sm" style="margin-left: 0.5rem;">Delete</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage Tips -->
        <div class="card mt-8">
            <div class="card-header">
                <h2 class="card-title">Tips for Using QR Codes</h2>
            </div>

            <div class="card-content space-y-4">
                <div>
                    <h3 class="font-medium mb-2">Social Media</h3>
                    <p class="text-sm text-muted">Share QR codes in your Instagram bio, TikTok bio link, or Twitter pinned tweet to drive traffic directly to your profile or latest release.</p>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Promotional Materials</h3>
                    <p class="text-sm text-muted">Print QR codes on flyers, posters, album covers, or merchandise to let fans easily access your music and subscribe to your tiers.</p>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Email & Newsletters</h3>
                    <p class="text-sm text-muted">Include QR codes in emails to fan subscribers for quick mobile access to your latest content and exclusive releases.</p>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Live Events</h3>
                    <p class="text-sm text-muted">Display QR codes at shows and performances to let audience members instantly follow you and discover your music.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
