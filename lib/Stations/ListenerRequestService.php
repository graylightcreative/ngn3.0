<?php
/**
 * Listener Request Service
 * Manages listener requests (song requests, shoutouts, dedications) for DJ programming
 */

namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class ListenerRequestService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Constants for request handling
    private const REQUEST_TYPES = ['song', 'shoutout', 'dedication'];
    private const REQUEST_EXPIRY_HOURS = 24;
    private const ANONYMOUS_RATE_LIMIT_PER_HOUR = 5;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'listener_requests');
    }

    /**
     * Submit a listener request (anonymous or authenticated)
     *
     * @param int $stationId Station ID
     * @param int|null $userId Authenticated user ID (null for anonymous)
     * @param string $type Request type: song, shoutout, dedication
     * @param array $data Request data: depends on type
     *                    song: {song_title, song_artist}
     *                    shoutout: {message}
     *                    dedication: {message, dedicated_to}
     * @param string|null $ipAddress IP address (for anonymous tracking)
     * @param string|null $userAgent Browser user agent (for anonymous tracking)
     * @return array Result with 'success', 'id', 'message'
     * @throws \InvalidArgumentException on validation failure
     */
    public function submitRequest(
        int $stationId,
        ?int $userId,
        string $type,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        try {
            // Validate request type
            if (!in_array($type, self::REQUEST_TYPES)) {
                throw new \InvalidArgumentException("Invalid request type: $type");
            }

            // Validate based on type
            if ($type === 'song') {
                if (empty($data['song_title'])) {
                    throw new \InvalidArgumentException('Song title is required');
                }
            } elseif ($type === 'dedication') {
                if (empty($data['message'])) {
                    throw new \InvalidArgumentException('Message is required');
                }
                if (empty($data['dedicated_to'])) {
                    throw new \InvalidArgumentException('Dedication recipient is required');
                }
            } elseif ($type === 'shoutout') {
                if (empty($data['message'])) {
                    throw new \InvalidArgumentException('Message is required');
                }
            }

            // Check anonymous rate limiting
            if ($userId === null && $ipAddress) {
                if ($this->isAnonymousRateLimited($ipAddress)) {
                    throw new \RuntimeException('Too many requests. Please try again later.');
                }
            }

            // Insert request
            $stmt = $this->write->prepare("
                INSERT INTO station_listener_requests
                (station_id, user_id, request_type, song_title, song_artist, message, dedicated_to, ip_address, user_agent, expires_at)
                VALUES (:stationId, :userId, :type, :songTitle, :songArtist, :message, :dedicatedTo, :ipAddress, :userAgent, DATE_ADD(NOW(), INTERVAL " . self::REQUEST_EXPIRY_HOURS . " HOUR))
            ");

            $success = $stmt->execute([
                ':stationId' => $stationId,
                ':userId' => $userId,
                ':type' => $type,
                ':songTitle' => $data['song_title'] ?? null,
                ':songArtist' => $data['song_artist'] ?? null,
                ':message' => substr($data['message'] ?? '', 0, 1000),
                ':dedicatedTo' => substr($data['dedicated_to'] ?? '', 0, 255),
                ':ipAddress' => $ipAddress,
                ':userAgent' => substr($userAgent ?? '', 0, 512)
            ]);

            if (!$success) {
                throw new \RuntimeException('Failed to submit request');
            }

            $requestId = (int)$this->write->lastInsertId();

            $this->logger->info('listener_request_submitted', [
                'station_id' => $stationId,
                'request_id' => $requestId,
                'type' => $type,
                'user_id' => $userId,
                'ip_address' => $ipAddress ? substr($ipAddress, 0, 15) : null // Log only partial IP
            ]);

            return [
                'success' => true,
                'id' => $requestId,
                'message' => 'Request submitted successfully'
            ];

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('submit_request_validation_failed', [
                'station_id' => $stationId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('submit_request_failed', [
                'station_id' => $stationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get request queue for station (for DJ view)
     *
     * @param int $stationId Station ID
     * @param string|null $status Filter by status (pending, approved, rejected, fulfilled)
     * @param int $page Pagination page
     * @param int $perPage Items per page
     * @return array Result with 'success', 'items', 'total'
     */
    public function listRequests(int $stationId, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        try {
            $where = ['station_id = :stationId'];
            $params = [':stationId' => $stationId];

            if ($status !== null) {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }

            // Exclude expired requests by default
            $where[] = '(expires_at IS NULL OR expires_at > NOW())';

            $whereClause = implode(' AND ', $where);

            // Count total
            $countStmt = $this->write->prepare("
                SELECT COUNT(*) as total
                FROM `ngn_2025`.`station_listener_requests`
                WHERE {$whereClause}
            ");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch paginated results
            $offset = ($page - 1) * $perPage;
            $listStmt = $this->write->prepare("
                SELECT
                    id, user_id, request_type, song_title, song_artist,
                    message, dedicated_to, status, approved_at, fulfilled_at,
                    created_at, expires_at,
                    CASE WHEN user_id IS NOT NULL THEN 'authenticated' ELSE 'anonymous' END as requester_type
                FROM `ngn_2025`.`station_listener_requests`
                WHERE {$whereClause}
                ORDER BY
                    CASE status
                        WHEN 'pending' THEN 0
                        WHEN 'approved' THEN 1
                        WHEN 'fulfilled' THEN 2
                        ELSE 3
                    END ASC,
                    created_at DESC
                LIMIT :offset, :perPage
            ");

            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $listStmt->bindValue($key, $value);
            }
            $listStmt->execute();
            $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return [
                'success' => true,
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            ];

        } catch (\Throwable $e) {
            $this->logger->error('list_requests_failed', ['station_id' => $stationId, 'error' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'total' => 0, 'message' => 'Failed to load requests'];
        }
    }

    /**
     * Get single request details
     *
     * @param int $requestId Request ID
     * @param int|null $stationId Verify ownership
     * @return array|null Request details
     */
    public function getRequest(int $requestId, ?int $stationId = null): ?array
    {
        try {
            $sql = "
                SELECT
                    id, station_id, user_id, request_type, song_title, song_artist,
                    message, dedicated_to, status, approved_by, approved_at, fulfilled_at,
                    rejection_reason, created_at, expires_at
                FROM `ngn_2025`.`station_listener_requests`
                WHERE id = :id
            ";

            if ($stationId !== null) {
                $sql .= " AND station_id = :stationId";
            }

            $stmt = $this->write->prepare($sql);
            $params = [':id' => $requestId];
            if ($stationId !== null) {
                $params[':stationId'] = $stationId;
            }
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {
            $this->logger->error('get_request_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Approve listener request
     *
     * @param int $requestId Request ID
     * @param int $stationId Station ID (for verification)
     * @param int $djUserId DJ/staff user ID approving
     * @return bool Success
     */
    public function approveRequest(int $requestId, int $stationId, int $djUserId): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_listener_requests
                SET status = 'approved', approved_by = :djUserId, approved_at = NOW()
                WHERE id = :id AND station_id = :stationId
            ");

            $success = $stmt->execute([
                ':id' => $requestId,
                ':stationId' => $stationId,
                ':djUserId' => $djUserId
            ]);

            if ($success) {
                $this->logger->info('request_approved', [
                    'request_id' => $requestId,
                    'station_id' => $stationId,
                    'approved_by' => $djUserId
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('approve_request_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reject listener request
     *
     * @param int $requestId Request ID
     * @param int $stationId Station ID (for verification)
     * @param string $reason Rejection reason
     * @return bool Success
     */
    public function rejectRequest(int $requestId, int $stationId, string $reason = ''): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_listener_requests
                SET status = 'rejected', rejection_reason = :reason
                WHERE id = :id AND station_id = :stationId
            ");

            $success = $stmt->execute([
                ':id' => $requestId,
                ':stationId' => $stationId,
                ':reason' => substr($reason, 0, 500)
            ]);

            if ($success) {
                $this->logger->info('request_rejected', [
                    'request_id' => $requestId,
                    'station_id' => $stationId
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('reject_request_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Mark request as fulfilled (played on air)
     *
     * @param int $requestId Request ID
     * @param int $stationId Station ID (for verification)
     * @return bool Success
     */
    public function fulfillRequest(int $requestId, int $stationId): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_listener_requests
                SET status = 'fulfilled', fulfilled_at = NOW()
                WHERE id = :id AND station_id = :stationId
            ");

            $success = $stmt->execute([':id' => $requestId, ':stationId' => $stationId]);

            if ($success) {
                $this->logger->info('request_fulfilled', [
                    'request_id' => $requestId,
                    'station_id' => $stationId
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('fulfill_request_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete request
     *
     * @param int $requestId Request ID
     * @param int $stationId Station ID
     * @return bool Success
     */
    public function deleteRequest(int $requestId, int $stationId): bool
    {
        try {
            $stmt = $this->write->prepare("
                DELETE FROM station_listener_requests
                WHERE id = :id AND station_id = :stationId
            ");
            return $stmt->execute([':id' => $requestId, ':stationId' => $stationId]);

        } catch (\Throwable $e) {
            $this->logger->error('delete_request_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get stats for station requests
     *
     * @param int $stationId Station ID
     * @return array Statistics
     */
    public function getStats(int $stationId): array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM `ngn_2025`.`station_listener_requests`
                WHERE station_id = :stationId AND expires_at > NOW()
            ");
            $stmt->execute([':stationId' => $stationId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int)($stats['total'] ?? 0),
                'pending' => (int)($stats['pending_count'] ?? 0),
                'approved' => (int)($stats['approved_count'] ?? 0),
                'fulfilled' => (int)($stats['fulfilled_count'] ?? 0),
                'rejected' => (int)($stats['rejected_count'] ?? 0)
            ];

        } catch (\Throwable $e) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'fulfilled' => 0, 'rejected' => 0];
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Check if anonymous requester is rate limited
     *
     * @param string $ipAddress IP address
     * @return bool True if rate limited
     */
    private function isAnonymousRateLimited(string $ipAddress): bool
    {
        try {
            $stmt = $this->write->prepare("
                SELECT COUNT(*) FROM `ngn_2025`.`station_listener_requests`
                WHERE ip_address = :ip
                  AND user_id IS NULL
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([':ip' => $ipAddress]);
            $count = (int)$stmt->fetchColumn();
            return $count >= self::ANONYMOUS_RATE_LIMIT_PER_HOUR;

        } catch (\Throwable $e) {
            return false; // Fail open on error
        }
    }
}
