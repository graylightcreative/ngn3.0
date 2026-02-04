<?php

namespace NGN\Lib\Services;

use Ramsey\Uuid\Uuid;
use NGN\Lib\Services\EmailService;

/**
 * TicketService
 *
 * Handles ticket generation, QR code creation, redemption, and manifest management
 * for the NGN ticketing system.
 *
 * Features:
 * - Secure QR hash generation with SHA-256 salting
 * - Ticket purchase and order creation
 * - Single-use redemption enforcement
 * - Offline manifest generation for Bouncer Mode
 * - Anti-fraud measures
 *
 * Related: Bible Ch. 8 (Ticket Architecture)
 */
class TicketService
{
    private \PDO $pdo;
    private string $secretKey;
    private EmailService $emailService;

    public function __construct(\PDO $pdo, EmailService $emailService, string $secretKey = null)
    {
        $this->pdo = $pdo;
        $this->emailService = $emailService;
        $this->secretKey = $secretKey ?? $_ENV['TICKET_SECRET_KEY'] ?? 'ngn_default_ticket_secret_change_me';
    }

    /**
     * Generate a secure QR hash for a ticket
     *
     * Format: SHA256(event_id + user_id + salt + secret_key)
     */
    public function generateQRHash(string $eventId, int $userId, string $salt): string
    {
        $data = $eventId . $userId . $salt . $this->secretKey;
        return hash('sha256', $data);
    }

    /**
     * Generate a random salt for QR hash
     */
    public function generateSalt(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a human-readable ticket number
     *
     * Format: TKT-{event_code}-{sequential}
     */
    public function generateTicketNumber(string $eventId): string
    {
        // Get event short code (first 6 chars of UUID)
        $eventCode = strtoupper(substr(str_replace('-', '', $eventId), 0, 6));

        // Get next sequential number for this event
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM ngn_2025.tickets
            WHERE event_id = ?
        ");
        $stmt->execute([$eventId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $sequential = ($result['count'] ?? 0) + 1;

        return "TKT-{$eventCode}-" . str_pad($sequential, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new ticket purchase
     *
     * @param array $data Ticket data
     * @return array Created ticket with QR hash
     * @throws \Exception
     */
    public function createTicket(array $data): array
    {
        $ticketId = Uuid::uuid4()->toString();
        $salt = $this->generateSalt();
        $qrHash = $this->generateQRHash($data['event_id'], $data['user_id'], $salt);
        $ticketNumber = $this->generateTicketNumber($data['event_id']);

        // Verify event exists and has capacity
        $event = $this->getEvent($data['event_id']);
        if (!$event) {
            throw new \Exception("Event not found");
        }

        if (!$event['enable_ticketing']) {
            throw new \Exception("Ticketing is not enabled for this event");
        }

        // Check capacity
        if ($event['ngn_allocation'] && $event['tickets_sold'] >= $event['ngn_allocation']) {
            throw new \Exception("Event is sold out");
        }

        // Insert ticket
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.tickets (
                id, event_id, user_id, qr_hash, qr_salt,
                ticket_type, ticket_number,
                base_price_cents, ngn_fee_cents, total_price_cents, currency,
                status, order_id, stripe_payment_intent_id, stripe_charge_id,
                metadata, notes, purchased_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $ticketId,
            $data['event_id'],
            $data['user_id'],
            $qrHash,
            $salt,
            $data['ticket_type'] ?? 'general',
            $ticketNumber,
            $data['base_price_cents'],
            $data['ngn_fee_cents'],
            $data['total_price_cents'],
            $data['currency'] ?? 'USD',
            'active',
            $data['order_id'] ?? null,
            $data['stripe_payment_intent_id'] ?? null,
            $data['stripe_charge_id'] ?? null,
            json_encode($data['metadata'] ?? []),
            $data['notes'] ?? null
        ]);

        // Increment tickets_sold count
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.events
            SET tickets_sold = tickets_sold + 1,
                tickets_remaining = ngn_allocation - (tickets_sold + 1)
            WHERE id = ?
        ");
        $stmt->execute([$data['event_id']]);

        // Invalidate manifest cache
        $this->invalidateManifest($data['event_id']);

        $createdTicket = [
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'qr_hash' => $qrHash,
            'event_id' => $data['event_id'],
            'user_id' => $data['user_id'],
            'status' => 'active'
        ];

        // Fetch additional user and event details for the email
        $ticketDetailsForEmail = $this->getTicket($ticketId); // Use getTicket to fetch full details including user/event names

        if ($ticketDetailsForEmail) {
            $this->sendTicketEmail($ticketDetailsForEmail);
        } else {
            // Log that ticket details could not be fetched for email
        }
        
        return $createdTicket;
    }

    /**
     * Bulk create multiple tickets (for group purchases)
     */
    public function createTickets(array $ticketsData): array
    {
        $tickets = [];
        foreach ($ticketsData as $ticketData) {
            $tickets[] = $this->createTicket($ticketData);
        }
        return $tickets;
    }

    /**
     * Redeem a ticket by QR hash
     *
     * @param string $qrHash QR hash scanned at door
     * @param array $scanData Scan details (scanner_id, location, etc.)
     * @return array Redemption result
     */
    public function redeemTicket(string $qrHash, array $scanData = []): array
    {
        // Find ticket by QR hash
        $stmt = $this->pdo->prepare("
            SELECT t.*, e.title as event_title, e.starts_at, u.Name as user_name, u.Email as user_email
            FROM ngn_2025.tickets t
            JOIN ngn_2025.events e ON e.id = t.event_id
            JOIN ngn_2025.users u ON u.Id = t.user_id
            WHERE t.qr_hash = ?
        ");
        $stmt->execute([$qrHash]);
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'invalid',
                'message' => 'Invalid ticket'
            ];
        }

        // Check if already redeemed
        if ($ticket['status'] === 'redeemed') {
            return [
                'success' => false,
                'error' => 'already_redeemed',
                'message' => 'Ticket already scanned',
                'redeemed_at' => $ticket['redeemed_at'],
                'ticket' => $ticket
            ];
        }

        // Check if ticket is valid
        if (!in_array($ticket['status'], ['active'])) {
            return [
                'success' => false,
                'error' => 'invalid_status',
                'message' => 'Ticket status: ' . $ticket['status']
            ];
        }

        // Mark as redeemed
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tickets
            SET status = 'redeemed',
                redeemed_at = NOW(),
                redeemed_by = ?,
                redeemed_location = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $scanData['scanned_by'] ?? null,
            $scanData['scan_location'] ?? null,
            $ticket['id']
        ]);

        // Log redemption
        $this->logRedemption($ticket['id'], 'success', $scanData);

        return [
            'success' => true,
            'message' => 'Ticket redeemed successfully',
            'ticket' => $ticket,
            'redeemed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Log a ticket redemption attempt
     */
    private function logRedemption(string $ticketId, string $result, array $scanData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.ticket_redemptions (
                ticket_id, scanned_by, scan_location, scan_method,
                device_id, device_ip, user_agent,
                latitude, longitude, scan_result, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $ticketId,
            $scanData['scanned_by'] ?? null,
            $scanData['scan_location'] ?? null,
            $scanData['scan_method'] ?? 'online',
            $scanData['device_id'] ?? null,
            $scanData['device_ip'] ?? null,
            $scanData['user_agent'] ?? null,
            $scanData['latitude'] ?? null,
            $scanData['longitude'] ?? null,
            $result,
            $scanData['notes'] ?? null
        ]);
    }

    /**
     * Generate offline manifest for Bouncer Mode
     *
     * Downloads all valid QR hashes for an event
     */
    public function generateManifest(string $eventId): array
    {
        // Get all active tickets for this event
        $stmt = $this->pdo->prepare("
            SELECT qr_hash
            FROM ngn_2025.tickets
            WHERE event_id = ?
            AND status = 'active'
        ");
        $stmt->execute([$eventId]);
        $tickets = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Create manifest hash for versioning
        $manifestHash = hash('sha256', json_encode($tickets) . time());

        // Store manifest
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.ticket_manifests (
                event_id, manifest_hash, ticket_hashes, total_tickets, is_current
            ) VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $eventId,
            $manifestHash,
            json_encode($tickets),
            count($tickets)
        ]);

        // Mark previous manifests as not current
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.ticket_manifests
            SET is_current = 0
            WHERE event_id = ? AND manifest_hash != ?
        ");
        $stmt->execute([$eventId, $manifestHash]);

        return [
            'event_id' => $eventId,
            'manifest_hash' => $manifestHash,
            'ticket_hashes' => $tickets,
            'total_tickets' => count($tickets),
            'generated_at' => date('c')
        ];
    }

    /**
     * Invalidate manifest cache (called when tickets are purchased)
     */
    private function invalidateManifest(string $eventId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.ticket_manifests
            SET is_current = 0
            WHERE event_id = ?
        ");
        $stmt->execute([$eventId]);
    }

    /**
     * Sync offline redemptions from Bouncer Mode
     *
     * @param array $redemptions Array of {qr_hash, scanned_at, scan_data}
     * @return array Sync results
     */
    public function syncOfflineRedemptions(array $redemptions): array
    {
        $results = [];

        foreach ($redemptions as $redemption) {
            $result = $this->redeemTicket($redemption['qr_hash'], array_merge(
                $redemption['scan_data'] ?? [],
                ['scan_method' => 'offline_manifest']
            ));

            $results[] = [
                'qr_hash' => $redemption['qr_hash'],
                'success' => $result['success'],
                'synced_at' => date('c')
            ];
        }

        return $results;
    }

    /**
     * Get event details
     */
    private function getEvent(string $eventId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ngn_2025.events WHERE id = ?
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get ticket by ID
     */
    public function getTicket(string $ticketId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, e.title as event_title, e.starts_at, u.Name as user_name, u.Email as user_email
            FROM ngn_2025.tickets t
            JOIN ngn_2025.events e ON e.id = t.event_id
            JOIN ngn_2025.users u ON u.Id = t.user_id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Sends a ticket email with an embedded QR code.
     *
     * @param array $ticketData Detailed ticket information including user and event data.
     * @return bool True if email was sent successfully, false otherwise.
     */
    public function sendTicketEmail(array $ticketData): bool
    {
        $qrCodeBase64 = $this->emailService->generateQrCodeAsBase64($ticketData['qr_hash']);

        if (!$qrCodeBase64) {
            // Log error, QR code generation failed
            return false;
        }

        $eventTitle = htmlspecialchars($ticketData['event_title']);
        $ticketNumber = htmlspecialchars($ticketData['ticket_number']);
        $userName = htmlspecialchars($ticketData['user_name']);
        $userEmail = htmlspecialchars($ticketData['user_email']);
        $eventStartsAt = (new \DateTime($ticketData['starts_at']))->format('F j, Y, h:i A');

        $subject = "Your Ticket for {$eventTitle} - {$ticketNumber}";

        $body = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Your Event Ticket</title>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
                    .container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
                    .header { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
                    .header h1 { color: #333; font-size: 24px; margin: 0; }
                    .ticket-details p { margin: 5px 0; line-height: 1.6; }
                    .ticket-details strong { color: #555; }
                    .qr-code { text-align: center; margin-top: 30px; }
                    .qr-code img { max-width: 150px; height: auto; border: 1px solid #eee; }
                    .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Your Ticket for {$eventTitle}</h1>
                    </div>
                    <div class="ticket-details">
                        <p><strong>Ticket Holder:</strong> {$userName}</p>
                        <p><strong>Email:</strong> {$userEmail}</p>
                        <p><strong>Event:</strong> {$eventTitle}</p>
                        <p><strong>Date & Time:</strong> {$eventStartsAt}</p>
                        <p><strong>Ticket Number:</strong> {$ticketNumber}</p>
                        <p>Please present this QR code at the entrance.</p>
                    </div>
                    <div class="qr-code">
                        <img src="{$qrCodeBase64}" alt="QR Code for Ticket {$ticketNumber}">
                        <p><strong>Scan for Entry</strong></p>
                    </div>
                    <div class="footer">
                        <p>&copy; 2025 NextGen Noise. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
HTML;
        return $this->emailService->send($userEmail, $subject, $body, true);
    }

    /**
     * Get tickets for a user
     */
    public function getUserTickets(int $userId, array $filters = []): array
    {
        $sql = "
            SELECT t.*, e.title as event_title, e.starts_at, e.image_url as event_image
            FROM ngn_2025.tickets t
            JOIN ngn_2025.events e ON e.id = t.event_id
            WHERE t.user_id = ?
        ";

        $params = [$userId];

        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['upcoming']) && $filters['upcoming']) {
            $sql .= " AND e.starts_at >= NOW()";
        }

        $sql .= " ORDER BY e.starts_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get tickets for an event
     */
    public function getEventTickets(string $eventId, array $filters = []): array
    {
        $sql = "
            SELECT t.*, u.Name as user_name, u.Email as user_email
            FROM ngn_2025.tickets t
            JOIN ngn_2025.users u ON u.Id = t.user_id
            WHERE t.event_id = ?
        ";

        $params = [$eventId];

        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY t.purchased_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Refund a ticket
     */
    public function refundTicket(string $ticketId, string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tickets
            SET status = 'refunded',
                refunded_at = NOW(),
                refund_reason = ?
            WHERE id = ?
            AND status IN ('active', 'redeemed')
        ");

        $stmt->execute([$reason, $ticketId]);

        if ($stmt->rowCount() > 0) {
            // Decrement tickets_sold if ticket was active
            $ticket = $this->getTicket($ticketId);
            if ($ticket) {
                $stmt = $this->pdo->prepare("
                    UPDATE ngn_2025.events
                    SET tickets_sold = GREATEST(0, tickets_sold - 1),
                        tickets_remaining = ngn_allocation - GREATEST(0, tickets_sold - 1)
                    WHERE id = ?
                ");
                $stmt->execute([$ticket['event_id']]);

                // Invalidate manifest
                $this->invalidateManifest($ticket['event_id']);
            }

            return true;
        }

        return false;
    }

    /**
     * Get ticket statistics for an event
     */
    public function getEventStats(string $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tickets,
                COUNT(CASE WHEN status = 'redeemed' THEN 1 END) as redeemed_tickets,
                COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_tickets,
                SUM(total_price_cents) as total_revenue_cents,
                SUM(ngn_fee_cents) as total_fees_cents
            FROM ngn_2025.tickets
            WHERE event_id = ?
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
