<?php
/**
 * Instagram OAuth Handler
 * Uses Facebook Login for Instagram Business accounts
 */
require_once dirname(__DIR__) . '/bootstrap.php';

dashboard_require_auth();

$user = dashboard_get_user();
$entityType = dashboard_get_entity_type();
$entity = dashboard_get_entity($entityType);

if (!$entity) {
    header('Location: ../profile.php?error=profile_required');
    exit;
}

$appId = $_ENV['FACEBOOK_APP_ID'] ?? $_ENV['META_APP_ID'] ?? '';
$appSecret = $_ENV['FACEBOOK_APP_SECRET'] ?? $_ENV['META_APP_SECRET'] ?? '';
$redirectUri = ($_ENV['APP_URL'] ?? 'https://nextgennoise.com') . '/dashboard/lib/oauth/instagram.php';

// Handle callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for token
    $tokenUrl = 'https://graph.facebook.com/v22.0/oauth/access_token?' . http_build_query([
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);

    $response = file_get_contents($tokenUrl);
    $data = json_decode($response, true);

    if (!empty($data['access_token'])) {
        $accessToken = $data['access_token'];

        // Get pages with Instagram business accounts
        $pagesUrl = 'https://graph.facebook.com/v22.0/me/accounts?fields=id,name,instagram_business_account&access_token=' . $accessToken;
        $pages = json_decode(file_get_contents($pagesUrl), true);

        $igAccount = null;
        foreach (($pages['data'] ?? []) as $page) {
            if (!empty($page['instagram_business_account'])) {
                $igAccount = $page['instagram_business_account'];
                break;
            }
        }

        if ($igAccount) {
            // Get Instagram profile
            $igUrl = 'https://graph.facebook.com/v22.0/' . $igAccount['id'] . '?fields=id,username,name,profile_picture_url,followers_count&access_token=' . $accessToken;
            $igProfile = json_decode(file_get_contents($igUrl), true);
            
            // Store token
            try {
                $pdo = dashboard_pdo();
                $stmt = $pdo->prepare("
                    INSERT INTO oauth_tokens (user_id, entity_type, entity_id, provider, provider_user_id, access_token, scope, expires_at, meta)
                    VALUES (?, ?, ?, 'instagram', ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        access_token = VALUES(access_token),
                        scope = VALUES(scope),
                        expires_at = VALUES(expires_at),
                        meta = VALUES(meta),
                        updated_at = NOW()
                ");
                
                $expiresAt = date('Y-m-d H:i:s', time() + ($data['expires_in'] ?? 5184000));
                $meta = json_encode([
                    'username' => $igProfile['username'] ?? null,
                    'name' => $igProfile['name'] ?? null,
                    'followers_count' => $igProfile['followers_count'] ?? null,
                    'profile_picture_url' => $igProfile['profile_picture_url'] ?? null,
                ]);
                
                $stmt->execute([
                    $user['Id'],
                    $entityType,
                    $entity['id'],
                    $igAccount['id'],
                    $accessToken,
                    'instagram_basic,instagram_manage_insights',
                    $expiresAt,
                    $meta,
                ]);
                
                header('Location: ../' . $entityType . '/connections.php?success=instagram');
                exit;
            } catch (PDOException $e) {
                header('Location: ../' . $entityType . '/connections.php?error=db');
                exit;
            }
        } else {
            header('Location: ../' . $entityType . '/connections.php?error=no_ig_account');
            exit;
        }
    } else {
        header('Location: ../' . $entityType . '/connections.php?error=token');
        exit;
    }
}

// Handle error
if (isset($_GET['error'])) {
    header('Location: ../' . $entityType . '/connections.php?error=' . urlencode($_GET['error_description'] ?? $_GET['error']));
    exit;
}

// Initiate OAuth flow
$scopes = 'instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement';
$state = bin2hex(random_bytes(16));
$_SESSION['ig_oauth_state'] = $state;

$authUrl = 'https://www.facebook.com/v22.0/dialog/oauth?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'scope' => $scopes,
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;

