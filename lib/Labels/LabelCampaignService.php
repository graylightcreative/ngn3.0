<?php
namespace NGN\Lib\Labels;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class LabelCampaignService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'label_campaigns');
    }

    public function listCampaigns(int $labelId): array
    {
        try {
            $stmt = $this->read->prepare("SELECT * FROM `ngn_2025`.`email_campaigns` WHERE label_id = ? ORDER BY created_at DESC");
            $stmt->execute([$labelId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->logger->error('list_campaigns_failed', ['label_id' => $labelId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function getCampaign(int $campaignId, int $labelId): ?array
    {
        try {
            $stmt = $this->read->prepare("SELECT * FROM `ngn_2025`.`email_campaigns` WHERE id = ? AND label_id = ?");
            $stmt->execute([$campaignId, $labelId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $this->logger->error('get_campaign_failed', ['campaign_id' => $campaignId, 'label_id' => $labelId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function createCampaign(int $labelId, string $subject, string $bodyHtml, string $targetAudience, string $status): array
    {
        try {
            $stmt = $this->write->prepare("
                INSERT INTO `ngn_2025`.`email_campaigns` (label_id, subject, body_html, frequency_type, target_group_id, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([
                $labelId,
                $subject,
                $bodyHtml,
                'once', // Default for now
                null, // target_group_id, TODO: map from targetAudience
                $status
            ]);

            if ($success) {
                $campaignId = (int)$this->write->lastInsertId();
                $this->logger->info('campaign_created', ['label_id' => $labelId, 'campaign_id' => $campaignId]);
                return ['success' => true, 'id' => $campaignId, 'message' => 'Campaign created successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to create campaign.'];
            }
        } catch (\Throwable $e) {
            $this->logger->error('create_campaign_failed', ['label_id' => $labelId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error creating campaign: ' . $e->getMessage()];
        }
    }

    public function updateCampaign(int $campaignId, int $labelId, string $subject, string $bodyHtml, string $targetAudience, string $status): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`email_campaigns`
                SET subject = ?, body_html = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND label_id = ?
            ");
            $success = $stmt->execute([
                $subject,
                $bodyHtml,
                $status,
                $campaignId,
                $labelId
            ]);

            if ($success) {
                $this->logger->info('campaign_updated', ['label_id' => $labelId, 'campaign_id' => $campaignId]);
            }
            return $success;
        } catch (\Throwable $e) {
            $this->logger->error('update_campaign_failed', ['campaign_id' => $campaignId, 'label_id' => $labelId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteCampaign(int $campaignId, int $labelId): bool
    {
        try {
            $stmt = $this->write->prepare("DELETE FROM `ngn_2025`.`email_campaigns` WHERE id = ? AND label_id = ?");
            $success = $stmt->execute([$campaignId, $labelId]);
            if ($success) {
                $this->logger->info('campaign_deleted', ['label_id' => $labelId, 'campaign_id' => $campaignId]);
            }
            return $success;
        } catch (\Throwable $e) {
            $this->logger->error('delete_campaign_failed', ['campaign_id' => $campaignId, 'label_id' => $labelId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendCampaign(int $campaignId, int $labelId): bool
    {
        // TODO: Implement logic to add campaign to EmailQueue
        return false;
    }
}
