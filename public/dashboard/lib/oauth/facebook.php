<?php
/**
 * Facebook OAuth Handler
 * Initiates OAuth flow and handles callback
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
$redirectUri = ($_ENV['APP_URL'] ?? 'https://nextgennoise.com') . '/dashboard/lib/oauth/facebook.php';

// Handle callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for short-lived token
    $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);

    $response = file_get_contents($tokenUrl);
    $data = json_decode($response, true);

    if (!empty($data['access_token'])) {
        // Exchange for long-lived token
        $longTokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $data['access_token'],
        ]);

        $longResponse = file_get_contents($longTokenUrl);
        $longData = json_decode($longResponse, true);
        $accessToken = $longData['access_token'] ?? $data['access_token'];
        $expiresIn = $longData['expires_in'] ?? $data['expires_in'] ?? 5184000; // 60 days default

        // Get user profile
        $profileUrl = 'https://graph.facebook.com/v19.0/me?fields=id,name,email&access_token=' . $accessToken;
        $profile = json_decode(file_get_contents($profileUrl), true);

        // Get pages (for business accounts)
        $pagesUrl = 'https://graph.facebook.com/v19.0/me/accounts?access_token=' . $accessToken;
        $pages = json_decode(file_get_contents($pagesUrl), true);
        
        // Store token
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("
                INSERT INTO oauth_tokens (user_id, entity_type, entity_id, provider, provider_user_id, access_token, scope, expires_at, meta)
                VALUES (?, ?, ?, 'facebook', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    scope = VALUES(scope),
                    expires_at = VALUES(expires_at),
                    meta = VALUES(meta),
                    updated_at = NOW()
            ");
            
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            $meta = json_encode([
                'name' => $profile['name'] ?? null,
                'email' => $profile['email'] ?? null,
                'pages' => $pages['data'] ?? [],
            ]);
            
            $stmt->execute([
                $user['Id'],
                $entityType,
                $entity['id'],
                $profile['id'] ?? null,
                $accessToken,
                'email,pages_show_list,pages_read_engagement,read_insights',
                $expiresAt,
                $meta,
            ]);
            
            // TODO: Consider implementing a more robust background token refresh mechanism
            // and more specific error handling for database operations.
            
            header('Location: ../' . $entityType . '/connections.php?success=facebook');
            exit;
        } catch (PDOException $e) {
            header('Location: ../' . $entityType . '/connections.php?error=db');
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
$scopes = 'email,pages_show_list,pages_read_engagement,read_insights';
$state = bin2hex(random_bytes(16));
$_SESSION['fb_oauth_state'] = $state;

$authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'scope' => $scopes,
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;

