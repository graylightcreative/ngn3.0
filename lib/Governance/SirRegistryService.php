<?php

namespace NGN\Lib\Governance;

use PDO;
use Exception;

/**
 * SirRegistryService
 *
 * Core service for CRUD operations on Standardized Input Requests (SIRs).
 * Manages the complete lifecycle: OPEN → IN_REVIEW → RANT_PHASE → VERIFIED → CLOSED
 *
 * Bible Reference: Chapter 31 - Directorate SIR Registry System
 */
class SirRegistryService
{
    private PDO $pdo;
    private DirectorateRoles $roles;
    private SirAuditService $auditService;
    private SirNotificationService $notificationService;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param DirectorateRoles|null $roles Director roles helper
     * @param SirAuditService|null $auditService Audit service
     * @param SirNotificationService|null $notificationService Notification service
     */
    public function __construct(
        PDO $pdo,
        ?DirectorateRoles $roles = null,
        ?SirAuditService $auditService = null,
        ?SirNotificationService $notificationService = null
    ) {
        $this->pdo = $pdo;
        $this->roles = $roles ?? new DirectorateRoles();
        $this->auditService = $auditService ?? new SirAuditService($pdo);
        $this->notificationService = $notificationService ?? new SirNotificationService($pdo, $this->roles);
    }

    /**
     * Create new SIR
     *
     * @param array $sirData SIR data including:
     *   - objective: One-sentence goal
     *   - context: Why this matters
     *   - deliverable: What done looks like
     *   - threshold: Deadline/milestone
     *   - assigned_to_director: brandon|pepper|erik
     *   - priority: critical|high|medium|low
     *   - issued_by_user_id: Chairman user ID
     *   - threshold_date: ISO date string (optional)
     *   - notes: Additional notes (optional)
     * @return int SIR ID
     * @throws Exception
     */
    public function createSir(array $sirData): int
    {
        try {
            // Validate required fields
            $this->validateSirData($sirData);

            // Validate director
            $directorSlug = strtolower($sirData['assigned_to_director']);
            if (!$this->roles->isValidDirector($directorSlug)) {
                throw new Exception("Invalid director: {$directorSlug}");
            }

            // Generate SIR number
            $sirNumber = $this->generateSirNumber();

            // Get director user ID
            $directorUserId = $this->roles->getDirectorUserId($directorSlug);
            $registryDivision = $this->roles->getRegistryDivision($directorSlug);

            // Prepare data
            $thresholdDate = null;
            if (!empty($sirData['threshold_date'])) {
                $thresholdDate = date('Y-m-d', strtotime($sirData['threshold_date']));
            }

            $metadata = !empty($sirData['metadata']) ? json_encode($sirData['metadata']) : null;

            // Insert SIR
            $stmt = $this->pdo->prepare(
                "INSERT INTO ngn_2025.directorate_sirs (
                    sir_number, objective, context, deliverable, threshold,
                    assigned_to_director, status, priority, registry_division,
                    issued_by_user_id, director_user_id,
                    threshold_date, notes, metadata,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );

            $stmt->execute([
                $sirNumber,
                $sirData['objective'],
                $sirData['context'],
                $sirData['deliverable'],
                $sirData['threshold'] ?? null,
                $directorSlug,
                'open',
                $sirData['priority'] ?? 'medium',
                $registryDivision,
                $sirData['issued_by_user_id'],
                $directorUserId,
                $thresholdDate,
                $sirData['notes'] ?? null,
                $metadata,
            ]);

            $sirId = (int)$this->pdo->lastInsertId();

            // Log creation
            $this->auditService->logCreated($sirId, $sirData['issued_by_user_id'], [
                'sir_number' => $sirNumber,
                'objective' => $sirData['objective'],
                'priority' => $sirData['priority'] ?? 'medium',
                'assigned_to_director' => $directorSlug,
            ]);

            // Send notification to director
            $this->notificationService->notifySirAssigned($sirId, $directorUserId, [
                'sir_number' => $sirNumber,
                'objective' => $sirData['objective'],
                'priority' => $sirData['priority'] ?? 'medium',
                'threshold_date' => $thresholdDate,
            ]);

            // Mark notification sent
            $this->markNotificationSent($sirId);

            return $sirId;
        } catch (\PDOException $e) {
            throw new Exception("Failed to create SIR: " . $e->getMessage());
        }
    }

    /**
     * Get SIR by ID
     *
     * @param int $sirId SIR ID
     * @return array|null SIR data or null if not found
     * @throws Exception
     */
    public function getSir(int $sirId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM ngn_2025.directorate_sirs WHERE id = ?"
            );

            $stmt->execute([$sirId]);
            $sir = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sir) {
                return null;
            }

            // Decode metadata
            if ($sir['metadata']) {
                $sir['metadata'] = json_decode($sir['metadata'], true);
            }

            // Calculate days open
            $createdAt = new \DateTime($sir['created_at']);
            $now = new \DateTime();
            $sir['days_open'] = $now->diff($createdAt)->days;

            // Calculate days until threshold
            if ($sir['threshold_date']) {
                $thresholdDate = new \DateTime($sir['threshold_date']);
                $sir['days_until_threshold'] = max(0, $thresholdDate->diff($now)->days);
                if ($thresholdDate < $now) {
                    $sir['is_past_threshold'] = true;
                }
            }

            return $sir;
        } catch (\PDOException $e) {
            throw new Exception("Failed to retrieve SIR: " . $e->getMessage());
        }
    }

    /**
     * List SIRs with filters
     *
     * @param array $filters Query filters:
     *   - status: Filter by status
     *   - director: Filter by director slug
     *   - priority: Filter by priority
     *   - registry: Filter by registry division
     *   - overdue: Boolean (show only overdue)
     *   - limit: Result limit (default 50, max 100)
     *   - offset: Pagination offset
     * @return array List of SIRs with pagination info
     * @throws Exception
     */
    public function listSirs(array $filters = []): array
    {
        try {
            // Build WHERE clause
            $where = [];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = "status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['director'])) {
                $where[] = "assigned_to_director = ?";
                $params[] = strtolower($filters['director']);
            }

            if (!empty($filters['priority'])) {
                $where[] = "priority = ?";
                $params[] = $filters['priority'];
            }

            if (!empty($filters['registry'])) {
                $where[] = "registry_division = ?";
                $params[] = $filters['registry'];
            }

            if (!empty($filters['overdue'])) {
                $where[] = "DATEDIFF(NOW(), updated_at) > 14 AND status IN ('open', 'in_review')";
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            // Get total count
            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) as total FROM ngn_2025.directorate_sirs {$whereClause}"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated results
            $limit = min((int)($filters['limit'] ?? 50), 100);
            $offset = (int)($filters['offset'] ?? 0);

            $stmt = $this->pdo->prepare(
                "SELECT * FROM ngn_2025.directorate_sirs
                 {$whereClause}
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?"
            );

            // Append pagination params
            $params[] = $limit;
            $params[] = $offset;

            $stmt->execute($params);
            $sirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enhance with calculated fields
            foreach ($sirs as &$sir) {
                if ($sir['metadata']) {
                    $sir['metadata'] = json_decode($sir['metadata'], true);
                }

                $createdAt = new \DateTime($sir['created_at']);
                $now = new \DateTime();
                $sir['days_open'] = $now->diff($createdAt)->days;
                $sir['is_overdue'] = $sir['days_open'] > 14 && in_array($sir['status'], ['open', 'in_review']);

                if ($sir['threshold_date']) {
                    $sir['is_past_threshold'] = strtotime($sir['threshold_date']) < time();
                }
            }

            return [
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'returned' => count($sirs),
                ],
                'sirs' => $sirs,
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to list SIRs: " . $e->getMessage());
        }
    }

    /**
     * Update SIR status
     *
     * @param int $sirId SIR ID
     * @param string $newStatus New status
     * @param int $actorUserId User making change
     * @param string $actorRole Role (chairman, director)
     * @return void
     * @throws Exception
     */
    public function updateStatus(int $sirId, string $newStatus, int $actorUserId, string $actorRole = 'director'): void
    {
        try {
            // Get current SIR
            $sir = $this->getSir($sirId);
            if (!$sir) {
                throw new Exception("SIR not found: {$sirId}");
            }

            $oldStatus = $sir['status'];

            // Validate transition
            if (!$this->validateStatusTransition($oldStatus, $newStatus)) {
                throw new Exception("Invalid status transition: {$oldStatus} → {$newStatus}");
            }

            // Update status
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Set timestamp based on status
            switch ($newStatus) {
                case 'in_review':
                    $updateData['claimed_at'] = date('Y-m-d H:i:s');
                    break;
                case 'rant_phase':
                    $updateData['rant_started_at'] = date('Y-m-d H:i:s');
                    break;
                case 'verified':
                    $updateData['verified_at'] = date('Y-m-d H:i:s');
                    break;
                case 'closed':
                    $updateData['closed_at'] = date('Y-m-d H:i:s');
                    break;
            }

            $setClause = implode(", ", array_map(fn($k) => "{$k} = ?", array_keys($updateData)));
            $values = array_values($updateData);
            $values[] = $sirId;

            $stmt = $this->pdo->prepare(
                "UPDATE ngn_2025.directorate_sirs SET {$setClause} WHERE id = ?"
            );
            $stmt->execute($values);

            // Log status change
            $this->auditService->logStatusChange($sirId, $oldStatus, $newStatus, $actorUserId, $actorRole);

            // Send notification about status change
            $recipientId = ($actorRole === 'director') ? $sir['issued_by_user_id'] : $sir['director_user_id'];
            $this->notificationService->notifyStatusChange($sirId, $newStatus, $recipientId, [
                'sir_number' => $sir['sir_number'],
                'objective' => $sir['objective'],
            ]);

        } catch (\PDOException $e) {
            throw new Exception("Failed to update SIR status: " . $e->getMessage());
        }
    }

    /**
     * Add feedback (Rant Phase comment)
     *
     * @param int $sirId SIR ID
     * @param string $feedbackText Feedback text
     * @param int $authorUserId Author user ID
     * @param string $authorRole Author role (chairman, director)
     * @param string $feedbackType Feedback type (default: director_comment)
     * @return int Feedback ID
     * @throws Exception
     */
    public function addFeedback(
        int $sirId,
        string $feedbackText,
        int $authorUserId,
        string $authorRole = 'director',
        string $feedbackType = 'director_comment'
    ): int {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO ngn_2025.sir_feedback (
                    sir_id, feedback_type, feedback_text, author_user_id, author_role, created_at
                ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );

            $stmt->execute([
                $sirId,
                $feedbackType,
                $feedbackText,
                $authorUserId,
                $authorRole,
            ]);

            $feedbackId = (int)$this->pdo->lastInsertId();

            // Log feedback added
            $this->auditService->logFeedbackAdded($sirId, $feedbackId, $authorUserId, $authorRole);

            // Transition to rant_phase if not already there
            $sir = $this->getSir($sirId);
            if ($sir['status'] === 'in_review' && $authorRole === 'director') {
                $this->updateStatus($sirId, 'rant_phase', $authorUserId, $authorRole);
            }

            return $feedbackId;
        } catch (\PDOException $e) {
            throw new Exception("Failed to add feedback: " . $e->getMessage());
        }
    }

    /**
     * Get SIR feedback thread
     *
     * @param int $sirId SIR ID
     * @return array Feedback thread
     * @throws Exception
     */
    public function getFeedback(int $sirId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    id as feedback_id,
                    feedback_type,
                    feedback_text,
                    author_user_id,
                    author_role,
                    created_at
                FROM ngn_2025.sir_feedback
                WHERE sir_id = ?
                ORDER BY created_at ASC"
            );

            $stmt->execute([$sirId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new Exception("Failed to retrieve feedback: " . $e->getMessage());
        }
    }

    /**
     * Get dashboard statistics
     *
     * @param string|null $directorSlug Filter by director (optional)
     * @return array Dashboard statistics
     * @throws Exception
     */
    public function getDashboardStats(?string $directorSlug = null): array
    {
        try {
            // Overall stats
            $whereClause = !empty($directorSlug) ? "WHERE assigned_to_director = ?" : "";
            $params = !empty($directorSlug) ? [strtolower($directorSlug)] : [];

            $stmt = $this->pdo->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                    SUM(CASE WHEN status = 'rant_phase' THEN 1 ELSE 0 END) as rant_phase,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN DATEDIFF(NOW(), updated_at) > 14 AND status IN ('open', 'in_review') THEN 1 ELSE 0 END) as overdue
                FROM ngn_2025.directorate_sirs {$whereClause}"
            );

            $stmt->execute($params);
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);

            // By director stats (only if not filtering by director)
            $byDirector = [];
            if (empty($directorSlug)) {
                $stmt = $this->pdo->prepare(
                    "SELECT
                        assigned_to_director,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                        SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                        AVG(DATEDIFF(verified_at, claimed_at)) as avg_days_to_verify
                    FROM ngn_2025.directorate_sirs
                    WHERE verified_at IS NOT NULL
                    GROUP BY assigned_to_director"
                );

                $stmt->execute();
                $directorStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($directorStats as $stat) {
                    $byDirector[$stat['assigned_to_director']] = $stat;
                }
            }

            // Overdue SIRs
            $stmt = $this->pdo->prepare(
                "SELECT sir_number, objective, assigned_to_director, DATEDIFF(NOW(), updated_at) as days_open, status
                 FROM ngn_2025.directorate_sirs
                 WHERE DATEDIFF(NOW(), updated_at) > 14 AND status IN ('open', 'in_review')
                 ORDER BY updated_at ASC"
            );

            $stmt->execute();
            $overdueSirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'overview' => $overview,
                'by_director' => $byDirector,
                'overdue_sirs' => $overdueSirs,
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to calculate dashboard stats: " . $e->getMessage());
        }
    }

    /**
     * Get overdue SIRs (>14 days without update)
     *
     * @return array List of overdue SIRs
     * @throws Exception
     */
    public function getOverdueSirs(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    id, sir_number, objective, assigned_to_director, director_user_id,
                    DATEDIFF(NOW(), updated_at) as days_open, status
                FROM ngn_2025.directorate_sirs
                WHERE DATEDIFF(NOW(), updated_at) > 14 AND status IN ('open', 'in_review')
                ORDER BY updated_at ASC"
            );

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new Exception("Failed to retrieve overdue SIRs: " . $e->getMessage());
        }
    }

    /**
     * Close SIR (archive and lock)
     *
     * @param int $sirId SIR ID
     * @param int $actorUserId Chairman user ID
     * @return void
     * @throws Exception
     */
    public function closeSir(int $sirId, int $actorUserId): void
    {
        try {
            $sir = $this->getSir($sirId);
            if (!$sir) {
                throw new Exception("SIR not found: {$sirId}");
            }

            if ($sir['status'] !== 'verified') {
                throw new Exception("Only VERIFIED SIRs can be closed. Current status: {$sir['status']}");
            }

            // Update status
            $stmt = $this->pdo->prepare(
                "UPDATE ngn_2025.directorate_sirs
                 SET status = 'closed', closed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            $stmt->execute([$sirId]);

            // Log closure
            $this->auditService->logClosed($sirId, $actorUserId);

            // Notify director
            $this->notificationService->notifyStatusChange($sirId, 'closed', $sir['director_user_id'], [
                'sir_number' => $sir['sir_number'],
                'objective' => $sir['objective'],
            ]);

        } catch (\PDOException $e) {
            throw new Exception("Failed to close SIR: " . $e->getMessage());
        }
    }

    /**
     * Validate SIR data on creation
     *
     * @param array $sirData SIR data
     * @return void
     * @throws Exception
     */
    private function validateSirData(array $sirData): void
    {
        $required = ['objective', 'context', 'deliverable', 'assigned_to_director', 'issued_by_user_id'];

        foreach ($required as $field) {
            if (empty($sirData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Validate field lengths
        if (strlen($sirData['objective']) > 255) {
            throw new Exception("Objective must be <= 255 characters");
        }

        if (strlen($sirData['context']) < 10) {
            throw new Exception("Context must be at least 10 characters");
        }

        if (strlen($sirData['deliverable']) < 10) {
            throw new Exception("Deliverable must be at least 10 characters");
        }
    }

    /**
     * Validate status transition
     *
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return bool True if transition is allowed
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $allowed = [
            'open' => ['in_review'],
            'in_review' => ['rant_phase', 'verified'],
            'rant_phase' => ['in_review', 'verified'],
            'verified' => ['closed'],
            'closed' => [], // Terminal state
        ];

        return in_array($newStatus, $allowed[$currentStatus] ?? []);
    }

    /**
     * Generate unique SIR number (SIR-YYYY-###)
     *
     * @return string SIR number
     * @throws Exception
     */
    private function generateSirNumber(): string
    {
        $year = date('Y');
        $maxRetries = 10;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) as count FROM ngn_2025.directorate_sirs WHERE sir_number LIKE ?"
                );

                $stmt->execute(["{$year}-%"]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $nextNumber = ($result['count'] ?? 0) + 1;

                $sirNumber = sprintf("SIR-%d-%03d", $year, $nextNumber);

                // Verify uniqueness
                $checkStmt = $this->pdo->prepare(
                    "SELECT id FROM ngn_2025.directorate_sirs WHERE sir_number = ?"
                );

                $checkStmt->execute([$sirNumber]);
                if (!$checkStmt->fetch()) {
                    return $sirNumber;
                }
            } catch (\PDOException $e) {
                // Retry
                continue;
            }
        }

        throw new Exception("Failed to generate unique SIR number after {$maxRetries} attempts");
    }

    /**
     * Mark notification sent for SIR
     *
     * @param int $sirId SIR ID
     * @return void
     */
    private function markNotificationSent(int $sirId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE ngn_2025.directorate_sirs
                 SET notification_sent = TRUE, notification_sent_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            $stmt->execute([$sirId]);
        } catch (\PDOException $e) {
            // Non-critical, silently fail
        }
    }
}
