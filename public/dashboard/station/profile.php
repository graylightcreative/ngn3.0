<?php
/**
 * Station Dashboard - Profile Editor
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Profile';
$currentPage = 'profile';

$success = $error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $facebook = trim($_POST['facebook_url'] ?? '');
        $instagram = trim($_POST['instagram_url'] ?? '');
        
        if (empty($name)) {
            $error = 'Station name is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                
                if ($entity) {
                    $stmt = $pdo->prepare("
                        UPDATE stations SET 
                            name = ?, bio = ?, email = ?, phone = ?, website = ?,
                            city = ?, region = ?, frequency = ?,
                            facebook_url = ?, instagram_url = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $bio, $email, $phone, $website,
                        $city, $region, $frequency,
                        $facebook, $instagram, $entity['id']
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO stations (user_id, slug, name, bio, email, phone, website, city, region, frequency, facebook_url, instagram_url, claimed)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $user['Id'], $user['Slug'], $name, $bio, $email, $phone, $website,
                        $city, $region, $frequency, $facebook, $instagram
                    ]);
                }
                
                $success = 'Profile updated!';
                $entity = dashboard_get_entity('station');
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Edit Profile</h1>
        <p class="page-subtitle">Manage your station profile</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Basic Information</h2>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Station Name *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($entity['name'] ?? $user['Title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <input type="text" name="frequency" class="form-input"
                               value="<?= htmlspecialchars($entity['frequency'] ?? '') ?>"
                               placeholder="e.g., 98.7 FM or Internet">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">About</label>
                    <textarea name="bio" class="form-textarea" placeholder="Tell people about your station..."><?= htmlspecialchars($entity['bio'] ?? '') ?></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input"
                               value="<?= htmlspecialchars($entity['email'] ?? $user['Email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input"
                               value="<?= htmlspecialchars($entity['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Website / Stream URL</label>
                    <input type="url" name="website" class="form-input" placeholder="https://"
                           value="<?= htmlspecialchars($entity['website'] ?? '') ?>">
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Location</h2>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input"
                               value="<?= htmlspecialchars($entity['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State/Region</label>
                        <input type="text" name="region" class="form-input"
                               value="<?= htmlspecialchars($entity['region'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Social Links</h2>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-facebook" style="color: #1877f2;"></i> Facebook</label>
                        <input type="url" name="facebook_url" class="form-input" placeholder="https://facebook.com/..."
                               value="<?= htmlspecialchars($entity['facebook_url'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-instagram" style="color: #e4405f;"></i> Instagram</label>
                        <input type="url" name="instagram_url" class="form-input" placeholder="https://instagram.com/..."
                               value="<?= htmlspecialchars($entity['instagram_url'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>

