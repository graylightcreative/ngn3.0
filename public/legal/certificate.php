<?php
/**
 * Digital Safety Seal - "The Fortress" Certificate Verification Page
 *
 * Premium public verification page for NGN Digital Safety Seal certificates.
 * Displays artist ownership proof with institutional authority and security theater.
 *
 * Pattern: Public-facing page with inline CSS
 * Accepts: ?id={certificate_id} or ?hash={sha256}
 *
 * Features:
 * 1. DNA Visualization - SHA-256 hash as visual security proof
 * 2. Artist Data Integrity - Verified Human Master badge, NGN Score
 * 3. Institutional Social Proof - Truth Layer badge, QR code, social share
 * 4. 90/10 Transparency - NGN Mandate explanation
 * 5. Mobile/Print Optimization - Responsive design, PDF export ready
 */

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\ContentLedgerService;
use NGN\Lib\Logging\LoggerFactory;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Initialize services
$config = new Config();
$pdo = ConnectionFactory::read($config);
$logger = LoggerFactory::create($config, 'certificate_view');

// Get parameters
$certificateId = isset($_GET['id']) ? trim($_GET['id']) : null;
$contentHash = isset($_GET['hash']) ? trim($_GET['hash']) : null;

// Validate inputs
if (!$certificateId && !$contentHash) {
    http_response_code(400);
    exit("Missing required parameter: 'id' or 'hash'");
}

if ($certificateId && !preg_match('/^CRT-\d{8}-[A-F0-9]{8}$/i', $certificateId)) {
    http_response_code(400);
    exit("Invalid certificate ID format");
}

if ($contentHash && !preg_match('/^[a-f0-9]{64}$/i', $contentHash)) {
    http_response_code(400);
    exit("Invalid hash format");
}

// Normalize hash to lowercase
if ($contentHash) {
    $contentHash = strtolower($contentHash);
}

// Lookup ledger entry
try {
    $ledgerService = new ContentLedgerService($pdo, $config, $logger);

    $ledger = null;
    if ($certificateId) {
        $ledger = $ledgerService->lookupByCertificateId($certificateId);
    } elseif ($contentHash) {
        $ledger = $ledgerService->lookupByHash($contentHash);
    }

    if (!$ledger) {
        http_response_code(404);
        exit("Certificate not found");
    }

    // Get artist information
    $artistStmt = $pdo->prepare("
        SELECT id, name, slug, verified, claimed, bio, image_url
        FROM ngn_2025.artists
        WHERE id = :owner_id
        LIMIT 1
    ");
    $artistStmt->execute([':owner_id' => $ledger['owner_id']]);
    $artist = $artistStmt->fetch(PDO::FETCH_ASSOC);

    if (!$artist) {
        $artist = [
            'id' => $ledger['owner_id'],
            'name' => $ledger['artist_name'] ?? 'Unknown Artist',
            'slug' => null,
            'verified' => false,
            'claimed' => false,
            'bio' => null,
            'image_url' => null
        ];
    }

    // Get NGN Score and ranking
    $scoreStmt = $pdo->prepare("
        SELECT score, ranking
        FROM ngn_2025.entity_scores
        WHERE entity_type = 'artist' AND entity_id = :entity_id
        LIMIT 1
    ");
    $scoreStmt->execute([':entity_id' => (int)$artist['id']]);
    $score = $scoreStmt->fetch(PDO::FETCH_ASSOC);

    if (!$score) {
        $score = ['score' => 0, 'ranking' => null];
    }

    // Generate QR code linking to this certificate
    $currentUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $qrCode = (new QRCode(new QROptions([
        'version' => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'scale' => 10,
        'imageBase64' => true
    ])))->render($currentUrl);

    // Increment verification count (non-blocking)
    try {
        $ledgerService->incrementVerificationCount(
            (int)$ledger['id'],
            'public_web',
            'match',
            [
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'request_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_referer' => $_SERVER['HTTP_REFERER'] ?? null,
                'request_metadata' => json_encode([
                    'viewed_at' => date('c'),
                    'platform' => 'web_certificate_page'
                ])
            ]
        );
    } catch (\Exception $e) {
        $logger->warning('verification_increment_failed', ['error' => $e->getMessage()]);
        // Don't break the page if logging fails
    }

    // Format file size
    $fileSize = $ledger['file_size_bytes'] ?? 0;
    $fileSizeFormatted = $fileSize > 0
        ? (
            $fileSize >= 1073741824 ? round($fileSize / 1073741824, 2) . ' GB' :
            ($fileSize >= 1048576 ? round($fileSize / 1048576, 2) . ' MB' :
            ($fileSize >= 1024 ? round($fileSize / 1024, 2) . ' KB' :
            $fileSize . ' B'))
        )
        : 'Unknown';

    // Format timestamp
    $certificateDate = isset($ledger['created_at'])
        ? date('F d, Y \a\t H:i:s \U\T\C', strtotime($ledger['created_at']))
        : 'Unknown';

    // Determine status color
    $statusColor = 'green';
    $statusText = 'VERIFIED';
    if ($ledger['status'] === 'disputed') {
        $statusColor = 'red';
        $statusText = 'DISPUTED';
    } elseif ($ledger['status'] === 'revoked') {
        $statusColor = 'orange';
        $statusText = 'REVOKED';
    }

} catch (\Exception $e) {
    $logger->error('certificate_page_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    exit("Error loading certificate");
}

// Determine page title
$pageTitle = htmlspecialchars($ledger['title'] ?? 'Certificate') . ' | NGN Digital Safety Seal';
$pageDescription = 'Verify this content\'s ownership and integrity on the NGN Digital Safety Seal.';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $pageDescription ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ledger['title'] ?? 'Certificate') ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:type" content="website">

    <title><?= $pageTitle ?></title>

    <style>
        /* === RESET & FOUNDATIONS === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SUSE', 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0b1020 0%, #1c2642 100%);
            color: #f8fafc;
            line-height: 1.6;
            min-height: 100vh;
        }

        :root {
            --bg-primary: #0b1020;
            --bg-secondary: #1c2642;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --accent-green: #1DB954;
            --accent-red: #ef4444;
            --accent-orange: #f97316;
            --border-color: #334155;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        a {
            color: var(--accent-green);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: #1ed760;
            text-decoration: underline;
        }

        button {
            background: var(--accent-green);
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        button:hover {
            background: #1ed760;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        button:active {
            transform: translateY(0);
        }

        /* === SEAL ANIMATION (PILLAR 1) === */
        .seal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: linear-gradient(135deg, #0b1020 0%, #1c2642 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.6s ease;
        }

        .seal-container.verified {
            opacity: 0;
            pointer-events: none;
        }

        .seal-icon {
            font-size: 80px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }

        .seal-text {
            font-size: 20px;
            font-weight: 600;
            color: var(--accent-green);
            letter-spacing: 2px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* === MAIN CONTENT === */
        .certificate-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .certificate-content {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
            padding: 40px 20px;
            animation: fadeInUp 0.8s ease 1.5s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* === PILLAR 1: DNA VISUALIZATION === */
        .seal-badge {
            text-align: center;
            margin-bottom: 40px;
            animation: slideDown 0.8s ease 1.6s both;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verified-status {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(29, 185, 84, 0.1);
            border: 2px solid var(--accent-green);
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
            color: var(--accent-green);
            margin-bottom: 20px;
        }

        .verified-status.disputed {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--accent-red);
            color: var(--accent-red);
        }

        .verified-status.revoked {
            background: rgba(249, 115, 22, 0.1);
            border-color: var(--accent-orange);
            color: var(--accent-orange);
        }

        .dna-section {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .dna-section h2 {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .dna-hash {
            font-family: 'Courier New', 'Monaco', monospace;
            font-size: 13px;
            letter-spacing: 0.05em;
            line-height: 1.8;
            word-break: break-all;
            color: var(--accent-green);
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 4px;
            border-left: 3px solid var(--accent-green);
            font-weight: 500;
            user-select: all;
        }

        .dna-hash.disputed {
            border-left-color: var(--accent-red);
            color: var(--accent-red);
        }

        .dna-hash.revoked {
            border-left-color: var(--accent-orange);
            color: var(--accent-orange);
        }

        /* === PILLAR 2: ARTIST DATA INTEGRITY === */
        .owner-section {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 40px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .owner-section h1 {
            font-size: 36px;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 700;
        }

        .owner-section h2 {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-weight: 400;
        }

        .verified-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(29, 185, 84, 0.15);
            border: 1px solid var(--accent-green);
            border-radius: 4px;
            color: var(--accent-green);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }

        .ngn-score {
            font-size: 16px;
            color: var(--text-secondary);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .ngn-score strong {
            color: var(--accent-green);
            font-weight: 700;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .metadata-item {
            text-align: left;
        }

        .metadata-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .metadata-value {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-word;
        }

        /* === PILLAR 3: SOCIAL PROOF & QR CODE === */
        .proof-section {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 40px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .proof-section h2 {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        .qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .qr-image {
            width: 250px;
            height: 250px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
        }

        .qr-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 15px;
            letter-spacing: 0.5px;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
            margin: 0 auto;
        }

        .action-buttons button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .share-button {
            background: rgba(29, 185, 84, 0.2);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }

        .share-button:hover {
            background: var(--accent-green);
            color: #000;
        }

        /* === PILLAR 4: 90/10 TRANSPARENCY === */
        .mandate-section {
            background: linear-gradient(135deg, rgba(29, 185, 84, 0.1), rgba(29, 185, 84, 0.05));
            border: 1px solid var(--accent-green);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .mandate-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .mandate-section h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 10px;
        }

        .mandate-section p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.8;
        }

        .mandate-section a {
            display: inline-block;
            margin-top: 10px;
            color: var(--accent-green);
            font-weight: 600;
            font-size: 13px;
        }

        /* === FOOTER & UTILITIES === */
        .utility-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
        }

        .utility-buttons button {
            font-size: 13px;
            padding: 10px 18px;
        }

        .copy-button {
            background: rgba(29, 185, 84, 0.2);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }

        .copy-button:hover {
            background: var(--accent-green);
            color: #000;
        }

        .print-button {
            background: rgba(100, 116, 139, 0.3);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .print-button:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
            font-size: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 40px;
        }

        .footer a {
            color: var(--accent-green);
        }

        /* === MODAL FOR EMBED CODE === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 5000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: auto;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .embed-code {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--accent-green);
            word-break: break-all;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
            user-select: all;
        }

        /* === RESPONSIVE DESIGN (PILLAR 5) === */
        @media (max-width: 768px) {
            .certificate-content {
                padding: 20px 15px;
            }

            .dna-section,
            .owner-section,
            .proof-section,
            .mandate-section {
                padding: 20px;
            }

            .owner-section h1 {
                font-size: 24px;
            }

            .owner-section h2 {
                font-size: 16px;
            }

            .metadata-grid {
                grid-template-columns: 1fr;
            }

            .dna-hash {
                font-size: 11px;
                letter-spacing: 0.02em;
            }

            .qr-image {
                width: 200px;
                height: 200px;
            }

            .action-buttons {
                max-width: 100%;
            }
        }

        /* === INSTAGRAM STORY FORMAT (9:16) === */
        @media (max-width: 480px) {
            body {
                background: linear-gradient(135deg, #0b1020 0%, #1c2642 100%);
            }

            .certificate-content {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 20px 15px;
            }

            .certificate-wrapper {
                min-height: auto;
            }

            .seal-icon {
                font-size: 60px;
            }

            .owner-section h1 {
                font-size: 20px;
            }

            .owner-section h2 {
                font-size: 14px;
            }

            .dna-section {
                padding: 15px;
            }

            .dna-hash {
                font-size: 10px;
                padding: 12px;
            }

            .qr-image {
                width: 150px;
                height: 150px;
            }

            .metadata-grid {
                gap: 12px;
            }
        }

        /* === PRINT OPTIMIZATION === */
        @media print {
            .seal-container,
            .action-buttons,
            .utility-buttons,
            .footer,
            .modal {
                display: none !important;
            }

            body {
                background: white;
                color: #000;
            }

            .certificate-content {
                animation: none;
                padding: 20px;
            }

            .dna-section,
            .owner-section,
            .proof-section,
            .mandate-section {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ccc;
                background: white;
                color: #000;
                backdrop-filter: none;
            }

            .dna-hash {
                border-left: 3px solid #000;
                background: #f5f5f5;
                color: #000;
            }

            .verified-status {
                border: 2px solid #1DB954;
                color: #000;
                background: #f0f9f6;
            }

            .qr-image {
                background: white;
                width: 4cm;
                height: 4cm;
            }

            a {
                color: #0066cc;
            }

            .verified-badge {
                background: #f0f9f6;
                border: 1px solid #1DB954;
                color: #000;
            }

            .ngn-score {
                color: #000;
            }

            .ngn-score strong {
                color: #000;
                font-weight: 700;
            }
        }
    </style>
</head>
<body>
    <!-- SEAL ANIMATION -->
    <div class="seal-container verifying">
        <div class="seal-icon">🔐</div>
        <div class="seal-text">Verifying...</div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="certificate-wrapper">
        <div class="certificate-content">

            <!-- SEAL BADGE -->
            <div class="seal-badge">
                <div class="verified-status <?= ($ledger['status'] === 'disputed' ? 'disputed' : ($ledger['status'] === 'revoked' ? 'revoked' : '')) ?>">
                    ✓ <?= htmlspecialchars($statusText) ?>
                </div>
            </div>

            <!-- DNA VISUALIZATION (PILLAR 1) -->
            <section class="dna-section">
                <h2>Digital DNA</h2>
                <div class="dna-hash <?= ($ledger['status'] === 'disputed' ? 'disputed' : ($ledger['status'] === 'revoked' ? 'revoked' : '')) ?>">
                    <?= htmlspecialchars($ledger['content_hash']) ?>
                </div>
            </section>

            <!-- ARTIST DATA INTEGRITY (PILLAR 2) -->
            <section class="owner-section">
                <?php if ($artist['verified']): ?>
                    <div class="verified-badge">✓ Verified Human Master</div>
                <?php elseif ($artist['claimed']): ?>
                    <div class="verified-badge">Claimed Artist</div>
                <?php endif; ?>

                <h1><?= htmlspecialchars($ledger['title'] ?? 'Untitled') ?></h1>
                <h2><?= htmlspecialchars($artist['name']) ?></h2>

                <div class="ngn-score">
                    NGN Score: <strong><?= number_format($score['score'], 0) ?></strong>
                    <?php if ($score['ranking']): ?>
                        | Rank: <strong>#<?= number_format($score['ranking']) ?> Artist</strong>
                    <?php endif; ?>
                </div>

                <div class="metadata-grid">
                    <div class="metadata-item">
                        <div class="metadata-label">File Size</div>
                        <div class="metadata-value"><?= htmlspecialchars($fileSizeFormatted) ?></div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">File Type</div>
                        <div class="metadata-value"><?= htmlspecialchars($ledger['mime_type'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">Certificate ID</div>
                        <div class="metadata-value" style="font-family: 'Courier New', monospace; font-size: 12px;">
                            <?= htmlspecialchars($ledger['certificate_id']) ?>
                        </div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">Registered</div>
                        <div class="metadata-value"><?= htmlspecialchars($certificateDate) ?></div>
                    </div>
                </div>
            </section>

            <!-- SOCIAL PROOF & QR CODE (PILLAR 3) -->
            <section class="proof-section">
                <h2>Verification Code</h2>

                <div class="qr-container">
                    <img src="<?= htmlspecialchars($qrCode) ?>" alt="Certificate QR Code" class="qr-image">
                    <div class="qr-label">Scan to verify this certificate</div>
                </div>

                <div class="action-buttons">
                    <button class="share-button" onclick="copyUrl()">📋 Copy Certificate URL</button>
                    <button class="share-button" onclick="shareToTwitter()">𝕏 Share on Twitter</button>
                    <button class="share-button" onclick="shareToFacebook()">f Share on Facebook</button>
                </div>
            </section>

            <!-- 90/10 TRANSPARENCY (PILLAR 4) -->
            <section class="mandate-section">
                <div class="mandate-icon">⚖️</div>
                <h3>Protected Under NGN 90/10 Mandate</h3>
                <p>
                    This content is registered in the NGN Digital Safety Seal. The artist retains <strong>90%</strong> of all revenue,
                    with 10% supporting platform operations. No middlemen. No label cuts. Pure artist empowerment.
                </p>
                <a href="/legal/mandate">Learn More About NGN 90/10 Mandate →</a>
            </section>

            <!-- UTILITY BUTTONS -->
            <div class="utility-buttons">
                <button class="copy-button" onclick="copyUrl()">Copy URL</button>
                <button class="print-button" onclick="window.print()">🖨️ Print Certificate</button>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p>
                Digital Safety Seal Certificate | NGN 2.0.2 |
                <a href="/legal/privacy">Privacy Policy</a> |
                <a href="/legal/mandate">NGN Mandate</a>
            </p>
            <p style="margin-top: 10px; font-size: 11px;">
                This certificate verifies content ownership and integrity on the NGN ledger.
                Learn more at <a href="https://nextgennoise.com">nextgennoise.com</a>
            </p>
        </div>
    </div>

    <!-- EMBED MODAL -->
    <div class="modal" id="embedModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Embed Badge</span>
                <button class="modal-close" onclick="closeEmbedModal()">×</button>
            </div>
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">
                Copy this code to embed the Truth Layer badge on your website:
            </p>
            <div class="embed-code" id="embedCodeContent"></div>
            <button onclick="copyEmbedCode()" style="width: 100;">Copy Code</button>
        </div>
    </div>

    <script>
        // Seal animation (1.5s delay before reveal)
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelector('.seal-container').classList.add('verified');
            }, 1500);
        });

        // Copy URL to clipboard
        function copyUrl() {
            var url = window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    alert('Certificate URL copied to clipboard!');
                }).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }

        function fallbackCopy(text) {
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('Certificate URL copied to clipboard!');
            } catch (err) {
                alert('Failed to copy URL');
            }
            document.body.removeChild(textArea);
        }

        // Share to Twitter
        function shareToTwitter() {
            var text = encodeURIComponent('Just verified this content on NGN Digital Safety Seal:\n' + window.location.href + '\n\n90% to the artist. Always. 🎵');
            window.open('https://x.com/intent/tweet?text=' + text, '_blank', 'width=550,height=420');
        }

        // Share to Facebook
        function shareToFacebook() {
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href), '_blank', 'width=550,height=420');
        }

        // Embed modal
        function showEmbedModal() {
            var certificateId = '<?= htmlspecialchars($ledger['certificate_id']) ?>';
            var embedCode = '<iframe src="https://<?= $_SERVER['HTTP_HOST'] ?>/legal/certificate.php?id=' + certificateId + '" width="100%" height="600" frameborder="0" style="border-radius: 8px;"></iframe>';
            document.getElementById('embedCodeContent').textContent = embedCode;
            document.getElementById('embedModal').classList.add('active');
        }

        function closeEmbedModal() {
            document.getElementById('embedModal').classList.remove('active');
        }

        function copyEmbedCode() {
            var embedCode = document.getElementById('embedCodeContent');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(embedCode.textContent).then(function() {
                    alert('Embed code copied to clipboard!');
                }).catch(function() {
                    fallbackCopyEmbed();
                });
            } else {
                fallbackCopyEmbed();
            }
        }

        function fallbackCopyEmbed() {
            var embedCode = document.getElementById('embedCodeContent');
            var range = document.createRange();
            range.selectNodeContents(embedCode);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            try {
                document.execCommand('copy');
                alert('Embed code copied to clipboard!');
            } catch (err) {
                alert('Failed to copy code');
            }
        }

        // Close modal when clicking outside
        document.getElementById('embedModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeEmbedModal();
            }
        });
    </script>
</body>
</html>
