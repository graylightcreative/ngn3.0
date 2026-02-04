<?php

namespace NGN\Lib\Services;

use PDO;

/**
 * Alert Service
 *
 * Manages system alerts and notifications for monitoring thresholds.
 * Implements Bible Ch. 12 alert tier system (P0, P1, P2).
 *
 * Alert Tiers:
 * - P0 (Critical): System failures - SMS/PagerDuty/Phone
 * - P1 (High): Service degradation - Slack/Discord
 * - P2 (Normal): Informational - Email/Weekly digest
 */
class AlertService
{
    private PDO $pdo;
    private array $config;

    /**
     * @param PDO $pdo Database connection
     * @param array $config Configuration array with notification settings
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Create and optionally send an alert
     *
     * @param string $alertType Type of alert (e.g., 'p95_latency', 'webhook_failure')
     * @param string $severity Alert severity: 'p0', 'p1', or 'p2'
     * @param string $message Human-readable alert message
     * @param array $details Additional context (metrics, thresholds, etc.)
     * @param bool $notify Whether to send notification immediately (default: true)
     * @return int The alert ID
     */
    public function createAlert(
        string $alertType,
        string $severity,
        string $message,
        array $details = [],
        bool $notify = true
    ): int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO alert_history
                    (alert_type, severity, message, details, notified_at)
                VALUES
                    (:alert_type, :severity, :message, :details, :notified_at)
            ");

            $stmt->execute([
                'alert_type' => $alertType,
                'severity' => $severity,
                'message' => $message,
                'details' => json_encode($details),
                'notified_at' => $notify ? date('Y-m-d H:i:s') : null
            ]);

            $alertId = (int) $this->pdo->lastInsertId();

            // Send notification if requested
            if ($notify) {
                $this->sendNotification($severity, $alertType, $message, $details);
            }

            return $alertId;
        } catch (\PDOException $e) {
            error_log("AlertService::createAlert failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send notification based on severity tier
     *
     * @param string $severity Alert severity: 'p0', 'p1', or 'p2'
     * @param string $alertType Type of alert
     * @param string $message Alert message
     * @param array $details Additional context
     */
    private function sendNotification(string $severity, string $alertType, string $message, array $details): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $detailsJson = json_encode($details, JSON_PRETTY_PRINT);

        // Log to file (always)
        $logMessage = "[$timestamp] [$severity] [$alertType] $message\nDetails: $detailsJson\n";
        error_log($logMessage, 3, __DIR__ . '/../../storage/logs/alerts.log');

        switch (strtoupper($severity)) {
            case 'P0':
                // Critical: SMS/PagerDuty/Phone
                $this->sendCriticalNotification($alertType, $message, $details);
                break;

            case 'P1':
                // High: Slack/Discord
                $this->sendHighPriorityNotification($alertType, $message, $details);
                break;

            case 'P2':
                // Normal: Email/Weekly digest
                $this->sendNormalNotification($alertType, $message, $details);
                break;
        }
    }

    /**
     * Send P0 critical alert (SMS/PagerDuty/Phone)
     */
    private function sendCriticalNotification(string $alertType, string $message, array $details): void
    {
        // TODO: Implement PagerDuty/SMS integration
        // For now, log to error_log which will appear in system logs
        error_log("🚨 P0 CRITICAL ALERT: [$alertType] $message");

        // If email is configured, send critical email
        if (isset($this->config['critical_email'])) {
            $this->sendEmail(
                $this->config['critical_email'],
                "🚨 P0 CRITICAL: $alertType",
                $this->formatEmailBody($alertType, $message, $details)
            );
        }
    }

    /**
     * Send P1 high priority alert (Slack/Discord)
     */
    private function sendHighPriorityNotification(string $alertType, string $message, array $details): void
    {
        // TODO: Implement Slack/Discord webhook integration
        error_log("⚠️  P1 HIGH: [$alertType] $message");

        // If Slack webhook is configured, send to Slack
        if (isset($this->config['slack_webhook_url'])) {
            $this->sendSlackNotification($alertType, $message, $details);
        }

        // If high priority email is configured, send email
        if (isset($this->config['high_priority_email'])) {
            $this->sendEmail(
                $this->config['high_priority_email'],
                "⚠️  P1 HIGH: $alertType",
                $this->formatEmailBody($alertType, $message, $details)
            );
        }
    }

    /**
     * Send P2 normal notification (Email/Weekly digest)
     */
    private function sendNormalNotification(string $alertType, string $message, array $details): void
    {
        // P2 alerts are informational and typically batched in weekly digests
        // For now, just log them
        error_log("ℹ️  P2 INFO: [$alertType] $message");
    }

    /**
     * Send Slack notification via webhook
     */
    private function sendSlackNotification(string $alertType, string $message, array $details): void
    {
        if (!isset($this->config['slack_webhook_url'])) {
            return;
        }

        $payload = [
            'text' => "⚠️  *P1 Alert: $alertType*",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*$alertType*\n$message"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => []
                ]
            ]
        ];

        // Add detail fields
        foreach ($details as $key => $value) {
            $payload['blocks'][1]['fields'][] = [
                'type' => 'mrkdwn',
                'text' => "*$key:*\n" . (is_array($value) ? json_encode($value) : $value)
            ];
        }

        $ch = curl_init($this->config['slack_webhook_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Send email notification
     */
    private function sendEmail(string $to, string $subject, string $body): void
    {
        $headers = [
            'From: alerts@nextgennoise.com',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Format email body for alerts
     */
    private function formatEmailBody(string $alertType, string $message, array $details): string
    {
        $body = "Alert Type: $alertType\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Message: $message\n\n";
        $body .= "Details:\n";
        $body .= json_encode($details, JSON_PRETTY_PRINT);
        $body .= "\n\n---\n";
        $body .= "NGN 2.0 Alert System\n";

        return $body;
    }

    /**
     * Resolve an alert (mark as resolved)
     *
     * @param int $alertId The alert ID to resolve
     * @return bool Success status
     */
    public function resolveAlert(int $alertId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE alert_history
                SET resolved_at = NOW()
                WHERE id = :alert_id
                AND resolved_at IS NULL
            ");

            return $stmt->execute(['alert_id' => $alertId]);
        } catch (\PDOException $e) {
            error_log("AlertService::resolveAlert failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an alert of the same type was recently fired (debouncing)
     *
     * @param string $alertType Type of alert to check
     * @param int $windowMinutes Time window to check (default: 15 minutes)
     * @return bool True if a similar alert was recently fired
     */
    public function wasRecentlyFired(string $alertType, int $windowMinutes = 15): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM alert_history
                WHERE alert_type = :alert_type
                AND created_at >= DATE_SUB(NOW(), INTERVAL :window_minutes MINUTE)
            ");

            $stmt->execute([
                'alert_type' => $alertType,
                'window_minutes' => $windowMinutes
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (\PDOException $e) {
            error_log("AlertService::wasRecentlyFired failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent alerts
     *
     * @param int $limit Maximum number of alerts to return
     * @param string|null $severity Filter by severity ('p0', 'p1', 'p2')
     * @return array Array of recent alerts
     */
    public function getRecentAlerts(int $limit = 50, ?string $severity = null): array
    {
        try {
            $sql = "SELECT * FROM alert_history";

            if ($severity !== null) {
                $sql .= " WHERE severity = :severity";
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);

            if ($severity !== null) {
                $stmt->bindValue(':severity', $severity);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("AlertService::getRecentAlerts failed: " . $e->getMessage());
            return [];
        }
    }
}
