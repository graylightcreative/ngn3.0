<?php
/**
 * Label Dashboard - Email Campaigns Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'Email Campaigns';
$currentPage = 'campaigns';

// Initialize variables
$action = $_GET['action'] ?? null;
$campaignId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editCampaign = null;
$campaigns = [];
$error = $success = null;

// Instantiate the service
$config = dashboard_get_config();
$campaignService = new \NGN\Lib\Labels\LabelCampaignService($config);

// Fetch campaigns for this label
if ($entity) {
    try {
        $campaigns = $campaignService->listCampaigns($entity['id']);
        
        if ($campaignId && $action === 'edit') {
            $editCampaign = $campaignService->getCampaign($campaignId, $entity['id']);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error fetching campaigns: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $post_action = $_POST['action'] ?? $action;

        if ($post_action === 'delete') {
            $campaignId_to_delete = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId_to_delete > 0) {
                try {
                    if ($campaignService->deleteCampaign($campaignId_to_delete, $entity['id'])) {
                        $success = 'Campaign deleted successfully.';
                    } else {
                        $error = 'Campaign not found or you do not have permission to delete it.';
                    }
                } catch (\Throwable $e) {
                    $error = 'Error deleting campaign: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid campaign ID for deletion.';
            }
        } else {
            $subject = trim($_POST['subject'] ?? ''); // Subject
            $bodyHtml = trim($_POST['body_html'] ?? ''); // Body HTML
            $targetAudience = $_POST['target_audience'] ?? 'all';
            $status = $_POST['status'] ?? 'draft'; // draft, scheduled, sent

            if (empty($subject) || empty($bodyHtml)) {
                $error = 'Subject and Body are required.';
            } else {
                try {
                    if ($action === 'edit' && $campaignId) {
                        $campaignService->updateCampaign($campaignId, $entity['id'], $subject, $bodyHtml, $targetAudience, $status);
                        $success = 'Campaign updated successfully!';
                    } else {
                        $campaignService->createCampaign($entity['id'], $subject, $bodyHtml, $targetAudience, $status);
                        $success = 'Campaign created successfully!';
                    }
                    
                    $action = 'list'; // Go back to list view
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                } catch (\Throwable $e) {
                    $error = 'Error saving campaign: ' . $e->getMessage();
                }
            }
        }
        // Refresh list after any POST action
        $campaigns = $campaignService->listCampaigns($entity['id']);
    }
}

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Email Campaigns</h1>
        <p class="page-subtitle">Manage your email marketing campaigns</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || ($action === 'edit' && $editCampaign)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Campaign' : 'Create New Campaign' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-input" required
                           value="<?= htmlspecialchars($editCampaign['subject'] ?? '') ?>"
                           placeholder="e.g., New Release: [Artist Name] - [Song Title]">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Body (HTML) *</label>
                    <textarea name="body_html" class="form-textarea" rows="10" required
                              placeholder="Your email content here..."><?= htmlspecialchars($editCampaign['body_html'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_audience" class="form-input">
                        <option value="all" <?= ($editCampaign['target_audience'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Label Followers</option>
                        <option value="artist_followers" <?= ($editCampaign['target_audience'] ?? '') === 'artist_followers' ? 'selected' : '' ?>>Followers of a Specific Artist (Choose Below)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Campaign Status</label>
                    <select name="status" class="form-input">
                        <option value="draft" <?= ($editCampaign['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= ($editCampaign['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Send Now</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="campaigns.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Campaign' : 'Save Campaign' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Campaigns List -->
        <?php /* TODO: Consider integrating with a third-party email service provider (ESP) API for
                      sending emails and tracking campaign performance (e.g., open rates, click-throughs).
                      Also, integrate campaign analytics here. */ ?>
        <div id="esp-integration-analytics-placeholder" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px dashed var(--accent); text-align: center; color: var(--text-muted);">
            <i class="bi bi-graph-up-arrow" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
            <span>ESP Integration / Campaign Analytics Area</span>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Campaigns (<?= count($campaigns) ?>)</h2>
                <a href="campaigns.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> New Campaign
                </a>
            </div>
            
            <?php if (empty($campaigns)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-envelope" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No email campaigns created yet.</p>
                <a href="campaigns.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Create First Campaign
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($campaigns as $campaign): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($campaign['subject']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            Status: <span style="text-transform: capitalize;"><?= htmlspecialchars($campaign['status']) ?></span>
                            <?php if ($campaign['status'] === 'sent'): ?>
                                · Sent: <?= date('M j, Y', strtotime($campaign['sent_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="campaigns.php?action=edit&id=<?= $campaign['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>
