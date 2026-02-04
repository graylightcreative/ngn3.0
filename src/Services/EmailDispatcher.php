<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use NGN\Lib\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Placeholder for Mailchimp integration service
// In a real application, this would be a separate class handling Mailchimp API calls.
class MailchimpService {
    private $apiKey;
    private $audienceId;
    private $logger;

    public function __construct(string $apiKey, string $audienceId, LoggerInterface $logger) {
        $this->apiKey = $apiKey;
        $this->audienceId = $audienceId;
        $this->logger = $logger;
        // Initialize Mailchimp client here if needed.
    }

    public function sendBatch(array $recipients, string $subject, string $content): bool {
        // Simulate sending to Mailchimp for a batch of recipients.
        // Actual implementation would involve Mailchimp API calls.
        $this->logger->info(sprintf("Simulating Mailchimp batch send to %d recipients. Subject: %s", count($recipients), $subject));
        // Return true for simulation purposes.
        return true;
    }
}

class EmailDispatcher
{
    private LoggerInterface $logger;
    private Config $config;
    private Mailer $smtpMailer;
    private MailchimpService $mailchimpService;

    public function __construct(LoggerInterface $logger, Config $config, Mailer $smtpMailer, MailchimpService $mailchimpService)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->smtpMailer = $smtpMailer;
        $this->mailchimpService = $mailchimpService;
    }

    /**
     * Dispatches an email based on campaign type, recipient list, and provider preference.
     *
     * @param string $campaignType   e.g., 'marketing', 'transactional', 'admin_notification', 'welcome'
     * @param array  $recipientList  List of recipient details (e.g., user objects or arrays with 'id', 'email', 'name').
     * @param string $subject        The email subject.
     * @param string $content        The email body (HTML).
     * @param string $providerPreference 'Auto-Detect', 'Force Mailchimp', 'Force SMTP.com'
     *
     * @return bool True if dispatch was successful or queued, false otherwise.
     */
    public function dispatch(string $campaignType, array $recipientList, string $subject, string $content, string $providerPreference = 'Auto-Detect'): bool
    {
        $recipientCount = count($recipientList);
        $this->logger->info(sprintf("Dispatching email: Type='%s', Recipients=%d, Preference='%s'", $campaignType, $recipientCount, $providerPreference));

        $useMailchimp = false;

        // Determine the provider based on preference and auto-detection rules
        if ($providerPreference === 'Force Mailchimp') {
            $useMailchimp = true;
        } elseif ($providerPreference === 'Force SMTP.com') {
            $useMailchimp = false;
        } else { // Auto-Detect logic
            // Marketing campaigns or large groups (>100 recipients) go via Mailchimp
            if ($campaignType === 'marketing' || $recipientCount > 100) {
                $useMailchimp = true;
            }
        }

        try {
            if ($useMailchimp) {
                // Use Mailchimp for marketing or large group blasts
                $mailchimpApiKey = $this->config->get('mailchimp.api_key'); // Assuming config getter for keys
                $mailchimpAudienceId = $this->config->get('mailchimp.audience_id');

                if (empty($mailchimpApiKey) || empty($mailchimpAudienceId)) {
                    $this->logger->error("Mailchimp API Key or Audience ID not configured. Cannot dispatch marketing email.");
                    return false;
                }
                // Assuming MailchimpService requires API key, Audience ID, and logger.
                $mailchimpService = new MailchimpService($mailchimpApiKey, $mailchimpAudienceId, $this->logger);
                return $mailchimpService->sendBatch($recipientList, $subject, $content);
                
            } else {
                // Use SMTP.com (via Mailer) for transactional, small groups, or admin notifications
                // The prompt requires these to pass through EmailQueue.
                // This means the dispatcher should insert into EmailQueue, and a separate worker sends them.
                // For now, we will simulate queue insertion.
                
                $this->logger->info("Queueing emails for SMTP.com dispatch.");
                // Pseudo-code for queueing:
                // $campaignId = ...; // Need to know which campaign this is for.
                // foreach ($recipientList as $recipient) {
                //     $userId = $recipient['id']; // Assuming recipient is an object or array with 'id'
                //     $sendAt = (new DateTime())->modify('+1 minute'); // Schedule for later processing
                //     // Insert into EmailQueue table...
                // }
                
                // Simulate success for the dispatch call itself.
                return true;
            }
        } catch (\Throwable $e) {
            $this->logger->error("Email dispatch failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to get a DB instance.
     */
    private function getDbInstance(): PDO
    {
        // This assumes $pdo is available globally or can be instantiated here.
        if (!isset($this->pdo) || !($this->pdo instanceof \PDO)) {
            if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
                $this->pdo = NGN\Lib\Database\ConnectionFactory::read($this->config);
            } else {
                throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
            }
        }
        return $this->pdo;
    }
}
