<?php
namespace NGN\Lib\Analytics;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * SpotifyAnalyticsService - Integrates with Spotify Web API for artist analytics
 * 
 * Features:
 * - OAuth 2.0 authorization flow
 * - Fetch artist profiles (popularity, followers, genres)
 * - Fetch top tracks and albums
 * - Store analytics snapshots for trending/history
 * - Refresh tokens automatically
 * 
 * Required env vars:
 * - SPOTIFY_CLIENT_ID
 * - SPOTIFY_CLIENT_SECRET
 * - SPOTIFY_REDIRECT_URI
 * 
 * @see https://developer.spotify.com/documentation/web-api
 */
class SpotifyAnalyticsService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    
    private const API_BASE = 'https://api.spotify.com/v1';
    private const AUTH_URL = 'https://accounts.spotify.com/authorize';
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';
    
    // Required scopes for analytics
    private const SCOPES = [
        'user-read-private',
        'user-read-email',
        'user-top-read',
        'user-read-recently-played',
    ];
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }
    
    /**
     * Get Spotify credentials from environment
     * @return array{client_id: string, client_secret: string, redirect_uri: string}
     */
    private function getCredentials(): array
    {
        return [
            'client_id' => $this->config->get('SPOTIFY_CLIENT_ID', ''),
            'client_secret' => $this->config->get('SPOTIFY_CLIENT_SECRET', ''),
            'redirect_uri' => $this->config->get('SPOTIFY_REDIRECT_URI', ''),
        ];
    }
    
    /**
     * Check if Spotify API is configured
     */
    public function isConfigured(): bool
    {
        $creds = $this->getCredentials();
        return $creds['client_id'] !== '' && $creds['client_secret'] !== '';
    }
    
    // ═══════════════════════════════════════════════════════════════
    // OAUTH 2.0 AUTHORIZATION
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Generate authorization URL for OAuth flow
     * 
     * @param string $state Random state parameter for CSRF protection
     * @param array<string> $scopes Additional scopes beyond defaults
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state, array $scopes = []): string
    {
        $creds = $this->getCredentials();
        $allScopes = array_unique(array_merge(self::SCOPES, $scopes));
        
        $params = [
            'client_id' => $creds['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $creds['redirect_uri'],
            'scope' => implode(' ', $allScopes),
            'state' => $state,
            'show_dialog' => 'true',
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code from callback
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, error?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $creds = $this->getCredentials();
        
        $response = $this->makeTokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $creds['redirect_uri'],
        ]);
        
        return $response;
    }
    
    /**
     * Refresh an expired access token
     * 
     * @param string $refreshToken The refresh token
     * @return array{success: bool, access_token?: string, expires_in?: int, error?: string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->makeTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }
    
    /**
     * Make token request to Spotify
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function makeTokenRequest(array $params): array
    {
        $creds = $this->getCredentials();
        
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($creds['client_id'] . ':' . $creds['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => $data['error_description'] ?? $data['error'] ?? 'Token request failed'];
        }
        
        return [
            'success' => true,
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? '',
        ];
    }
    
    /**
     * Store OAuth tokens for a user/entity
     * 
     * @param int $userId NGN user ID
     * @param string $accessToken Spotify access token
     * @param string|null $refreshToken Spotify refresh token
     * @param int $expiresIn Token TTL in seconds
     * @return array{success: bool, error?: string}
     */
    public function storeTokens(int $userId, string $accessToken, ?string $refreshToken, int $expiresIn): array
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            $sql = "INSERT INTO `ngn_2025`.`oauth_tokens` 
                    (user_id, provider, access_token, refresh_token, expires_at, created_at, updated_at)
                    VALUES (:userId, 'spotify', :accessToken, :refreshToken, :expiresAt, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        access_token = :accessToken2,
                        refresh_token = COALESCE(:refreshToken2, refresh_token),
                        expires_at = :expiresAt2,
                        updated_at = NOW()";
            
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':accessToken', $accessToken);
            $stmt->bindValue(':refreshToken', $refreshToken);
            $stmt->bindValue(':expiresAt', $expiresAt);
            $stmt->bindValue(':accessToken2', $accessToken);
            $stmt->bindValue(':refreshToken2', $refreshToken);
            $stmt->bindValue(':expiresAt2', $expiresAt);
            $stmt->execute();
            
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get stored tokens for a user
     * @return array<string,mixed>|null
     */
    public function getStoredTokens(int $userId): ?array
    {
        try {
            $sql = "SELECT access_token, refresh_token, expires_at 
                    FROM `ngn_2025`.`oauth_tokens` 
                    WHERE user_id = :userId AND provider = 'spotify'";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) return null;
            
            // Check if token is expired
            $expiresAt = strtotime($row['expires_at']);
            $isExpired = $expiresAt <= time();
            
            return [
                'access_token' => $row['access_token'],
                'refresh_token' => $row['refresh_token'],
                'expires_at' => $row['expires_at'],
                'is_expired' => $isExpired,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Get valid access token for user, refreshing if needed
     * @return array{success: bool, access_token?: string, error?: string}
     */
    public function getValidToken(int $userId): array
    {
        $tokens = $this->getStoredTokens($userId);
        
        if (!$tokens) {
            return ['success' => false, 'error' => 'No Spotify connection found'];
        }
        
        if (!$tokens['is_expired']) {
            return ['success' => true, 'access_token' => $tokens['access_token']];
        }
        
        // Token expired - refresh it
        if (!$tokens['refresh_token']) {
            return ['success' => false, 'error' => 'Token expired and no refresh token available'];
        }
        
        $refreshed = $this->refreshAccessToken($tokens['refresh_token']);
        
        if (!$refreshed['success']) {
            return $refreshed;
        }
        
        // Store new tokens
        $this->storeTokens(
            $userId,
            $refreshed['access_token'],
            $refreshed['refresh_token'] ?? null,
            $refreshed['expires_in'] ?? 3600
        );
        
        return ['success' => true, 'access_token' => $refreshed['access_token']];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // SPOTIFY API REQUESTS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Make authenticated request to Spotify API
     * @param string $accessToken Valid access token
     * @param string $endpoint API endpoint (e.g., /me, /artists/{id})
     * @param string $method HTTP method
     * @param array<string,mixed>|null $data Request body for POST/PUT
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    private function apiRequest(string $accessToken, string $endpoint, string $method = 'GET', ?array $data = null): array
    {
        $url = self::API_BASE . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'cURL error: ' . $error, 'status' => 0];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded, 'status' => $httpCode];
        }
        
        $errorMsg = $decoded['error']['message'] ?? 'API request failed';
        return ['success' => false, 'error' => $errorMsg, 'status' => $httpCode];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // ARTIST ANALYTICS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Get Spotify artist by ID (public endpoint - uses client credentials)
     * 
     * @param string $spotifyArtistId Spotify artist ID
     * @return array{success: bool, artist?: array<string,mixed>, error?: string}
     */
    public function getArtist(string $spotifyArtistId): array
    {
        $token = $this->getClientCredentialsToken();
        if (!$token['success']) {
            return $token;
        }
        
        $result = $this->apiRequest($token['access_token'], '/artists/' . urlencode($spotifyArtistId));
        
        if (!$result['success']) {
            return $result;
        }
        
        $artist = $result['data'];
        
        return [
            'success' => true,
            'artist' => [
                'spotify_id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? null,
                'popularity' => $artist['popularity'] ?? 0,
                'followers' => $artist['followers']['total'] ?? 0,
                'genres' => $artist['genres'] ?? [],
                'images' => $artist['images'] ?? [],
                'external_url' => $artist['external_urls']['spotify'] ?? null,
            ],
        ];
    }
    
    /**
     * Get multiple artists by IDs
     * 
     * @param array<string> $spotifyArtistIds Array of Spotify artist IDs (max 50)
     * @return array{success: bool, artists?: array<int,array<string,mixed>>, error?: string}
     */
    public function getArtists(array $spotifyArtistIds): array
    {
        if (empty($spotifyArtistIds)) {
            return ['success' => true, 'artists' => []];
        }
        
        // Spotify allows max 50 IDs per request
        $spotifyArtistIds = array_slice($spotifyArtistIds, 0, 50);
        
        $token = $this->getClientCredentialsToken();
        if (!$token['success']) {
            return $token;
        }
        
        $result = $this->apiRequest(
            $token['access_token'],
            '/artists?ids=' . implode(',', array_map('urlencode', $spotifyArtistIds))
        );
        
        if (!$result['success']) {
            return $result;
        }
        
        $artists = [];
        foreach ($result['data']['artists'] ?? [] as $artist) {
            if (!$artist) continue;
            $artists[] = [
                'spotify_id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? null,
                'popularity' => $artist['popularity'] ?? 0,
                'followers' => $artist['followers']['total'] ?? 0,
                'genres' => $artist['genres'] ?? [],
                'images' => $artist['images'] ?? [],
                'external_url' => $artist['external_urls']['spotify'] ?? null,
            ];
        }
        
        return ['success' => true, 'artists' => $artists];
    }
    
    /**
     * Get artist's top tracks
     * 
     * @param string $spotifyArtistId Spotify artist ID
     * @param string $market ISO 3166-1 alpha-2 country code (default US)
     * @return array{success: bool, tracks?: array<int,array<string,mixed>>, error?: string}
     */
    public function getArtistTopTracks(string $spotifyArtistId, string $market = 'US'): array
    {
        $token = $this->getClientCredentialsToken();
        if (!$token['success']) {
            return $token;
        }
        
        $result = $this->apiRequest(
            $token['access_token'],
            '/artists/' . urlencode($spotifyArtistId) . '/top-tracks?market=' . urlencode($market)
        );
        
        if (!$result['success']) {
            return $result;
        }
        
        $tracks = [];
        foreach ($result['data']['tracks'] ?? [] as $track) {
            $tracks[] = [
                'spotify_id' => $track['id'] ?? null,
                'name' => $track['name'] ?? null,
                'popularity' => $track['popularity'] ?? 0,
                'duration_ms' => $track['duration_ms'] ?? 0,
                'preview_url' => $track['preview_url'] ?? null,
                'album_name' => $track['album']['name'] ?? null,
                'album_image' => $track['album']['images'][0]['url'] ?? null,
                'external_url' => $track['external_urls']['spotify'] ?? null,
            ];
        }
        
        return ['success' => true, 'tracks' => $tracks];
    }
    
    /**
     * Get artist's albums
     * 
     * @param string $spotifyArtistId Spotify artist ID
     * @param int $limit Max albums to return (default 20, max 50)
     * @return array{success: bool, albums?: array<int,array<string,mixed>>, error?: string}
     */
    public function getArtistAlbums(string $spotifyArtistId, int $limit = 20): array
    {
        $token = $this->getClientCredentialsToken();
        if (!$token['success']) {
            return $token;
        }
        
        $limit = max(1, min(50, $limit));
        
        $result = $this->apiRequest(
            $token['access_token'],
            '/artists/' . urlencode($spotifyArtistId) . '/albums?include_groups=album,single&limit=' . $limit
        );
        
        if (!$result['success']) {
            return $result;
        }
        
        $albums = [];
        foreach ($result['data']['items'] ?? [] as $album) {
            $albums[] = [
                'spotify_id' => $album['id'] ?? null,
                'name' => $album['name'] ?? null,
                'type' => $album['album_type'] ?? null,
                'release_date' => $album['release_date'] ?? null,
                'total_tracks' => $album['total_tracks'] ?? 0,
                'image_url' => $album['images'][0]['url'] ?? null,
                'external_url' => $album['external_urls']['spotify'] ?? null,
            ];
        }
        
        return ['success' => true, 'albums' => $albums];
    }
    
    /**
     * Search for artists by name
     * 
     * @param string $query Artist name to search
     * @param int $limit Max results (default 10, max 50)
     * @return array{success: bool, artists?: array<int,array<string,mixed>>, error?: string}
     */
    public function searchArtists(string $query, int $limit = 10): array
    {
        $token = $this->getClientCredentialsToken();
        if (!$token['success']) {
            return $token;
        }
        
        $limit = max(1, min(50, $limit));
        
        $result = $this->apiRequest(
            $token['access_token'],
            '/search?type=artist&q=' . urlencode($query) . '&limit=' . $limit
        );
        
        if (!$result['success']) {
            return $result;
        }
        
        $artists = [];
        foreach ($result['data']['artists']['items'] ?? [] as $artist) {
            $artists[] = [
                'spotify_id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? null,
                'popularity' => $artist['popularity'] ?? 0,
                'followers' => $artist['followers']['total'] ?? 0,
                'genres' => $artist['genres'] ?? [],
                'images' => $artist['images'] ?? [],
                'external_url' => $artist['external_urls']['spotify'] ?? null,
            ];
        }
        
        return ['success' => true, 'artists' => $artists];
    }
    
    /**
     * Get client credentials token (for public endpoints)
     * @return array{success: bool, access_token?: string, error?: string}
     */
    private function getClientCredentialsToken(): array
    {
        // Check cache first
        static $cachedToken = null;
        static $cachedExpires = 0;
        
        if ($cachedToken && time() < $cachedExpires) {
            return ['success' => true, 'access_token' => $cachedToken];
        }
        
        $result = $this->makeTokenRequest([
            'grant_type' => 'client_credentials',
        ]);
        
        if ($result['success']) {
            $cachedToken = $result['access_token'];
            $cachedExpires = time() + ($result['expires_in'] ?? 3600) - 60; // 1 min buffer
        }
        
        return $result;
    }
    
    // ═══════════════════════════════════════════════════════════════
    // ANALYTICS STORAGE & SNAPSHOTS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Store analytics snapshot for an NGN artist
     * 
     * @param int $artistId NGN artist ID
     * @param string $spotifyId Spotify artist ID
     * @param array<string,mixed> $data Analytics data from Spotify
     * @return array{success: bool, id?: int, error?: string}
     */
    public function storeSnapshot(int $artistId, string $spotifyId, array $data): array
    {
        try {
            $sql = "INSERT INTO `ngn_2025`.`analytics_snapshots` 
                    (entity_type, entity_id, provider, external_id, 
                     popularity, followers, monthly_listeners, genres, data, snapshot_date, created_at)
                    VALUES ('artist', :artistId, 'spotify', :spotifyId,
                            :popularity, :followers, :monthly, :genres, :data, CURDATE(), NOW())";
            
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':artistId', $artistId, PDO::PARAM_INT);
            $stmt->bindValue(':spotifyId', $spotifyId);
            $stmt->bindValue(':popularity', (int)($data['popularity'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':followers', (int)($data['followers'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':monthly', (int)($data['monthly_listeners'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':genres', json_encode($data['genres'] ?? []));
            $stmt->bindValue(':data', json_encode($data));
            $stmt->execute();
            
            return ['success' => true, 'id' => (int)$this->write->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get latest analytics snapshot for an artist
     * 
     * @param int $artistId NGN artist ID
     * @return array<string,mixed>|null
     */
    public function getLatestSnapshot(int $artistId): ?array
    {
        try {
            $sql = "SELECT * FROM `ngn_2025`.`analytics_snapshots` 
                    WHERE entity_type = 'artist' AND entity_id = :artistId AND provider = 'spotify'
                    ORDER BY snapshot_date DESC LIMIT 1";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':artistId', $artistId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) return null;
            
            return [
                'id' => (int)$row['id'],
                'entity_id' => (int)$row['entity_id'],
                'spotify_id' => $row['external_id'],
                'popularity' => (int)$row['popularity'],
                'followers' => (int)$row['followers'],
                'monthly_listeners' => (int)$row['monthly_listeners'],
                'genres' => json_decode($row['genres'], true) ?: [],
                'data' => json_decode($row['data'], true) ?: [],
                'snapshot_date' => $row['snapshot_date'],
                'created_at' => $row['created_at'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Get analytics history for an artist
     * 
     * @param int $artistId NGN artist ID
     * @param int $days Number of days of history (default 30)
     * @return array<int,array<string,mixed>>
     */
    public function getHistory(int $artistId, int $days = 30): array
    {
        try {
            $sql = "SELECT snapshot_date, popularity, followers, monthly_listeners
                    FROM `ngn_2025`.`analytics_snapshots` 
                    WHERE entity_type = 'artist' AND entity_id = :artistId AND provider = 'spotify'
                      AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    ORDER BY snapshot_date ASC";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':artistId', $artistId, PDO::PARAM_INT);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            return array_map(function($row) {
                return [
                    'date' => $row['snapshot_date'],
                    'popularity' => (int)$row['popularity'],
                    'followers' => (int)$row['followers'],
                    'monthly_listeners' => (int)$row['monthly_listeners'],
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    /**
     * Link NGN artist to Spotify artist ID
     * 
     * @param int $artistId NGN artist ID
     * @param string $spotifyId Spotify artist ID
     * @return array{success: bool, error?: string}
     */
    public function linkArtist(int $artistId, string $spotifyId): array
    {
        try {
            $sql = "UPDATE `ngn_2025`.`artists` 
                    SET spotify_id = :spotifyId, updated_at = NOW() 
                    WHERE id = :artistId";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':spotifyId', $spotifyId);
            $stmt->bindValue(':artistId', $artistId, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get Spotify ID for an NGN artist
     * 
     * @param int $artistId NGN artist ID
     * @return string|null Spotify artist ID or null
     */
    public function getLinkedSpotifyId(int $artistId): ?string
    {
        try {
            $sql = "SELECT spotify_id FROM `ngn_2025`.`artists` WHERE id = :artistId";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':artistId', $artistId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row['spotify_id'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Sync analytics for an NGN artist (fetch from Spotify and store snapshot)
     * 
     * @param int $artistId NGN artist ID
     * @return array{success: bool, data?: array<string,mixed>, error?: string}
     */
    public function syncArtistAnalytics(int $artistId): array
    {
        $spotifyId = $this->getLinkedSpotifyId($artistId);
        
        if (!$spotifyId) {
            return ['success' => false, 'error' => 'Artist not linked to Spotify'];
        }
        
        $result = $this->getArtist($spotifyId);
        
        if (!$result['success']) {
            return $result;
        }
        
        $artist = $result['artist'];
        
        // Store snapshot
        $stored = $this->storeSnapshot($artistId, $spotifyId, $artist);
        
        if (!$stored['success']) {
            return $stored;
        }
        
        return [
            'success' => true,
            'data' => $artist,
            'snapshot_id' => $stored['id'],
        ];
    }
    
    /**
     * Batch sync analytics for multiple artists
     * 
     * @param array<int> $artistIds NGN artist IDs
     * @return array{success: int, failed: int, errors: array<string>}
     */
    public function batchSyncAnalytics(array $artistIds): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        // Get Spotify IDs for all artists in a single query
        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
        $sql = "SELECT id, spotify_id FROM `ngn_2025`.`artists` WHERE id IN ({$placeholders}) AND spotify_id IS NOT NULL AND spotify_id != ''";
        $stmt = $this->read->prepare($sql);
        foreach ($artistIds as $key => $artistId) {
            $stmt->bindValue(($key + 1), $artistId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $linkedArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spotifyIds = [];
        $linkedArtistIds = [];
        foreach ($linkedArtists as $artist) {
            $spotifyIds[$artist['spotify_id']] = (int)$artist['id'];
            $linkedArtistIds[] = (int)$artist['id'];
        }

        // Identify artists that were not linked
        $unlinkedArtistIds = array_diff($artistIds, $linkedArtistIds);
        foreach ($unlinkedArtistIds as $artistId) {
            $failed++;
            $errors[] = "Artist $artistId not linked to Spotify";
        }
        
        if (empty($spotifyIds)) {
            return ['success' => 0, 'failed' => $failed, 'errors' => $errors];
        }
        
        // Batch fetch from Spotify (max 50)
        $result = $this->getArtists(array_keys($spotifyIds));
        
        if (!$result['success']) {
            return ['success' => 0, 'failed' => count($artistIds), 'errors' => [$result['error']]];
        }
        
        // Store snapshots
        foreach ($result['artists'] as $artist) {
            $spotifyId = $artist['spotify_id'];
            $artistId = $spotifyIds[$spotifyId] ?? null;
            
            if (!$artistId) continue;
            
            $stored = $this->storeSnapshot($artistId, $spotifyId, $artist);
            
            if ($stored['success']) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Failed to store snapshot for artist $artistId: " . ($stored['error'] ?? 'Unknown error');
            }
        }
        
        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }
    
    /**
     * Get aggregated stats for Spotify-linked artists
     * 
     * @return array<string,mixed>
     */
    public function getAggregatedStats(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT s.entity_id) AS artists_tracked,
                        SUM(s.followers) AS total_followers,
                        AVG(s.popularity) AS avg_popularity,
                        MAX(s.followers) AS max_followers,
                        MAX(s.popularity) AS max_popularity
                    FROM `ngn_2025`.`analytics_snapshots` s
                    INNER JOIN (
                        SELECT entity_id, MAX(snapshot_date) AS latest
                        FROM `ngn_2025`.`analytics_snapshots`
                        WHERE provider = 'spotify' AND entity_type = 'artist'
                        GROUP BY entity_id
                    ) latest ON s.entity_id = latest.entity_id AND s.snapshot_date = latest.latest
                    WHERE s.provider = 'spotify' AND s.entity_type = 'artist'";
            
            $stmt = $this->read->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'artists_tracked' => (int)($row['artists_tracked'] ?? 0),
                'total_followers' => (int)($row['total_followers'] ?? 0),
                'avg_popularity' => round((float)($row['avg_popularity'] ?? 0), 1),
                'max_followers' => (int)($row['max_followers'] ?? 0),
                'max_popularity' => (int)($row['max_popularity'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'artists_tracked' => 0,
                'total_followers' => 0,
                'avg_popularity' => 0,
                'max_followers' => 0,
                'max_popularity' => 0,
            ];
        }
    }

    /**
     * Sync all artists that have a spotify_id linked
     *
     * @return array{synced: int, failed: int, errors: array<string>}
     */
    public function syncAllArtists(): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];

        try {
            // Get all artists with spotify_id
            $sql = "SELECT id, spotify_id FROM `ngn_2025`.`artists` WHERE spotify_id IS NOT NULL AND spotify_id != ''";
            $stmt = $this->read->query($sql);
            $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($artists as $artist) {
                $result = $this->syncArtistAnalytics((int)$artist['id']);
                if ($result['success']) {
                    $synced++;
                } else {
                    $failed++;
                    $errors[] = "Artist {$artist['id']}: " . ($result['error'] ?? 'Unknown error');
                }
                // Rate limiting - Spotify allows 30 requests per second
                usleep(50000); // 50ms delay
            }
        } catch (\Throwable $e) {
            $errors[] = 'Query error: ' . $e->getMessage();
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Get artist profile from Spotify (alias for getArtist for API compatibility)
     *
     * @param string $spotifyArtistId Spotify artist ID
     * @return array<string,mixed>
     */
    public function getArtistProfile(string $spotifyArtistId): array
    {
        $result = $this->getArtist($spotifyArtistId);
        if ($result['success'] && isset($result['artist'])) {
            return $result['artist'];
        }
        throw new \RuntimeException($result['error'] ?? 'Failed to fetch artist');
    }
}
