<?php

namespace NGN\Lib\Services\Royalties;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class PlaybackService
{
    private PDO $db;

    public function __construct(Config $config)
    {
        $this->db = ConnectionFactory::write($config);
    }

    /**
     * Log a playback event for legal auditing and royalty reporting.
     * 
     * @param int $trackId ID of the track played
     * @param int $durationSeconds How long the track was played
     * @param int|null $stationId Station ID if played via station, NULL for on-demand
     * @param int|null $userId Listener User ID (if authenticated)
     * @param string $territory ISO 3166-1 alpha-2 country code (default 'XX')
     * @param int $listeners Number of listeners (for broadcasts, default 1)
     * @param string|null $ip Listener IP address
     * @param string|null $ua Listener User Agent
     * @return int Inserted Log ID
     */
    public function logListen(
        int $trackId, 
        int $durationSeconds, 
        ?int $stationId = null, 
        ?int $userId = null,
        string $territory = 'XX',
        int $listeners = 1,
        ?string $ip = null,
        ?string $ua = null
    ): int {
        // "Qualified Listen" definition: 30 seconds continuous playback
        $isQualified = ($durationSeconds >= 30) ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO `pln_playback_log` 
            (track_id, station_id, user_id, played_at, duration_seconds, is_qualified, territory, listeners, ip_address, user_agent)
            VALUES 
            (:track_id, :station_id, :user_id, NOW(), :duration, :is_qualified, :territory, :listeners, :ip, :ua)
        ");

        $stmt->execute([
            ':track_id' => $trackId,
            ':station_id' => $stationId,
            ':user_id' => $userId,
            ':duration' => $durationSeconds,
            ':is_qualified' => $isQualified,
            ':territory' => substr(strtoupper($territory), 0, 2),
            ':listeners' => max(1, $listeners),
            ':ip' => $ip,
            ':ua' => $ua ? substr($ua, 0, 255) : null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Process a qualified listen (30+ seconds) and trigger royalty payment
     *
     * Called automatically when:
     * - Streaming API completes a play (30+ seconds)
     * - Cron job processes pending qualified listens
     * - Manual royalty processing (admin)
     *
     * @param int $playbackEventId ID of playback_events record
     * @return bool True if royalty was processed, false if skipped
     * @throws RuntimeException if event not found or has errors
     */
    public function processQualifiedListen(int $playbackEventId): bool
    {
        // 1. Get playback event
        $stmt = $this->db->prepare("
            SELECT * FROM `playback_events`
            WHERE id = :id
        ");
        $stmt->execute([':id' => $playbackEventId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            throw new \RuntimeException("Playback event not found: {$playbackEventId}");
        }

        // 2. Skip if already processed
        if ($event['royalty_processed']) {
            return false;
        }

        // 3. Check if this is a qualified listen (30+ seconds)
        if (!$event['is_qualified_listen']) {
            return false;
        }

        $trackId = (int)$event['track_id'];

        // 4. Check rights_ledger eligibility
        $stmt = $this->db->prepare("
            SELECT id, is_royalty_eligible
            FROM `cdm_rights_ledger`
            WHERE track_id = :track_id
            LIMIT 1
        ");
        $stmt->execute([':track_id' => $trackId]);
        $rights = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$rights || !$rights['is_royalty_eligible']) {
            // Mark as processed but skip royalty (disputed/unavailable track)
            $stmt = $this->db->prepare("
                UPDATE playback_events
                SET royalty_processed = TRUE, royalty_processed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $playbackEventId]);
            return false;
        }

        // 5. Get track details for royalty calculation
        $stmt = $this->db->prepare("
            SELECT id, title, artist_id
            FROM tracks
            WHERE id = :track_id
        ");
        $stmt->execute([':track_id' => $trackId]);
        $track = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$track) {
            throw new \RuntimeException("Track not found: {$trackId}");
        }

        // 6. Calculate royalty amount
        // Placeholder: $0.001 per qualified listen
        // Future: Implement pool distribution and split calculation
        $royaltyAmount = 0.001; // $0.001 USD

        // 7. Record royalty transaction via RoyaltyLedgerService
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `royalty_transactions` (
                    track_id, artist_id, user_id,
                    amount_cents, currency, transaction_type,
                    source_type, source_id,
                    status, notes,
                    created_at
                ) VALUES (
                    :track_id, :artist_id, :user_id,
                    :amount_cents, :currency, :transaction_type,
                    :source_type, :source_id,
                    :status, :notes,
                    NOW()
                )
            ");

            $stmt->execute([
                ':track_id' => $trackId,
                ':artist_id' => $track['artist_id'],
                ':user_id' => $event['user_id'],
                ':amount_cents' => (int)($royaltyAmount * 100), // Convert to cents
                ':currency' => 'USD',
                ':transaction_type' => 'streaming_royalty',
                ':source_type' => $event['source_type'] ?? 'on_demand',
                ':source_id' => $event['source_id'],
                ':status' => 'pending',
                ':notes' => sprintf(
                    'Qualified listen from %s (%s)',
                    $event['territory'] ?? 'XX',
                    $event['source_type'] ?? 'on_demand'
                )
            ]);

            // 8. Mark playback event as processed
            $stmt = $this->db->prepare("
                UPDATE playback_events
                SET royalty_processed = TRUE, royalty_processed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $playbackEventId]);

            return true;

        } catch (\Throwable $e) {
            // Log error but don't fail - royalties can be retried
            error_log("Royalty processing error for event {$playbackEventId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get pending royalty transactions for a track or artist
     *
     * @param int $trackId Track ID (optional)
     * @param int $artistId Artist ID (optional)
     * @param string $status Status filter (pending, processed, failed)
     * @param int $limit Results limit
     * @return array List of pending royalty transactions
     */
    public function getPendingRoyalties(
        ?int $trackId = null,
        ?int $artistId = null,
        string $status = 'pending',
        int $limit = 100
    ): array {
        $where = ['status = :status'];
        $params = [':status' => $status];

        if ($trackId) {
            $where[] = 'track_id = :track_id';
            $params[':track_id'] = $trackId;
        }

        if ($artistId) {
            $where[] = 'artist_id = :artist_id';
            $params[':artist_id'] = $artistId;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT * FROM royalty_transactions
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get royalty statistics for analytics
     *
     * @param int $trackId Track ID
     * @return array Royalty stats {total_amount, qualified_listens, average_per_listen, last_processed}
     */
    public function getRoyaltyStats(int $trackId): array
    {
        // Get qualified listen count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM playback_events
            WHERE track_id = :track_id
            AND is_qualified_listen = TRUE
        ");
        $stmt->execute([':track_id' => $trackId]);
        $listenStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get royalty total
        $stmt = $this->db->prepare("
            SELECT
                SUM(amount_cents) as total_cents,
                COUNT(*) as transaction_count,
                MAX(created_at) as last_processed
            FROM royalty_transactions
            WHERE track_id = :track_id
            AND transaction_type = 'streaming_royalty'
        ");
        $stmt->execute([':track_id' => $trackId]);
        $royaltyStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $totalCents = (int)($royaltyStats['total_cents'] ?? 0);
        $qualifiedListens = (int)($listenStats['count'] ?? 0);

        return [
            'track_id' => $trackId,
            'qualified_listens' => $qualifiedListens,
            'total_royalties_cents' => $totalCents,
            'total_royalties_usd' => round($totalCents / 100, 4),
            'average_per_listen' => $qualifiedListens > 0
                ? round($totalCents / ($qualifiedListens * 100), 4)
                : 0,
            'transaction_count' => (int)($royaltyStats['transaction_count'] ?? 0),
            'last_processed' => $royaltyStats['last_processed']
        ];
    }

    /**
     * Record qualified listen event
     *
     * @param array $data Event data
     * @return int Event ID
     */
    public function recordQualifiedListen(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`playback_events` (
                user_id,
                track_id,
                session_id,
                started_at,
                duration_ms,
                is_qualified_listen,
                source_type,
                territory,
                ip_address,
                user_agent,
                event,
                royalty_processed
            ) VALUES (
                :user_id,
                :track_id,
                :session_id,
                :started_at,
                :duration_ms,
                TRUE,
                :source_type,
                :territory,
                :ip_address,
                :user_agent,
                :metadata,
                FALSE
            )
        ");

        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':track_id' => $data['track_id'],
            ':session_id' => $data['session_id'],
            ':started_at' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
            ':duration_ms' => ($data['duration_seconds'] ?? 30) * 1000,
            ':source_type' => $data['source_type'] ?? 'on_demand',
            ':territory' => $this->detectTerritory($data['ip_address'] ?? null),
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':metadata' => json_encode($data['metadata'] ?? [])
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Check for duplicate event within time window
     *
     * @param int $trackId Track ID
     * @param string $sessionId Session ID
     * @param int|null $userId User ID (optional)
     * @param int $windowSeconds Time window in seconds
     * @return bool True if duplicate found
     */
    public function isDuplicateEvent(
        int $trackId,
        string $sessionId,
        ?int $userId,
        int $windowSeconds = 300
    ): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM `ngn_2025`.`playback_events`
            WHERE track_id = :track_id
              AND session_id = :session_id
              AND (:user_id IS NULL OR user_id = :user_id)
              AND is_qualified_listen = TRUE
              AND started_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)
        ");

        $stmt->execute([
            ':track_id' => $trackId,
            ':session_id' => $sessionId,
            ':user_id' => $userId,
            ':window' => $windowSeconds
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Detect user territory from IP address
     *
     * @param string|null $ipAddress IP address
     * @return string ISO 3166-1 alpha-2 country code
     */
    private function detectTerritory(?string $ipAddress): string
    {
        // TODO: Implement GeoIP lookup
        // For now, default to 'XX' (unknown)
        return 'XX';
    }

    /**
     * Calculate royalty splits from rights ledger
     *
     * @param int $trackId Track ID
     * @return array Array of splits with user_id, role, percentage
     */
    public function getRoyaltySplits(int $trackId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                rs.user_id,
                rs.role,
                rs.percentage,
                u.email,
                u.username
            FROM `ngn_2025`.`cdm_rights_ledger` rl
            JOIN `ngn_2025`.`cdm_rights_splits` rs ON rl.id = rs.ledger_id
            JOIN `ngn_2025`.`users` u ON rs.user_id = u.id
            WHERE rl.track_id = :track_id
              AND rl.status = 'active'
              AND rl.is_royalty_eligible = TRUE
              AND rs.accepted_at IS NOT NULL
            ORDER BY rs.percentage DESC
        ");

        $stmt->execute([':track_id' => $trackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process pending qualified listens (for cron job)
     *
     * @param int $limit Max events to process per run
     * @return array Processing stats
     */
    public function processPendingEvents(int $limit = 1000): array
    {
        $stats = [
            'processed' => 0,
            'failed' => 0,
            'total_royalties' => 0.0,
            'errors' => []
        ];

        // Fetch unprocessed qualified listens
        $stmt = $this->pdo->prepare("
            SELECT
                pe.id,
                pe.track_id,
                pe.user_id,
                pe.duration_ms,
                pe.source_type,
                pe.territory,
                pe.started_at,
                t.title as track_title,
                a.name as artist_name
            FROM `ngn_2025`.`playback_events` pe
            JOIN `ngn_2025`.`tracks` t ON pe.track_id = t.id
            LEFT JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id
            WHERE pe.is_qualified_listen = TRUE
              AND pe.royalty_processed = FALSE
            ORDER BY pe.started_at ASC
            LIMIT :limit
        ");

        $stmt->execute([':limit' => $limit]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $event) {
            try {
                // Get royalty splits for track
                $splits = $this->getRoyaltySplits($event['track_id']);

                if (empty($splits)) {
                    error_log("[PlaybackService] No splits found for track {$event['track_id']}");
                    $this->markEventProcessed($event['id']);
                    $stats['failed']++;
                    continue;
                }

                // Calculate base royalty amount
                $baseRoyalty = $this->calculateRoyalty(
                    $event['source_type'],
                    $event['territory'],
                    $event['duration_ms']
                );

                // Distribute royalty across splits
                foreach ($splits as $split) {
                    $splitAmount = $baseRoyalty * ($split['percentage'] / 100);

                    $this->createRoyaltyTransaction([
                        'track_id' => $event['track_id'],
                        'playback_event_id' => $event['id'],
                        'to_user_id' => $split['user_id'],
                        'role' => $split['role'],
                        'amount' => $splitAmount,
                        'percentage' => $split['percentage'],
                        'source_type' => $event['source_type'],
                        'metadata' => [
                            'track_title' => $event['track_title'],
                            'artist_name' => $event['artist_name'],
                            'played_at' => $event['started_at']
                        ]
                    ]);
                }

                // Mark event as processed
                $this->markEventProcessed($event['id']);

                $stats['processed']++;
                $stats['total_royalties'] += $baseRoyalty;

            } catch (Exception $e) {
                error_log("[PlaybackService] Error processing event {$event['id']}: " . $e->getMessage());
                $stats['failed']++;
                $stats['errors'][] = "Event {$event['id']}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Calculate royalty amount based on source and territory
     *
     * @param string $sourceType Source type (on_demand, station_stream, etc.)
     * @param string $territory ISO country code
     * @param int $durationMs Playback duration in milliseconds
     * @return float Royalty amount in dollars
     */
    private function calculateRoyalty(
        string $sourceType,
        string $territory,
        int $durationMs
    ): float {
        // Base rate: $0.001 per qualified listen
        $baseRate = 0.001;

        // TODO: Future enhancements:
        // - Territory multipliers (US: 1.0, UK: 0.8, etc.)
        // - Source type multipliers (on_demand: 1.0, radio: 0.5, etc.)
        // - Duration-based scaling (>60s gets bonus, etc.)

        return $baseRate;
    }

    /**
     * Create royalty transaction record
     */
    private function createRoyaltyTransaction(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`cdm_royalty_transactions` (
                transaction_id,
                source_type,
                to_user_id,
                entity_type,
                entity_id,
                amount_gross,
                platform_fee,
                amount_net,
                status,
                rights_split_data,
                created_at
            ) VALUES (
                :transaction_id,
                'rights_payment',
                :to_user_id,
                'track',
                :track_id,
                :amount,
                0.00,
                :amount,
                'pending',
                :metadata,
                NOW()
            )
        ");

        $transactionId = 'RP-' . strtoupper(bin2hex(random_bytes(8)));

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':to_user_id' => $data['to_user_id'],
            ':track_id' => $data['track_id'],
            ':amount' => $data['amount'],
            ':metadata' => json_encode([
                'playback_event_id' => $data['playback_event_id'],
                'role' => $data['role'],
                'percentage' => $data['percentage'],
                'source_type' => $data['source_type'],
                'track_title' => $data['metadata']['track_title'] ?? null,
                'artist_name' => $data['metadata']['artist_name'] ?? null,
                'played_at' => $data['metadata']['played_at'] ?? null
            ])
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Mark playback event as processed
     */
    private function markEventProcessed(int $eventId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`playback_events`
            SET royalty_processed = TRUE,
                royalty_processed_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([':id' => $eventId]);
    }
}
