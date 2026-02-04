<?php
/**
 * YouTube OAuth Handler
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

// TODO: User needs to provide these values in .env
$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$redirectUri = ($_ENV['APP_URL'] ?? 'https://nextgennoise.com') . '/dashboard/lib/oauth/youtube.php';

if (empty($clientId) || empty($clientSecret)) {
    header('Location: ../' . $entityType . '/connections.php?error=google_credentials_missing');
    exit;
}

// Handle callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';

    if (empty($state) || ($state !== ($_SESSION['youtube_oauth_state'] ?? ''))) {
        unset($_SESSION['youtube_oauth_state']);
        header('Location: ../' . $entityType . '/connections.php?error=invalid_state');
        exit;
    }
    unset($_SESSION['youtube_oauth_state']);

    // Exchange code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenParams = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUri,
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!empty($data['access_token'])) {
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3599;
        
        // Store token
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("
                INSERT INTO oauth_tokens (user_id, entity_type, entity_id, provider, access_token, refresh_token, scope, expires_at)
                VALUES (?, ?, ?, 'youtube', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    scope = VALUES(scope),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ");
            
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            $stmt->execute([
                $user['Id'],
                $entityType,
                $entity['id'],
                $accessToken,
                $refreshToken,
                'https://www.googleapis.com/auth/youtube.readonly', // Example scope
                $expiresAt,
            ]);
            
            header('Location: ../' . $entityType . '/connections.php?success=youtube');
            exit;
        } catch (PDOException $e) {
            header('Location: ../' . $entityType . '/connections.php?error=db');
            exit;
        }

    } else {
        header('Location: ../' . $entityType . '/connections.php?error=token_exchange_failed');
        exit;
    }
}

// Handle error
if (isset($_GET['error'])) {
    header('Location: ../' . $entityType . '/connections.php?error=' . urlencode($_GET['error_description'] ?? $_GET['error']));
    exit;
}


// Initiate OAuth flow
$scopes = [
    'https://www.googleapis.com/auth/youtube.readonly',
    'https://www.googleapis.com/auth/yt-analytics.readonly',
];
$state = bin2hex(random_bytes(16));
$_SESSION['youtube_oauth_state'] = $state;

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => implode(' ', $scopes),
    'response_type' => 'code',
    'state' => $state,
    'access_type' => 'offline',
    'prompt' => 'consent',
]);

header('Location: ' . $authUrl);
exit;
