<?php
/**
 * TikTok OAuth Handler
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

$clientKey = $_ENV['TIKTOK_CLIENT_KEY'] ?? '';
$clientSecret = $_ENV['TIKTOK_CLIENT_SECRET'] ?? '';
// Ensure this redirect URI is configured in your TikTok Developer app settings
$redirectUri = ($_ENV['APP_URL'] ?? 'https://nextgennoise.com') . '/dashboard/lib/oauth/tiktok.php';

// Handle callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';

    // Validate state
    if (empty($state) || ($state !== ($_SESSION['tiktok_oauth_state'] ?? ''))) {
        unset($_SESSION['tiktok_oauth_state']);
        header('Location: ../' . $entityType . '/connections.php?error=invalid_state');
        exit;
    }
    unset($_SESSION['tiktok_oauth_state']);

    // Exchange code for access token
    $tokenUrl = 'https://open.tiktokapis.com/v2/oauth/token/';
    $tokenParams = [
        'client_key' => $clientKey,
        'client_secret' => $clientSecret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUri,
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tokenParams));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && !empty($data['access_token'])) {
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600; // Default to 1 hour if not provided
        $refreshToken = $data['refresh_token'] ?? null;
        $refreshExpiresIn = $data['refresh_expires_in'] ?? null;

        // Fetch user profile (optional, but good for verification/display)
        $profileUrl = 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url';
        $ch = curl_init($profileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $profileResponse = curl_exec($ch);
        curl_close($ch);
        $profile = json_decode($profileResponse, true)['data']['user'] ?? [];

        // Store token
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("
                INSERT INTO oauth_tokens (user_id, entity_type, entity_id, provider, provider_user_id, access_token, refresh_token, scope, expires_at, meta)
                VALUES (?, ?, ?, 'tiktok', ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    scope = VALUES(scope),
                    expires_at = VALUES(expires_at),
                    meta = VALUES(meta),
                    updated_at = NOW()
            ");
            
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            $meta = json_encode([
                'display_name' => $profile['display_name'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
            ]);
            
            $stmt->execute([
                $user['Id'],
                $entityType,
                $entity['id'],
                $profile['open_id'] ?? null,
                $accessToken,
                $refreshToken,
                'user.info.basic,video.list', // Example scopes, adjust as needed
                $expiresAt,
                $meta,
            ]);
            
            header('Location: ../' . $entityType . '/connections.php?success=tiktok');
            exit;
        } catch (PDOException $e) {
            error_log("TikTok OAuth DB error: " . $e->getMessage());
            header('Location: ../' . $entityType . '/connections.php?error=db_error');
            exit;
        }
    } else {
        error_log("TikTok OAuth token exchange failed: " . ($data['error_description'] ?? $response));
        header('Location: ../' . $entityType . '/connections.php?error=' . urlencode($data['error_description'] ?? 'token_exchange_failed'));
        exit;
    }
}

// Handle error passed from TikTok
if (isset($_GET['error'])) {
    header('Location: ../' . $entityType . '/connections.php?error=' . urlencode($_GET['error_description'] ?? $_GET['error']));
    exit;
}

// Initiate OAuth flow
$scopes = 'user.info.basic,video.list'; // Basic scope, adjust as needed for desired data
$state = bin2hex(random_bytes(16));
$_SESSION['tiktok_oauth_state'] = $state;

$authUrl = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
    'client_key' => $clientKey,
    'redirect_uri' => $redirectUri,
    'scope' => $scopes,
    'response_type' => 'code',
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;
