<?php

namespace NGN\Lib\Governance;

use PDO;
use Exception;

/**
 * SirNotificationService
 *
 * Handles push notifications for SIR assignments, reminders, and status updates.
 * Integrates with Firebase Cloud Messaging and existing notification system.
 *
 * Bible Reference: Chapter 31 - Mobile notification architecture
 */
class SirNotificationService
{
    private PDO $pdo;
    private DirectorateRoles $roles;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param DirectorateRoles $roles Director roles helper
     */
    public function __construct(PDO $pdo, DirectorateRoles $roles)
    {
        $this->pdo = $pdo;
        $this->roles = $roles;
    }

    /**
     * Send "SIR Assigned" notification to director
     *
     * @param int $sirId SIR ID
     * @param int $directorUserId Director user ID
     * @param array $sirData SIR data (for notification content)
     * @return void
     * @throws Exception
     */
    public function notifySirAssigned(int $sirId, int $directorUserId, array $sirData = []): void
    {
        $payload = $this->createNotificationPayload('sir_assigned', [
            'sir_id' => $sirId,
            'sir_number' => $sirData['sir_number'] ?? null,
            'objective' => $sirData['objective'] ?? 'New SIR Assigned',
            'priority' => $sirData['priority'] ?? 'medium',
            'threshold_date' => $sirData['threshold_date'] ?? null,
            'director_user_id' => $directorUserId,
        ]);

        $this->sendNotification($directorUserId, $payload, $sirId, 'sir_assigned');
    }

    /**
     * Send reminder for overdue SIR (>14 days)
     *
     * @param int $sirId SIR ID
     * @param int $directorUserId Director user ID
     * @param array $sirData SIR data
     * @return void
     * @throws Exception
     */
    public function sendReminder(int $sirId, int $directorUserId, array $sirData = []): void
    {
        // Check if reminder already sent today
        if ($this->reminderSentToday($sirId, $directorUserId)) {
            return; // Don't spam reminders
        }

        $daysOpen = $sirData['days_open'] ?? 14;

        $payload = $this->createNotificationPayload('sir_reminder', [
            'sir_id' => $sirId,
            'sir_number' => $sirData['sir_number'] ?? null,
            'objective' => $sirData['objective'] ?? 'Action Needed',
            'days_open' => $daysOpen,
            'director_user_id' => $directorUserId,
        ]);

        $this->sendNotification($directorUserId, $payload, $sirId, 'sir_reminder');

        // Update last_reminder_sent_at
        $this->updateLastReminderSent($sirId);
    }

    /**
     * Send notification when status changes
     *
     * @param int $sirId SIR ID
     * @param string $newStatus New status
     * @param int $recipientUserId Recipient user ID
     * @param array $sirData SIR data
     * @return void
     * @throws Exception
     */
    public function notifyStatusChange(int $sirId, string $newStatus, int $recipientUserId, array $sirData = []): void
    {
        // Map status to notification type
        $notificationTypeMap = [
            'rant_phase' => 'rant_phase_update',
            'verified' => 'sir_verified',
            'closed' => 'sir_closed',
        ];

        $notificationType = $notificationTypeMap[$newStatus] ?? 'status_update';

        $payload = $this->createNotificationPayload($notificationType, [
            'sir_id' => $sirId,
            'sir_number' => $sirData['sir_number'] ?? null,
            'status' => $newStatus,
            'objective' => $sirData['objective'] ?? null,
        ]);

        $this->sendNotification($recipientUserId, $payload, $sirId, $notificationType);
    }

    /**
     * Send notification when SIR is ready for one-tap verification
     *
     * @param int $sirId SIR ID
     * @param int $directorUserId Director user ID
     * @param array $sirData SIR data
     * @return void
     * @throws Exception
     */
    public function notifyVerificationReady(int $sirId, int $directorUserId, array $sirData = []): void
    {
        $payload = $this->createNotificationPayload('sir_verification_ready', [
            'sir_id' => $sirId,
            'sir_number' => $sirData['sir_number'] ?? null,
            'objective' => $sirData['objective'] ?? null,
            'one_tap_verify_url' => '/api/v1/governance/sir/' . $sirId . '/verify',
            'director_user_id' => $directorUserId,
        ]);

        $this->sendNotification($directorUserId, $payload, $sirId, 'sir_verification_ready');
    }

    /**
     * Create notification payload
     *
     * @param string $type Notification type (sir_assigned, sir_reminder, etc.)
     * @param array $data SIR data for context
     * @return array Notification payload
     */
    private function createNotificationPayload(string $type, array $data = []): array
    {
        $titles = [
            'sir_assigned' => 'New SIR Assigned',
            'sir_reminder' => 'Action Needed: SIR Overdue',
            'rant_phase_update' => 'Feedback Received',
            'sir_verified' => 'SIR Verified',
            'sir_closed' => 'SIR Closed',
            'sir_verification_ready' => 'Ready to Verify',
        ];

        $sirId = $data['sir_id'] ?? null;
        $sirNumber = $data['sir_number'] ?? 'SIR-????-???';

        $basePayload = [
            'type' => $type,
            'user_id' => $data['director_user_id'] ?? null,
            'title' => $titles[$type] ?? 'SIR Update',
            'data' => [
                'sir_id' => $sirId,
                'sir_number' => $sirNumber,
                'action_url' => '/admin/governance/sir/' . $sirId,
                'one_tap_verify_url' => $data['one_tap_verify_url'] ?? null,
            ],
        ];

        // Add type-specific messages and actions
        switch ($type) {
            case 'sir_assigned':
                $basePayload['message'] = sprintf(
                    '%s assigned to you (Priority: %s, Due: %s)',
                    $data['objective'] ?? 'New SIR',
                    ucfirst($data['priority'] ?? 'medium'),
                    $data['threshold_date'] ?? 'TBD'
                );
                break;

            case 'sir_reminder':
                $basePayload['message'] = sprintf(
                    '%s has been open for %d days. Review and respond.',
                    $sirNumber,
                    $data['days_open'] ?? 14
                );
                $basePayload['priority'] = 'high';
                break;

            case 'rant_phase_update':
                $basePayload['message'] = sprintf(
                    'Chairman has responded to your feedback on %s',
                    $sirNumber
                );
                break;

            case 'sir_verified':
                $basePayload['message'] = sprintf(
                    '%s verified and approved',
                    $sirNumber
                );
                break;

            case 'sir_closed':
                $basePayload['message'] = sprintf(
                    '%s closed and archived',
                    $sirNumber
                );
                break;

            case 'sir_verification_ready':
                $basePayload['message'] = sprintf(
                    '%s is ready for your verification',
                    $sirNumber
                );
                $basePayload['actions'] = [
                    [
                        'action' => 'verify',
                        'title' => 'One-Tap Verify',
                    ],
                ];
                break;
        }

        return $basePayload;
    }

    /**
     * Send notification to user
     *
     * @param int $userId Recipient user ID
     * @param array $payload Notification payload
     * @param int $sirId SIR ID (for tracking)
     * @param string $notificationType Notification type
     * @return void
     * @throws Exception
     */
    private function sendNotification(int $userId, array $payload, int $sirId, string $notificationType): void
    {
        try {
            // Record notification in sir_notifications table
            $stmt = $this->pdo->prepare(
                "INSERT INTO ngn_2025.sir_notifications (
                    sir_id, notification_type, recipient_user_id, sent_at
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            );

            $stmt->execute([
                $sirId,
                $notificationType,
                $userId,
            ]);

            // TODO: Integrate with push notification service (Firebase FCM)
            // For now, log that notification was queued
            // The actual delivery would be handled by a separate service:
            // $pushService = new PushNotificationService($this->pdo);
            // $pushService->sendPush($userId, $payload['title'], $payload['message'] ?? '', $payload['data']);

        } catch (\PDOException $e) {
            throw new Exception("Failed to send notification: " . $e->getMessage());
        }
    }

    /**
     * Check if reminder was already sent today
     *
     * @param int $sirId SIR ID
     * @param int $directorUserId Director user ID
     * @return bool True if reminder already sent today
     */
    private function reminderSentToday(int $sirId, int $directorUserId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM ngn_2025.sir_notifications
                 WHERE sir_id = ? AND recipient_user_id = ?
                 AND notification_type = 'sir_reminder'
                 AND DATE(sent_at) = CURDATE()"
            );

            $stmt->execute([$sirId, $directorUserId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
        } catch (\PDOException $e) {
            // If query fails, allow sending (fail open)
            return false;
        }
    }

    /**
     * Update last_reminder_sent_at on SIR
     *
     * @param int $sirId SIR ID
     * @return void
     */
    private function updateLastReminderSent(int $sirId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE ngn_2025.directorate_sirs
                 SET last_reminder_sent_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            $stmt->execute([$sirId]);
        } catch (\PDOException $e) {
            // Non-critical, silently fail
        }
    }

    /**
     * Get pending notifications for user
     *
     * @param int $userId User ID
     * @param int $limit Limit results
     * @return array List of pending notifications
     * @throws Exception
     */
    public function getPendingNotifications(int $userId, int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    sn.id,
                    sn.sir_id,
                    sn.notification_type,
                    sn.sent_at,
                    ds.sir_number,
                    ds.objective,
                    ds.priority,
                    ds.status
                FROM ngn_2025.sir_notifications sn
                JOIN ngn_2025.directorate_sirs ds ON sn.sir_id = ds.id
                WHERE sn.recipient_user_id = ?
                ORDER BY sn.sent_at DESC
                LIMIT ?"
            );

            $stmt->execute([$userId, $limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new Exception("Failed to retrieve notifications: " . $e->getMessage());
        }
    }
}
