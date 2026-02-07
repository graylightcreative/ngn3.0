<?php

namespace NGN\Lib\Legal;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * DigitalCertificateService
 *
 * Generates professional HTML certificates with embedded QR codes for content ownership.
 * Certificates can be printed, displayed, and scanned for verification.
 *
 * Features:
 * - Professional template with print CSS
 * - Embedded QR code linking to verification API
 * - Artist-friendly design with watermark and seal
 * - Print button with auto-open dialog
 * - Responsive layout for web and print
 */
class DigitalCertificateService
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Generate professional HTML certificate with embedded QR code
     *
     * @param array $ledgerRecord Content ledger entry (from ContentLedgerService)
     * @param array $ownerInfo Owner/artist information (id, name, email)
     * @return string HTML certificate markup
     */
    public function generateCertificateHtml(array $ledgerRecord, array $ownerInfo): string
    {
        $certificateId = htmlspecialchars($ledgerRecord['certificate_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $contentHash = htmlspecialchars($ledgerRecord['content_hash'] ?? '', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($ledgerRecord['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
        $artistName = htmlspecialchars($ledgerRecord['artist_name'] ?? $ownerInfo['name'] ?? 'Artist', ENT_QUOTES, 'UTF-8');
        $ownerName = htmlspecialchars($ownerInfo['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $fileSizeBytes = $ledgerRecord['file_size_bytes'] ?? 0;
        $fileSizeMB = round($fileSizeBytes / (1024 * 1024), 2);
        $mimeType = htmlspecialchars($ledgerRecord['mime_type'] ?? '', ENT_QUOTES, 'UTF-8');
        $createdAt = $ledgerRecord['created_at'] ?? date('Y-m-d H:i:s');
        $formattedDate = date('F d, Y \a\t g:i A', strtotime($createdAt));

        // Generate QR code as base64-encoded PNG
        $verificationUrl = $this->getVerificationUrl($certificateId);
        $qrCodeBase64 = $this->generateQrCodeBase64($verificationUrl);

        // Short hash preview (first 8 chars)
        $hashPreview = substr($contentHash, 0, 8) . '...';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital DNA Certificate - $certificateId</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }

        .certificate-container {
            max-width: 850px;
            margin: 20px auto;
            background: white;
            padding: 60px 50px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        /* Decorative background watermark */
        .certificate-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.03) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        .seal-icon {
            display: inline-block;
            font-size: 48px;
            margin-bottom: 15px;
        }

        .certificate-title {
            font-size: 32px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .certificate-subtitle {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(to right, transparent, #3b82f6, transparent);
            margin: 20px auto;
        }

        .content-section {
            position: relative;
            z-index: 1;
            margin-bottom: 40px;
        }

        .content-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .content-value {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
            padding: 12px;
            background: #f9fafb;
            border-left: 3px solid #3b82f6;
            padding-left: 15px;
        }

        .certificate-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
            position: relative;
            z-index: 1;
        }

        .info-group {
            text-align: left;
        }

        .info-group.full {
            grid-column: 1 / -1;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 1px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .info-value {
            font-size: 14px;
            color: #1f2937;
            word-break: break-all;
            font-family: 'Courier New', monospace;
        }

        .hash-display {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 12px;
            word-break: break-all;
        }

        .qr-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 40px 0;
            padding: 30px;
            background: #f9fafb;
            border-radius: 8px;
            position: relative;
            z-index: 1;
        }

        .qr-code {
            flex-shrink: 0;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
            border: 2px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px;
            background: white;
        }

        .qr-info {
            flex: 1;
            margin-left: 30px;
        }

        .qr-info-title {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .qr-info-text {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .verification-url {
            font-size: 11px;
            color: #3b82f6;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            margin-top: 8px;
        }

        .certificate-footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .footer-text {
            font-size: 11px;
            color: #9ca3af;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .footer-seal {
            display: inline-block;
            padding: 12px 20px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: background 0.3s ease;
        }

        .print-button:hover {
            background: #2563eb;
        }

        .print-button:active {
            transform: scale(0.98);
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .certificate-container {
                max-width: 100%;
                margin: 0;
                padding: 40px 60px;
                box-shadow: none;
                page-break-after: avoid;
            }

            .print-button {
                display: none;
            }

            a {
                color: #3b82f6;
                text-decoration: underline;
            }

            .verification-url {
                text-decoration: underline;
            }
        }

        /* Mobile responsive */
        @media (max-width: 600px) {
            .certificate-container {
                padding: 30px 20px;
            }

            .certificate-title {
                font-size: 24px;
            }

            .certificate-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .qr-section {
                flex-direction: column;
                text-align: center;
            }

            .qr-info {
                margin-left: 0;
                margin-top: 20px;
            }

            .print-button {
                position: static;
                display: block;
                width: 100%;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Print Certificate</button>

    <div class="certificate-container">
        <div class="certificate-header">
            <div class="seal-icon">🔐</div>
            <div class="certificate-subtitle">Digital DNA Certificate</div>
            <h1 class="certificate-title">Ownership Verified</h1>
            <div class="divider"></div>
        </div>

        <div class="content-section">
            <div class="content-label">Content Title</div>
            <div class="content-value">$title</div>

            <div class="content-label">Artist Name</div>
            <div class="content-value">$artistName</div>
        </div>

        <div class="certificate-info">
            <div class="info-group">
                <div class="info-label">Certificate ID</div>
                <div class="info-value">$certificateId</div>
            </div>

            <div class="info-group">
                <div class="info-label">Registered Owner</div>
                <div class="info-value">$ownerName</div>
            </div>

            <div class="info-group">
                <div class="info-label">Registration Date</div>
                <div class="info-value">$formattedDate</div>
            </div>

            <div class="info-group">
                <div class="info-label">File Size</div>
                <div class="info-value">{$fileSizeMB} MB</div>
            </div>

            <div class="info-group full">
                <div class="info-label">Content Hash (SHA-256)</div>
                <div class="info-value">$hashPreview</div>
                <div class="hash-display">$contentHash</div>
            </div>

            <div class="info-group full">
                <div class="info-label">File Type</div>
                <div class="info-value">$mimeType</div>
            </div>
        </div>

        <div class="qr-section">
            <div class="qr-code">
                <img src="data:image/png;base64,$qrCodeBase64" alt="QR Code for verification">
            </div>
            <div class="qr-info">
                <div class="qr-info-title">Verify This Certificate</div>
                <div class="qr-info-text">
                    Scan the QR code with any smartphone to verify this certificate's authenticity on the NGN ledger. This ensures the content ownership is legitimate and the file has not been tampered with.
                </div>
                <div class="verification-url">
                    {$verificationUrl}
                </div>
            </div>
        </div>

        <div class="certificate-footer">
            <div class="footer-text">
                This Digital DNA Certificate proves ownership and registration of the content in the NGN (Next Generation Noise) Ledger. The QR code above can be scanned to verify the certificate's authenticity and view ownership details.
            </div>
            <div class="footer-text">
                Certificate Status: Active | Ledger: NGN 2.0.2 | System: Digital Safety Seal
            </div>
            <div class="footer-seal">
                ✓ Cryptographically Verified
            </div>
        </div>
    </div>

    <script>
        // Auto-open print dialog on load (optional - comment out if not desired)
        // window.addEventListener('load', function() {
        //     window.print();
        // });

        // Handle QR code click to copy verification URL
        document.querySelectorAll('.verification-url').forEach(el => {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function() {
                const url = this.textContent;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Verification URL copied to clipboard!');
                }).catch(() => {
                    console.log('URL: ' + url);
                });
            });
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate QR code as base64-encoded PNG
     *
     * @param string $data Content to encode in QR code
     * @return string Base64-encoded PNG image data
     */
    private function generateQrCodeBase64(string $data): string
    {
        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H, // High error correction
                'scale' => 5, // Larger modules for better scanning
                'quietZone' => 2, // Quiet zone around code
                'imageBase64' => true // Return as base64
            ]);

            $qrCode = new QRCode($options);
            $qrCodeData = $qrCode->render($data);

            // Extract base64 from data URI if needed
            if (strpos($qrCodeData, 'data:') === 0) {
                // Format: data:image/png;base64,{data}
                return substr($qrCodeData, strpos($qrCodeData, ',') + 1);
            }

            return $qrCodeData;

        } catch (\Exception $e) {
            // Fallback to placeholder if QR generation fails
            error_log('QR code generation failed: ' . $e->getMessage());
            return $this->getPlaceholderQrCode();
        }
    }

    /**
     * Get verification URL for certificate
     *
     * @param string $certificateId Certificate identifier
     * @return string Full verification URL
     */
    private function getVerificationUrl(string $certificateId): string
    {
        return "{$this->baseUrl}/api/v1/legal/verify?certificate_id=" . urlencode($certificateId);
    }

    /**
     * Get placeholder QR code (1x1 transparent PNG in base64)
     * Used as fallback if QR generation fails
     *
     * @return string Base64-encoded PNG
     */
    private function getPlaceholderQrCode(): string
    {
        // 1x1 transparent PNG
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }
}
