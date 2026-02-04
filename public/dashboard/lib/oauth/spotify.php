<?php
/**
 * Spotify OAuth Handler
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

$clientId = $_ENV['SPOTIFY_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['SPOTIFY_CLIENT_SECRET'] ?? '';
$redirectUri = ($_ENV['APP_URL'] ?? 'https://nextgennoise.com') . '/dashboard/lib/oauth/spotify.php';

// Handle callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange code for token
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        // Get user profile
        $ch = curl_init('https://api.spotify.com/v1/me');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $data['access_token']],
        ]);
        $profile = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        // Store token
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("
                INSERT INTO oauth_tokens (user_id, entity_type, entity_id, provider, provider_user_id, access_token, refresh_token, scope, expires_at, meta)
                VALUES (?, ?, ?, 'spotify', ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    scope = VALUES(scope),
                    expires_at = VALUES(expires_at),
                    meta = VALUES(meta),
                    updated_at = NOW()
            ");
            
            $expiresAt = date('Y-m-d H:i:s', time() + ($data['expires_in'] ?? 3600));
            $meta = json_encode(['display_name' => $profile['display_name'] ?? null, 'email' => $profile['email'] ?? null]);
            
            $stmt->execute([
                $user['Id'],
                $entityType,
                $entity['id'],
                $profile['id'] ?? null,
                $data['access_token'],
                $data['refresh_token'] ?? null,
                $data['scope'] ?? null,
                $expiresAt,
                $meta,
            ]);
            
            header('Location: ../' . $entityType . '/connections.php?success=spotify');
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

// Handle error from Spotify
if (isset($_GET['error'])) {
    header('Location: ../' . $entityType . '/connections.php?error=' . urlencode($_GET['error']));
    exit;
}

// Initiate OAuth flow
$scopes = 'user-read-private user-read-email user-top-read user-read-recently-played';
$state = bin2hex(random_bytes(16));
$_SESSION['spotify_oauth_state'] = $state;

$authUrl = 'https://accounts.spotify.com/authorize?' . http_build_query([
    'client_id' => $clientId,
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'scope' => $scopes,
    'state' => $state,
    'show_dialog' => 'true',
]);

header('Location: ' . $authUrl);
exit;

