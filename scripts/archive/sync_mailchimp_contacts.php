<?php

// This script synchronizes users from NGN to Mailchimp Audience.
// It is intended to be run daily via cron or a scheduler.

require_once __DIR__ . '/../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Assuming MailchimpMarketing class is available and configured.
// If not, this script will log a warning and exit.
if (!class_exists('MailchimpMarketing')) {
    error_log("MailchimpMarketing class not found. Cannot perform Mailchimp sync.");
    exit("MailchimpMarketing class not found.");
}

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/mailchimp_sync.log';
$artistRoleId = 3;
$labelRoleId = 7;

// --- Setup Logger ---
try {
    $logger = new Logger('mailchimp_sync');
    $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));

    // Assume $pdo and $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }
    
    // Ensure Mailchimp API Key and Audience ID are set
    $mailchimpApiKey = $_ENV['MAILCHIMP_API_KEY'] ?? null;
    $mailchimpAudienceId = $_ENV['MAILCHIMP_AUDIENCE_ID'] ?? null;
    
    if (empty($mailchimpApiKey) || empty($mailchimpAudienceId)) {
        throw new \RuntimeException("Mailchimp API Key or Audience ID not configured.");
    }

} catch (\Throwable $e) {
    error_log("Mailchimp Sync setup error: " . $e->getMessage());
    exit("Mailchimp Sync setup failed.");
}

$logger->info("Mailchimp synchronization worker started.");

// --- Synchronization Logic ---
try {
    $marketing = new MailchimpMarketing($mailchimpApiKey);

    // Fetch Artists
    $artists = $this->fetchUsersByRole($artistRoleId);
    $this->syncUsersToMailchimp($marketing, $mailchimpAudienceId, $artists, 'Artist');

    // Fetch Labels
    $labels = $this->fetchUsersByRole($labelRoleId);
    $this->syncUsersToMailchimp($marketing, $mailchimpAudienceId, $labels, 'Label');

    $logger->info("Mailchimp synchronization finished.");

} catch (\Throwable $e) {
    $logger->critical("Mailchimp sync worker encountered a critical error: " . $e->getMessage());
    exit("Mailchimp sync failed.");
}

/**
 * Fetches users from the database by their role ID.
 * In a real application, this might call an internal API like GET /api/v1/users/by-role?id=X.
 * For a script, direct DB access is more efficient.
 * 
 * @param int $roleId The role ID to fetch users for.
 * @return array An array of user IDs.
 */
function fetchUsersByRole(int $roleId): array
{
    global $pdo, $logger;
    $users = [];
    try {
        $stmt = $pdo->prepare("SELECT Id FROM users WHERE role_id = :role_id AND status = 'active'");
        $stmt->execute([':role_id' => $roleId]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        $logger->error("Error fetching users for role ID {$roleId}: " . $e->getMessage());
    }
    return $users;
}

/**
 * Syncs a list of user IDs to Mailchimp Audience with a specific tag.
 * 
 * @param MailchimpMarketing $marketing       The MailchimpMarketing client instance.
 * @param string             $audienceId    The Mailchimp Audience ID.
 * @param array              $userIds       An array of user IDs.
 * @param string             $tag           The tag to apply (e.g., 'Artist', 'Label').
 */
function syncUsersToMailchimp(MailchimpMarketing $marketing, string $audienceId, array $userIds, string $tag):
 void
{
    global $logger;
    $count = 0;
    
    if (empty($userIds)) {
        $logger->info("No users found for tag: {$tag}. Skipping sync.");
        return;
    }

    $logger->info(sprintf("Syncing %d users for tag: %s.", count($userIds), $tag));

    foreach ($userIds as $userId) {
        try {
            // Fetch user details needed for Mailchimp (e.g., email, name)
            // This would ideally use a User service or repository.
            // For simplicity, assuming direct DB access to 'users' table.
            $userStmt = $pdo->prepare("SELECT Email, Name FROM users WHERE Id = :user_id LIMIT 1");
            $userStmt->execute([':user_id' => $userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['Email'])) {
                $logger->warning("User ID {$userId} not found or has no email. Skipping Mailchimp sync.");
                continue;
            }

            $memberData = [
                'email_address' => $user['Email'],
                'status' => 'subscribed',
                'email_type' => 'html',
                'merge_fields' => [
                    'FNAME' => $user['Name'] ? explode(' ', $user['Name'])[0] : '', // First name
                    'LNAME' => $user['Name'] ? implode(' ', array_slice(explode(' ', $user['Name']), 1)) : '', // Last name
                ],
                'tags' => [$tag] // Apply the role-based tag
            ];
            
            // Add or update subscriber in Mailchimp
            $marketing->addListMember($audienceId, $memberData);
            $count++;

        } catch (\Throwable $e) {
            $logger->error("Error syncing user ID {$userId} to Mailchimp: " . $e->getMessage());
        }
    }
    $logger->info(sprintf("Successfully synced %d users to Mailchimp with tag '%s'.", $count, $tag));
}

?>
