<?php

/**
 * Cron Job: Send SIR Reminders
 *
 * Sends reminders for overdue SIRs (>14 days without update)
 * Schedule: 0 9 * * * (Daily at 9:00 AM UTC)
 *
 * Bible Reference: Chapter 31 - SIR overdue monitoring
 * Core Rule: "No SIR stays OPEN for >14 days without update"
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Governance\SirRegistryService;
use NGN\Lib\Governance\SirNotificationService;
use NGN\Lib\Governance\DirectorateRoles;

try {
    // Initialize services
    $config = new Config();
    $pdo = ConnectionFactory::write($config);

    if (!$pdo) {
        error_log("[SIR Reminders] Database connection failed");
        exit(1);
    }

    $roles = new DirectorateRoles();
    $sirService = new SirRegistryService($pdo, $roles);
    $notificationService = new SirNotificationService($pdo, $roles);

    error_log("=== SIR Reminder Job Started ===");

    // Get overdue SIRs
    $overdueSirs = $sirService->getOverdueSirs();

    $remindersSent = 0;
    $skipped = 0;

    foreach ($overdueSirs as $sir) {
        try {
            $sirId = (int)$sir['id'];
            $directorUserId = (int)$sir['director_user_id'];
            $daysOpen = (int)$sir['days_open'];

            // Check if reminder already sent today (to avoid spam)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) as count FROM ngn_2025.sir_notifications
                 WHERE sir_id = ? AND recipient_user_id = ?
                 AND notification_type = 'sir_reminder'
                 AND DATE(sent_at) = CURDATE()"
            );
            $stmt->execute([$sirId, $directorUserId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $skipped++;
                error_log("  ⊘ SIR-{$sir['sir_number']} - Reminder already sent today");
                continue;
            }

            // Send reminder
            $sirData = [
                'sir_id' => $sirId,
                'sir_number' => $sir['sir_number'],
                'objective' => $sir['objective'] ?? 'Pending Review',
                'days_open' => $daysOpen,
            ];

            $notificationService->sendReminder($sirId, $directorUserId, $sirData);

            $remindersSent++;

            error_log(
                "  ✓ SIR-{$sir['sir_number']} - Reminder sent " .
                "(Open {$daysOpen} days, Status: {$sir['status']})"
            );

        } catch (Exception $e) {
            error_log("  ✗ Error processing SIR-{$sir['sir_number']}: " . $e->getMessage());
            continue;
        }
    }

    // Log summary
    error_log("=== SIR Reminder Job Complete ===");
    error_log("  Overdue SIRs found: " . count($overdueSirs));
    error_log("  Reminders sent: {$remindersSent}");
    error_log("  Skipped (already reminded): {$skipped}");

    // Exit with success code
    exit(0);

} catch (Exception $e) {
    error_log("[SIR Reminders] Fatal error: " . $e->getMessage());
    error_log("[SIR Reminders] Trace: " . $e->getTraceAsString());
    exit(1);
}
