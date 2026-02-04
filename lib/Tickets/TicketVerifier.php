<?php
/**
 * Ticket Verifier - Cryptographic QR code validation
 *
 * Handles:
 * - QR hash generation (SHA-256 with salt)
 * - Hash verification for both online and offline modes
 * - Manifests for offline validation
 * - Redemption logging
 *
 * Security Model:
 * - Each ticket has unique salt + hash
 * - Hashes are SHA-256(event_id + user_id + qr_salt + secret_key)
 * - Offline mode uses pre-downloaded manifest of valid hashes
 * - Online mode verifies against database
 */

namespace NGN\Lib\Tickets;

use PDO;
use Exception;

class TicketVerifier
{
    private PDO $pdo;
    private string $secretKey;
    private const HASH_ALGORITHM = 'sha256';

    public function __construct(PDO $pdo, string $secretKey = '')
    {
        $this->pdo = $pdo;
        // Use environment secret or fallback
        $this->secretKey = $secretKey ?: (getenv('TICKET_SECRET_KEY') ?: 'ngn-default-key');
    }

    /**
     * Generate a QR hash for a new ticket
     *
     * Formula: SHA256(event_id | user_id | qr_salt | secret_key)
     *
     * @param string $eventId - Event UUID
     * @param int $userId - User ID
     * @param string $salt - Random salt (will be generated if not provided)
     * @return array ['qr_hash' => string, 'qr_salt' => string]
     */
    public function generateQRHash(string $eventId, int $userId, string $salt = ''): array
    {
        // Generate random salt if not provided
        if (empty($salt)) {
            $salt = bin2hex(random_bytes(32)); // 64-char hex string
        }

        // Combine components for hashing
        $hashInput = implode('|', [
            $eventId,
            $userId,
            $salt,
            $this->secretKey
        ]);

        // Generate SHA-256 hash
        $qrHash = hash(self::HASH_ALGORITHM, $hashInput);

        return [
            'qr_hash' => $qrHash,
            'qr_salt' => $salt
        ];
    }

    /**
     * Verify a scanned QR hash (online mode)
     *
     * Checks if hash exists in database and ticket is valid
     *
     * @param string $qrHash - Scanned QR hash
     * @param string $eventId - Event ID (optional - for validation)
     * @return array|null - Ticket data if valid, null if invalid
     */
    public function verifyOnline(string $qrHash, string $eventId = ''): ?array
    {
        try {
            // Look up ticket by hash
            $stmt = $this->pdo->prepare(
                'SELECT
                    t.id, t.event_id, t.user_id, t.ticket_type, t.status,
                    t.redeemed_at, t.redeemed_by, t.redeemed_location,
                    e.title as event_title, e.venue_name, e.starts_at
                FROM tickets t
                LEFT JOIN events e ON t.event_id = e.id
                WHERE t.qr_hash = ?'
            );
            $stmt->execute([$qrHash]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                return null; // Hash not found
            }

            // Validate event ID if provided
            if (!empty($eventId) && $ticket['event_id'] !== $eventId) {
                return null; // Event mismatch
            }

            // Check ticket status
            if ($ticket['status'] !== 'active') {
                return [
                    'valid' => false,
                    'ticket_id' => $ticket['id'],
                    'status' => $ticket['status'],
                    'reason' => "Ticket is {$ticket['status']}",
                    'redeemed_at' => $ticket['redeemed_at']
                ];
            }

            return [
                'valid' => true,
                'ticket_id' => $ticket['id'],
                'event_id' => $ticket['event_id'],
                'event_title' => $ticket['event_title'],
                'venue_name' => $ticket['venue_name'],
                'user_id' => $ticket['user_id'],
                'ticket_type' => $ticket['ticket_type'],
                'status' => $ticket['status'],
                'event_starts_at' => $ticket['starts_at']
            ];

        } catch (Exception $e) {
            error_log("TicketVerifier::verifyOnline error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify a QR hash in offline mode
     *
     * Checks against pre-downloaded manifest (array of valid hashes)
     *
     * @param string $qrHash - Scanned hash
     * @param array $manifest - Array of valid hashes for event
     * @return bool
     */
    public function verifyOffline(string $qrHash, array $manifest): bool
    {
        // Check if hash exists in manifest
        return in_array($qrHash, $manifest, true);
    }

    /**
     * Redeem a ticket (mark as scanned)
     *
     * @param string $qrHash - Ticket QR hash
     * @param string $bouncerId - Bouncer user/device ID
     * @param string $location - Scan location (e.g., "Main Entrance")
     * @param string $method - Scan method (online, offline_manifest, manual_override)
     * @param bool $offlineRedeemed - Was this scanned offline?
     * @return array ['success' => bool, 'ticket_id' => string, 'message' => string]
     */
    public function redeemTicket(
        string $qrHash,
        string $bouncerId,
        string $location = 'General',
        string $method = 'online',
        bool $offlineRedeemed = false
    ): array {
        try {
            // Verify ticket first
            $ticket = $this->verifyOnline($qrHash);

            if (!$ticket || !$ticket['valid']) {
                return [
                    'success' => false,
                    'ticket_id' => $ticket['ticket_id'] ?? null,
                    'message' => $ticket['reason'] ?? 'Ticket not found or invalid'
                ];
            }

            $ticketId = $ticket['ticket_id'];

            // Update ticket status
            $updateStmt = $this->pdo->prepare(
                'UPDATE tickets
                SET status = ?, redeemed_at = NOW(), redeemed_by = ?, redeemed_location = ?, offline_redeemed = ?
                WHERE id = ?'
            );
            $updateStmt->execute(['redeemed', $bouncerId, $location, $offlineRedeemed ? 1 : 0, $ticketId]);

            // Log redemption
            $logStmt = $this->pdo->prepare(
                'INSERT INTO ticket_redemptions
                (ticket_id, scanned_by, scan_location, scan_method, scan_result)
                VALUES (?, ?, ?, ?, ?)'
            );
            $logStmt->execute([$ticketId, $bouncerId, $location, $method, 'success']);

            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'message' => "Ticket redeemed: {$ticket['event_title']} @ {$ticket['venue_name']}"
            ];

        } catch (Exception $e) {
            error_log("TicketVerifier::redeemTicket error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error redeeming ticket'
            ];
        }
    }

    /**
     * Generate a manifest for offline bouncer mode
     *
     * Creates a JSON file with all valid QR hashes for an event
     * This is downloaded to the bouncer's device for offline validation
     *
     * @param string $eventId - Event UUID
     * @return array ['manifest_hash' => string, 'ticket_hashes' => array, 'total_tickets' => int]
     */
    public function generateManifest(string $eventId): array
    {
        try {
            // Get all active tickets for event
            $stmt = $this->pdo->prepare(
                'SELECT qr_hash FROM tickets
                WHERE event_id = ? AND status = ?'
            );
            $stmt->execute([$eventId, 'active']);
            $tickets = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Create manifest
            $manifest = [
                'event_id' => $eventId,
                'ticket_hashes' => $tickets,
                'total_tickets' => count($tickets),
                'generated_at' => date('c'),
                'version' => '1.0'
            ];

            // Hash the manifest itself for versioning
            $manifestHash = hash(self::HASH_ALGORITHM, json_encode($tickets));

            // Save to database
            $insertStmt = $this->pdo->prepare(
                'INSERT INTO ticket_manifests
                (event_id, manifest_hash, ticket_hashes, total_tickets, is_current, generated_at)
                VALUES (?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                is_current = 0'
            );
            $insertStmt->execute([
                $eventId,
                $manifestHash,
                json_encode($tickets),
                count($tickets)
            ]);

            return [
                'manifest_hash' => $manifestHash,
                'ticket_hashes' => $tickets,
                'total_tickets' => count($tickets)
            ];

        } catch (Exception $e) {
            error_log("TicketVerifier::generateManifest error: " . $e->getMessage());
            return [
                'ticket_hashes' => [],
                'total_tickets' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get current manifest for an event
     *
     * @param string $eventId
     * @return array|null
     */
    public function getManifest(string $eventId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM ticket_manifests
                WHERE event_id = ? AND is_current = 1
                ORDER BY generated_at DESC
                LIMIT 1'
            );
            $stmt->execute([$eventId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("TicketVerifier::getManifest error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync offline redemptions back to server
     *
     * Called by bouncer app after coming back online
     *
     * @param array $offlineScanLog - Array of scan records
     * @return array ['synced' => int, 'failed' => int, 'errors' => array]
     */
    public function syncOfflineRedemptions(array $offlineScanLog): array
    {
        $result = [
            'synced' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            foreach ($offlineScanLog as $scan) {
                try {
                    $qrHash = $scan['qr_hash'] ?? null;
                    $bouncerId = $scan['bouncer_id'] ?? 'unknown';
                    $location = $scan['location'] ?? 'Offline';
                    $scanTime = $scan['scanned_at'] ?? date('c');

                    if (!$qrHash) {
                        $result['failed']++;
                        $result['errors'][] = 'Missing QR hash in scan record';
                        continue;
                    }

                    // Redeem with offline flag
                    $redemption = $this->redeemTicket(
                        $qrHash,
                        $bouncerId,
                        $location,
                        'offline_manifest',
                        true
                    );

                    if ($redemption['success']) {
                        $result['synced']++;
                    } else {
                        $result['failed']++;
                        $result['errors'][] = $redemption['message'];
                    }

                } catch (Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = $e->getMessage();
                }
            }

        } catch (Exception $e) {
            error_log("TicketVerifier::syncOfflineRedemptions error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get event ticket stats
     *
     * @param string $eventId
     * @return array
     */
    public function getEventStats(string $eventId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = "redeemed" THEN 1 ELSE 0 END) as redeemed,
                    SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded
                FROM tickets
                WHERE event_id = ?'
            );
            $stmt->execute([$eventId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (Exception $e) {
            error_log("TicketVerifier::getEventStats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Handle manual override (fallback for lost/damaged tickets)
     *
     * Allows bouncer to manually input ticket number or user info
     *
     * @param string $identifier - Ticket number, user email, or ID
     * @param string $bouncerId - Who did this override
     * @param string $location - Scan location
     * @param string $reason - Why was override needed
     * @return array
     */
    public function manualOverride(
        string $identifier,
        string $bouncerId,
        string $location,
        string $reason = 'Lost ticket'
    ): array {
        try {
            // Find ticket by number or user
            $stmt = $this->pdo->prepare(
                'SELECT id, status FROM tickets
                WHERE ticket_number = ? OR user_id = ?
                ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$identifier, (int)$identifier]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                return [
                    'success' => false,
                    'message' => 'Ticket not found'
                ];
            }

            // Update ticket
            $updateStmt = $this->pdo->prepare(
                'UPDATE tickets
                SET status = ?, redeemed_at = NOW(), redeemed_by = ?, redeemed_location = ?
                WHERE id = ?'
            );
            $updateStmt->execute(['redeemed', $bouncerId, $location, $ticket['id']]);

            // Log override
            $logStmt = $this->pdo->prepare(
                'INSERT INTO ticket_redemptions
                (ticket_id, scanned_by, scan_location, scan_method, scan_result, notes)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $logStmt->execute([
                $ticket['id'],
                $bouncerId,
                $location,
                'manual_override',
                'manual_override',
                $reason
            ]);

            return [
                'success' => true,
                'ticket_id' => $ticket['id'],
                'message' => "Manual override applied: {$reason}"
            ];

        } catch (Exception $e) {
            error_log("TicketVerifier::manualOverride error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error processing override'
            ];
        }
    }
}
