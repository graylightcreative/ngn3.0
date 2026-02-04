<?php
/**
 * Artist Dashboard - Profile Editor
 * (Bible Ch. 7 - A.1 Dashboard: Public artist profile and metadata)
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Profile';
$currentPage = 'profile';

$success = $error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $facebook = trim($_POST['facebook_url'] ?? '');
        $instagram = trim($_POST['instagram_url'] ?? '');
        $tiktok = trim($_POST['tiktok_url'] ?? '');
        $youtube = trim($_POST['youtube_url'] ?? '');
        $spotify = trim($_POST['spotify_url'] ?? '');
        
        if (empty($name)) {
            $error = 'Name is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                
                if ($entity) {
                    // Update existing entity
                    $stmt = $pdo->prepare("
                        UPDATE artists SET 
                            name = ?, bio = ?, email = ?, phone = ?, website = ?,
                            city = ?, region = ?, country = ?,
                            facebook_url = ?, instagram_url = ?, tiktok_url = ?, 
                            youtube_url = ?, spotify_url = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $bio, $email, $phone, $website,
                        $city, $region, $country,
                        $facebook, $instagram, $tiktok, $youtube, $spotify,
                        $entity['id']
                    ]);
                } else {
                    // Create new entity linked to user
                    $stmt = $pdo->prepare("
                        INSERT INTO artists (user_id, slug, name, bio, email, phone, website,
                            city, region, country,
                            facebook_url, instagram_url, tiktok_url, youtube_url, spotify_url, claimed)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $user['Id'], $user['Slug'], $name, $bio, $email, $phone, $website,
                        $city, $region, $country,
                        $facebook, $instagram, $tiktok, $youtube, $spotify
                    ]);
                }
                
                $success = 'Profile updated successfully!';
                $entity = dashboard_get_entity('artist'); // Refresh
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
        <p class="page-subtitle">Manage your artist profile and social links</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            
            <!-- Basic Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Basic Information</h2>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Artist/Band Name *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($entity['name'] ?? $user['Title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input"
                               value="<?= htmlspecialchars($entity['email'] ?? $user['Email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-textarea" placeholder="Tell fans about yourself..."><?= htmlspecialchars($entity['bio'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-3">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input" value="<?= htmlspecialchars($entity['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State/Region</label>
                        <input type="text" name="region" class="form-input" value="<?= htmlspecialchars($entity['region'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input" value="<?= htmlspecialchars($entity['country'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input"
                               value="<?= htmlspecialchars($entity['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-input" placeholder="https://"
                               value="<?= htmlspecialchars($entity['website'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Social Links -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Social Links</h2>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 14px;">
                    Add your social media profiles. These will be displayed on your public profile and used for analytics.
                </p>
                
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
                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-tiktok"></i> TikTok</label>
                        <input type="url" name="tiktok_url" class="form-input" placeholder="https://tiktok.com/@..."
                               value="<?= htmlspecialchars($entity['tiktok_url'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-youtube" style="color: #ff0000;"></i> YouTube</label>
                        <input type="url" name="youtube_url" class="form-input" placeholder="https://youtube.com/..."
                               value="<?= htmlspecialchars($entity['youtube_url'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-spotify" style="color: #1DB954;"></i> Spotify</label>
                        <input type="url" name="spotify_url" class="form-input" placeholder="https://open.spotify.com/artist/..."
                               value="<?= htmlspecialchars($entity['spotify_url'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Profile Image -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Profile Image</h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 24px;">
                    <div style="width: 120px; height: 120px; border-radius: 12px; background: var(--bg-primary); border: 2px dashed var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if (!empty($entity['image_url'])): ?>
                        <img src="<?= htmlspecialchars($entity['image_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php elseif (!empty($user['Image'])): ?>
                        <img src="<?= $baseurl ?>lib/images/users/<?= htmlspecialchars($user['Slug']) ?>/<?= htmlspecialchars($user['Image']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-person" style="font-size: 48px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="file" name="image" id="image-upload" accept="image/*" style="display: none;">
                        <label for="image-upload" class="btn btn-secondary" style="cursor: pointer;">
                            <i class="bi bi-upload"></i> Upload New Image
                        </label>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                            Recommended: 500x500px, JPG or PNG, max 2MB
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Submit -->
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

