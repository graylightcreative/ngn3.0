<?php
namespace NGN\Lib\Analytics;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * TikTokAnalyticsService - Integrates with TikTok API for creator analytics
 * 
 * Features:
 * - OAuth 2.0 authorization flow
 * - Fetch creator profile (followers, likes, videos)
 * - Fetch video analytics
 * - Store analytics snapshots for trending/history
 * 
 * Required env vars:
 * - TIKTOK_CLIENT_KEY
 * - TIKTOK_CLIENT_SECRET
 * - TIKTOK_REDIRECT_URI
 * 
 * @see https://developers.tiktok.com/doc/login-kit-web
 */
class TikTokAnalyticsService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    
    private const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize/';
    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    private const API_BASE = 'https://open.tiktokapis.com/v2';
    
    // Required scopes
    private const SCOPES = [
        'user.info.basic',
        'user.info.profile',
        'user.info.stats',
        'video.list',
    ];
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }
    
    /**
     * Get TikTok credentials from environment
     */
    private function getCredentials(): array
    {
        return [
            'client_key' => (string)(getenv('TIKTOK_CLIENT_KEY') ?: ''),
            'client_secret' => (string)(getenv('TIKTOK_CLIENT_SECRET') ?: ''),
            'redirect_uri' => (string)(getenv('TIKTOK_REDIRECT_URI') ?: '/admin/analytics-tiktok-callback.php'),
        ];
    }
    
    /**
     * Check if TikTok API is configured
     */
    public function isConfigured(): bool
    {
        $creds = $this->getCredentials();
        return $creds['client_key'] !== '' && $creds['client_secret'] !== '';
    }
    
    /**
     * Generate authorization URL for OAuth flow
     */
    public function getAuthorizationUrl(string $state): string
    {
        $creds = $this->getCredentials();
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
        $redirectUri = $baseUrl . $creds['redirect_uri'];
        
        $params = [
            'client_key' => $creds['client_key'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', self::SCOPES),
            'state' => $state,
            'response_type' => 'code',
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): array
    {
        $creds = $this->getCredentials();
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
        $redirectUri = $baseUrl . $creds['redirect_uri'];
        
        $data = [
            'client_key' => $creds['client_key'],
            'client_secret' => $creds['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ];
        
        return $this->httpPost(self::TOKEN_URL, $data);
    }
    
    /**
     * Refresh access token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $creds = $this->getCredentials();
        
        $data = [
            'client_key' => $creds['client_key'],
            'client_secret' => $creds['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
        
        return $this->httpPost(self::TOKEN_URL, $data);
    }
    
    /**
     * Get user info
     */
    public function getUserInfo(string $accessToken): array
    {
        $url = self::API_BASE . '/user/info/';
        $params = [
            'fields' => 'open_id,union_id,avatar_url,display_name,bio_description,profile_deep_link,is_verified,follower_count,following_count,likes_count,video_count',
        ];
        
        return $this->httpGet($url . '?' . http_build_query($params), $accessToken);
    }
    
    /**
     * Get user videos
     */
    public function getUserVideos(string $accessToken, int $maxCount = 20): array
    {
        $url = self::API_BASE . '/video/list/';
        $params = [
            'fields' => 'id,title,video_description,duration,cover_image_url,share_url,view_count,like_count,comment_count,share_count,create_time',
            'max_count' => min(20, $maxCount),
        ];
        
        return $this->httpPost($url . '?' . http_build_query($params), [], $accessToken);
    }
    
    /**
     * Store OAuth tokens for an entity
     */
    public function storeTokens(int $userId, string $entityType, int $entityId, string $accessToken, ?string $refreshToken, string $openId, int $expiresIn = 86400): array
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            $sql = "INSERT INTO `ngn_2025`.`oauth_tokens` 
                    (user_id, entity_type, entity_id, provider, access_token, refresh_token, provider_user_id, expires_at, created_at)
                    VALUES (:userId, :entityType, :entityId, 'tiktok', :token, :refresh, :openId, :expires, NOW())
                    ON DUPLICATE KEY UPDATE 
                        access_token = VALUES(access_token),
                        refresh_token = VALUES(refresh_token),
                        provider_user_id = VALUES(provider_user_id),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()";
            
            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':entityType' => $entityType,
                ':entityId' => $entityId,
                ':token' => $accessToken,
                ':refresh' => $refreshToken,
                ':openId' => $openId,
                ':expires' => $expiresAt,
            ]);
            
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get stored tokens for an entity
     */
    public function getStoredTokens(string $entityType, int $entityId): ?array
    {
        try {
            $sql = "SELECT * FROM `ngn_2025`.`oauth_tokens` 
                    WHERE entity_type = :type AND entity_id = :id AND provider = 'tiktok'";
            $stmt = $this->read->prepare($sql);
            $stmt->execute([':type' => $entityType, ':id' => $entityId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Store analytics snapshot
     */
    public function storeSnapshot(string $entityType, int $entityId, array $data): array
    {
        try {
            $sql = "INSERT INTO `ngn_2025`.`analytics_snapshots` 
                    (entity_type, entity_id, provider, external_id, followers, videos_count, data, snapshot_date, created_at)
                    VALUES (:type, :id, 'tiktok', :extId, :followers, :videos, :data, CURDATE(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        followers = VALUES(followers),
                        videos_count = VALUES(videos_count),
                        data = VALUES(data)";
            
            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':type' => $entityType,
                ':id' => $entityId,
                ':extId' => $data['open_id'] ?? null,
                ':followers' => $data['follower_count'] ?? null,
                ':videos' => $data['video_count'] ?? null,
                ':data' => json_encode($data),
            ]);
            
            return ['success' => true, 'id' => (int)$this->write->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * HTTP GET request helper
     */
    private function httpGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'Request failed'];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 || isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Request failed', 'code' => $httpCode];
        }
        
        return ['success' => true, 'data' => $data['data'] ?? $data];
    }
    
    /**
     * HTTP POST request helper
     */
    private function httpPost(string $url, array $postData, ?string $accessToken = null): array
    {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'Request failed'];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 || isset($data['error'])) {
            return ['success' => false, 'error' => $data['error_description'] ?? $data['error'] ?? 'Request failed'];
        }
        
        return ['success' => true, 'data' => $data];
    }
}

