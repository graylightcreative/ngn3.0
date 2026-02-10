<?php

namespace App\Admin\Controllers;

require_once __DIR__ . \'../../lib/bootstrap.php\'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Lib\Email\Mailer; // Ensure Mailer is available
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MailingListController
{
    private PDO $pdo;
    private Logger $logger;
    private Config $config;
    private Mailer $mailer;

    public function __construct(PDO $pdo, Logger $logger, Config $config, Mailer $mailer)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->mailer = $mailer;
    }

    /**
     * Create a new email campaign.
     * Expects POST data: subject, body_html, frequency_type, target_group_id, provider_preference
     * target_group_id: NULL for 'All Users', 3 for Artists, 7 for Labels.
     * provider_preference: 'Auto-Detect', 'Force Mailchimp', 'Force SMTP.com'
     */
    public function createCampaign(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();

        $subject = $data['subject'] ?? null;
        $bodyHtml = $data['body_html'] ?? null;
        $frequencyType = $data['frequency_type'] ?? null;
        $targetGroupId = $data['target_group_id'] ?? null;
        $providerPreference = $data['provider_preference'] ?? 'Auto-Detect'; // New preference field

        // Basic validation
        $errors = [];
        if (empty($subject)) $errors[] = 'Subject is required.';
        if (empty($bodyHtml)) $errors[] = 'Body HTML is required.';
        if (empty($frequencyType) || !in_array($frequencyType, ['daily', 'weekly', 'monthly', 'once'])) {
            $errors[] = 'Invalid frequency type. Allowed values: daily, weekly, monthly, once.';
        }
        // Validate target_group_id: must be NULL, 3, or 7.
        if ($targetGroupId !== null && !in_array((int)$targetGroupId, [3, 7])) {
            $errors[] = 'Invalid target group ID. Allowed values: 3 (Artists), 7 (Labels), or null (All Users).';
        }
        // Validate provider preference
        if (!in_array($providerPreference, ['Auto-Detect', 'Force Mailchimp', 'Force SMTP.com'])) {
            $errors[] = 'Invalid provider preference. Allowed values: Auto-Detect, Force Mailchimp, Force SMTP.com.';
        }

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson(['success' => false, 'errors' => $errors]);
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `ngn_2025`.`email_campaigns` (subject, body_html, frequency_type, target_group_id, status, provider_preference) VALUES (:subject, :body_html, :frequency_type, :target_group_id, 'scheduled', :provider_preference)"
            );

            $success = $stmt->execute([
                ':subject' => $subject,
                ':body_html' => $bodyHtml,
                ':frequency_type' => $frequencyType,
                ':target_group_id' => $targetGroupId,
                ':provider_preference' => $providerPreference,
            ]);

            if ($success) {
                $campaignId = $this->pdo->lastInsertId();
                $this->logger->info("Created new email campaign (ID: {$campaignId}) with preference: {$providerPreference}.");
                return $response->withStatus(201)->withJson(['success' => true, 'message' => 'Campaign created successfully.', 'data' => ['id' => $campaignId]]);
            } else {
                $this->logger->error("Failed to create email campaign.");
                return $response->withStatus(500)->withJson(['success' => false, 'message' => 'Failed to create campaign.']);
            }

        } catch (\Throwable $e) {
            $this->logger->error("Error creating email campaign: " . $e->getMessage());
            return $response->withStatus(500)->withJson(['success' => false, 'message' => 'An internal error occurred.']);
        }
    }

    /**
     * Get the status of the email queue.
     * Returns counts of pending, sent, and failed emails.
     */
    public function getQueueStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $pendingStmt = $this->pdo->query("SELECT COUNT(*) FROM `ngn_2025`.`email_queue` WHERE status = 'pending'");
            $pendingCount = $pendingStmt->fetchColumn();

            $sentStmt = $this->pdo->query("SELECT COUNT(*) FROM `ngn_2025`.`email_queue` WHERE status = 'sent'");
            $sentCount = $sentStmt->fetchColumn();

            $failedStmt = $this->pdo->query("SELECT COUNT(*) FROM `ngn_2025`.`email_queue` WHERE status = 'failed'");
            $failedCount = $failedStmt->fetchColumn();

            return $response->withJson([
                'success' => true,
                'data' => [
                    'pending' => (int)$pendingCount,
                    'sent' => (int)$sentCount,
                    'failed' => (int)$failedCount
                ]
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching email queue status: " . $e->getMessage());
            return $response->withStatus(500)->withJson(['success' => false, 'message' => 'Failed to retrieve queue status.']);
        }
    }

    /**
     * Pause or resume the email queue processing.
     * Expects POST data: {"paused": true|false}
     * This will affect campaigns currently marked as 'scheduled' or 'sending'.
     */
    public function pauseQueue(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $paused = $data['paused'] ?? null;

        if ($paused === null || !is_bool($paused)) {
            return $response->withStatus(400)->withJson(['success' => false, 'message' => 'Invalid payload. \'paused\' must be a boolean (true/false).']);
        }

        try {
            // Logic to update a flag indicating the queue processor should pause/resume.
            // This might involve updating a dedicated settings table or a file.
            // For simplicity, we log the request and indicate the action.
            
            $statusMessage = $paused ? 'paused' : 'resumed';
            $this->logger->info("Mailing list queue operation requested: {$statusMessage}.");

            // Placeholder for the actual mechanism to signal the scheduler/dispatcher.
            // A robust implementation might write to a file like storage/mailing_list_paused.flag
            // or update a record in a settings table.
            
            return $response->withJson(['success' => true, 'message' => "Mailing list queue operation {$statusMessage} requested. A flag would be set for the scheduler/worker to check."]);

        } catch (\Throwable $e) {
            $this->logger->error("Error pausing/resuming queue: " . $e->getMessage());
            return $response->withStatus(500)->withJson(['success' => false, 'message' => 'An internal error occurred.']);
        }
    }

    // Helper method to fetch user data based on target group ID.
    // This method assumes direct DB access. In a larger app, this might be a repository.
    private function getUsersForTargetGroup(int|string|null $targetGroup): array
    {
        $users = [];
        try {
            // Handle 'All Users' by fetching from the users table directly.
            if ($targetGroup === null || $targetGroup === 'all') { 
                $userStmt = $this->pdo->query("SELECT id FROM `ngn_2025`.`users` WHERE status = 'active'");
            } elseif ((int)$targetGroup === 3) { // Artists (Role ID 3)
                $userStmt = $this->pdo->prepare("SELECT id FROM `ngn_2025`.`users` WHERE role_id = 3 AND status = 'active'");
                $userStmt->execute();
            } elseif ((int)$targetGroup === 7) { // Labels (Role ID 7)
                $stmt = $this->pdo->prepare("SELECT id FROM `ngn_2025`.`users` WHERE role_id = 7 AND status = 'active'");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $this->logger->warning("Unknown target group ID: {$targetGroup}.");
                return []; // Return empty array for unknown group
            }
            
            if (isset($userStmt)) {
                $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
            }

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching users for target group {$targetGroup}: " . $e->getMessage());
        }
        return $users;
    }
}

// Note: The actual routing logic to map URLs to these controller methods would typically be handled by a framework.
// Example: Using Slim Framework or similar routing setup.
/*
$router = new \Slim\Slim(); // Or your chosen router

// Inject dependencies into the controller
$pdo = $pdo ?? NGN\Lib\Database\ConnectionFactory::read(new Config());
$logger = $logger ?? new Logger('admin_api');
$config = $config ?? new Config();
$mailer = new Mailer($pdo, $logger, $config); // Assuming Mailer is instantiated here or passed in
$mailingListController = new MailingListController($pdo, $logger, $config, $mailer);

$router->post('/admin/mailing-list/create', [$mailingListController, 'createCampaign']);
$router->get('/admin/mailing-list/queue', [$mailingListController, 'getQueueStatus']);
$router->post('/admin/mailing-list/pause', [$mailingListController, 'pauseQueue']);

$router->run();
*/

?>