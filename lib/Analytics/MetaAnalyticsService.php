<?php
namespace NGN\Lib\Analytics;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * MetaAnalyticsService - Integrates with Meta Graph API for Facebook/Instagram analytics
 * 
 * Features:
 * - OAuth 2.0 authorization flow for Facebook Pages
 * - Fetch page insights (reach, engagement, impressions)
 * - Fetch Instagram business account insights
 * - Store analytics snapshots for trending/history
 * 
 * Required env vars:
 * - FACEBOOK_APP_ID
 * - FACEBOOK_APP_SECRET
 * - FACEBOOK_REDIRECT_URI (optional, defaults to /admin/analytics-meta-callback.php)
 * 
 * @see https://developers.facebook.com/docs/graph-api
 */
class MetaAnalyticsService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    
    private const GRAPH_API_VERSION = 'v22.0';
    private const GRAPH_API_BASE = 'https://graph.facebook.com';
    
    // Required scopes for page insights
    private const SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'read_insights',
        'instagram_basic',
        'instagram_manage_insights',
    ];
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }
    
    /**
     * Get Meta credentials from environment
     */
    private function getCredentials(): array
    {
        return [
            'app_id' => (string)(\NGN\Lib\Env::get('FACEBOOK_APP_ID', '') ?: ''),
            'app_secret' => (string)(\NGN\Lib\Env::get('FACEBOOK_APP_SECRET', '') ?: ''),
            'redirect_uri' => (string)(\NGN\Lib\Env::get('FACEBOOK_REDIRECT_URI', '/admin/analytics-meta-callback.php') ?: '/admin/analytics-meta-callback.php'),
        ];
    }
    
    /**
     * Check if Meta API is configured
     */
    public function isConfigured(): bool
    {
        $creds = $this->getCredentials();
        return $creds['app_id'] !== '' && $creds['app_secret'] !== '';
    }
    
    /**
     * Generate authorization URL for OAuth flow
     */
    public function getAuthorizationUrl(string $state): string
    {
        $creds = $this->getCredentials();
        $baseUrl = rtrim(getenv('APP_URL') ?: 'https://nextgennoise.com', '/');
        $redirectUri = $baseUrl . $creds['redirect_uri'];

        $params = [
            'client_id' => $creds['app_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', self::SCOPES),
            'state' => $state,
            'response_type' => 'code',
        ];

        // OAuth dialog is on facebook.com, not graph.facebook.com
        return 'https://www.facebook.com/' . self::GRAPH_API_VERSION . '/dialog/oauth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): array
    {
        $creds = $this->getCredentials();
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
        $redirectUri = $baseUrl . $creds['redirect_uri'];
        
        $url = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/oauth/access_token';
        $params = [
            'client_id' => $creds['app_id'],
            'client_secret' => $creds['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ];
        
        $response = $this->httpGet($url . '?' . http_build_query($params));
        
        if (!$response['success']) {
            return $response;
        }
        
        return [
            'success' => true,
            'access_token' => $response['data']['access_token'] ?? null,
            'token_type' => $response['data']['token_type'] ?? 'bearer',
            'expires_in' => $response['data']['expires_in'] ?? null,
        ];
    }
    
    /**
     * Exchange short-lived token for long-lived token
     */
    public function getLongLivedToken(string $shortToken): array
    {
        $creds = $this->getCredentials();
        
        $url = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/oauth/access_token';
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $creds['app_id'],
            'client_secret' => $creds['app_secret'],
            'fb_exchange_token' => $shortToken,
        ];
        
        return $this->httpGet($url . '?' . http_build_query($params));
    }
    
    /**
     * Get user's Facebook pages
     */
    public function getUserPages(string $accessToken): array
    {
        $url = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/me/accounts';
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,instagram_business_account',
        ];
        
        $response = $this->httpGet($url . '?' . http_build_query($params));
        
        if (!$response['success']) {
            return $response;
        }
        
        return [
            'success' => true,
            'pages' => $response['data']['data'] ?? [],
        ];
    }
    
    /**
     * Get page insights
     */
    public function getPageInsights(string $pageId, string $pageAccessToken, string $period = 'day', int $days = 7): array
    {
        // Get basic page info first (followers count)
        $infoUrl = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/' . $pageId;
        $infoParams = [
            'access_token' => $pageAccessToken,
            'fields' => 'id,name,followers_count,fan_count',
        ];
        $info = $this->httpGet($infoUrl . '?' . http_build_query($infoParams));

        // Valid FB Page metrics as of 2024+ (v22.0)
        // Include metrics relevant to scoring factors: engagements and video views.
        $metrics = [
            'page_impressions',                 // General reach
            'page_engaged_users',               // Users who engaged with content
            'page_post_engagements',            // Engagements on posts
            'page_video_views_paid',            // Paid video views
            'page_video_views_organic',         // Organic video views
            'post_video_complete_views_30s_organic', // Organic video views (at least 30s)
            'post_video_complete_views_30s_paid',    // Paid video views (at least 30s)
            'page_video_views_10s_unique',      // Unique viewers (at least 10s)
            'post_video_views_unique',          // Unique video views
            'post_video_views_10s_unique',      // Unique video viewers (at least 10s)
        ];

        $since = date('Y-m-d', strtotime("-{$days} days"));
        $until = date('Y-m-d');

        $url = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/' . $pageId . '/insights';
        $params = [
            'access_token' => $pageAccessToken,
            'metric' => implode(',', $metrics),
            'period' => $period,
            'since' => $since,
            'until' => $until,
        ];

        $insights = $this->httpGet($url . '?' . http_build_query($params));

        return [
            'success' => $info['success'] || $insights['success'],
            'data' => [
                'page_info' => $info['data'] ?? null,
                'insights' => $insights['data']['data'] ?? [],
            ],
            'error' => $insights['error'] ?? $info['error'] ?? null,
        ];
    }
    
    /**
     * Get Instagram business account insights
     */
    public function getInstagramInsights(string $igAccountId, string $accessToken, int $days = 7): array
    {
        // Time-series metrics (period=day)
        $timeMetrics = ['reach', 'follower_count'];

        // Total value metrics need metric_type=total_value
        $totalMetrics = ['profile_views', 'accounts_engaged', 'total_interactions'];

        $since = strtotime("-{$days} days");
        $until = time();

        $results = ['success' => true, 'data' => []];

        // Fetch time-series metrics
        $url = self::GRAPH_API_BASE . '/' . self::GRAPH_API_VERSION . '/' . $igAccountId . '/insights';
        $params = [
            'access_token' => $accessToken,
            'metric' => implode(',', $timeMetrics),
            'period' => 'day',
            'since' => $since,
            'until' => $until,
        ];
        $r1 = $this->httpGet($url . '?' . http_build_query($params));
        if ($r1['success']) {
            $results['data']['time_series'] = $r1['data']['data'] ?? [];
        } else {
            $results['error_time'] = $r1['error'] ?? 'Unknown';
        }

        // Fetch total value metrics
        $params2 = [
            'access_token' => $accessToken,
            'metric' => implode(',', $totalMetrics),
            'period' => 'day',
            'metric_type' => 'total_value',
            'since' => $since,
            'until' => $until,
        ];
        $r2 = $this->httpGet($url . '?' . http_build_query($params2));
        if ($r2['success']) {
            $results['data']['totals'] = $r2['data']['data'] ?? [];
        } else {
            $results['error_totals'] = $r2['error'] ?? 'Unknown';
        }

        return $results;
    }
    
    /**
     * Store OAuth tokens for an entity
     */
    public function storeTokens(int $userId, string $entityType, int $entityId, string $accessToken, ?string $pageId = null, ?string $igAccountId = null, int $expiresIn = 5184000): array
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            $sql = "INSERT INTO `ngn_2025`.`oauth_tokens` 
                    (user_id, entity_type, entity_id, provider, access_token, provider_page_id, provider_user_id, expires_at, created_at)
                    VALUES (:userId, :entityType, :entityId, 'facebook', :token, :pageId, :igId, :expires, NOW())
                    ON DUPLICATE KEY UPDATE 
                        access_token = VALUES(access_token),
                        provider_page_id = VALUES(provider_page_id),
                        provider_user_id = VALUES(provider_user_id),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()";
            
            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':entityType' => $entityType,
                ':entityId' => $entityId,
                ':token' => $accessToken,
                ':pageId' => $pageId,
                ':igId' => $igAccountId,
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
                    WHERE entity_type = :type AND entity_id = :id AND provider = 'facebook'";
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
    public function storeSnapshot(string $entityType, int $entityId, string $provider, array $data): array
    {
        try {
            $sql = "INSERT INTO `ngn_2025`.`analytics_snapshots` 
                    (entity_type, entity_id, provider, followers, engagement_rate, posts_count, data, snapshot_date, created_at)
                    VALUES (:type, :id, :provider, :followers, :engagement, :posts, :data, CURDATE(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        followers = VALUES(followers),
                        engagement_rate = VALUES(engagement_rate),
                        posts_count = VALUES(posts_count),
                        data = VALUES(data)";
            
            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':type' => $entityType,
                ':id' => $entityId,
                ':provider' => $provider,
                ':followers' => $data['followers'] ?? null,
                ':engagement' => $data['engagement_rate'] ?? null,
                ':posts' => $data['posts_count'] ?? null,
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
    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
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
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Request failed', 'code' => $httpCode];
        }
        
        return ['success' => true, 'data' => $data];
    }
}

