<?php
namespace NGN\Lib\OAuth;

/**
 * Unified OAuth Service
 * Handles OAuth flows for Facebook, Instagram, Spotify, TikTok
 */
class OAuthService
{
    private const META_API_VERSION = 'v22.0';
    private const META_GRAPH_BASE = 'https://graph.facebook.com';
    private const META_AUTH_BASE = 'https://www.facebook.com';
    
    private array $config;
    
    public function __construct()
    {
        $this->config = [
            'facebook' => [
                'app_id' => $_ENV['FACEBOOK_APP_ID'] ?? '',
                'app_secret' => $_ENV['FACEBOOK_APP_SECRET'] ?? '',
                'redirect_uri' => $_ENV['FB_REDIRECT_URI'] ?? 'meta/fb-callback',
                'scopes' => ['email', 'pages_show_list', 'pages_read_engagement', 'read_insights'],
            ],
            'instagram' => [
                'app_id' => $_ENV['FACEBOOK_APP_ID'] ?? '', // Uses Facebook app
                'app_secret' => $_ENV['FACEBOOK_APP_SECRET'] ?? '',
                'redirect_uri' => $_ENV['IG_REDIRECT_URI'] ?? 'meta/ig-callback',
                'scopes' => ['instagram_basic', 'instagram_manage_insights', 'pages_show_list', 'pages_read_engagement'],
            ],
            'spotify' => [
                'client_id' => $_ENV['SPOTIFY_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['SPOTIFY_CLIENT_SECRET'] ?? '',
                'scopes' => ['user-read-private', 'user-read-email', 'user-top-read', 'user-read-recently-played'],
            ],
            'tiktok' => [
                'client_key' => $_ENV['TIKTOK_CLIENT_KEY'] ?? '',
                'client_secret' => $_ENV['TIKTOK_CLIENT_SECRET'] ?? '',
                'scopes' => ['user.info.basic', 'video.list'],
            ],
        ];
    }
    
    /**
     * Check if a provider is configured
     */
    public function isConfigured(string $provider): bool
    {
        $cfg = $this->config[$provider] ?? [];
        return match ($provider) {
            'facebook', 'instagram' => !empty($cfg['app_id']) && !empty($cfg['app_secret']),
            'spotify' => !empty($cfg['client_id']) && !empty($cfg['client_secret']),
            'tiktok' => !empty($cfg['client_key']) && !empty($cfg['client_secret']),
            default => false,
        };
    }
    
    /**
     * Get configuration status for all providers
     */
    public function getConfigStatus(): array
    {
        return [
            'facebook' => $this->isConfigured('facebook'),
            'instagram' => $this->isConfigured('instagram'),
            'spotify' => $this->isConfigured('spotify'),
            'tiktok' => $this->isConfigured('tiktok'),
        ];
    }
    
    /**
     * Get authorization URL for a provider
     */
    public function getAuthUrl(string $provider, string $redirectUri, string $state): string
    {
        if (!$this->isConfigured($provider)) {
            throw new \RuntimeException("Provider {$provider} is not configured");
        }
        
        return match ($provider) {
            'facebook' => $this->getFacebookAuthUrl($redirectUri, $state),
            'instagram' => $this->getInstagramAuthUrl($redirectUri, $state),
            'spotify' => $this->getSpotifyAuthUrl($redirectUri, $state),
            'tiktok' => $this->getTikTokAuthUrl($redirectUri, $state),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
    
    private function getFacebookAuthUrl(string $redirectUri, string $state): string
    {
        $cfg = $this->config['facebook'];
        return self::META_AUTH_BASE . '/' . self::META_API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id' => $cfg['app_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $cfg['scopes']),
            'state' => $state,
        ]);
    }
    
    private function getInstagramAuthUrl(string $redirectUri, string $state): string
    {
        $cfg = $this->config['instagram'];
        return self::META_AUTH_BASE . '/' . self::META_API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id' => $cfg['app_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $cfg['scopes']),
            'state' => $state,
        ]);
    }
    
    private function getSpotifyAuthUrl(string $redirectUri, string $state): string
    {
        $cfg = $this->config['spotify'];
        return 'https://accounts.spotify.com/authorize?' . http_build_query([
            'client_id' => $cfg['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $cfg['scopes']),
            'state' => $state,
            'show_dialog' => 'true',
        ]);
    }
    
    private function getTikTokAuthUrl(string $redirectUri, string $state): string
    {
        $cfg = $this->config['tiktok'];
        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => $cfg['client_key'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $cfg['scopes']),
            'state' => $state,
        ]);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchangeCode(string $provider, string $code, string $redirectUri): array
    {
        return match ($provider) {
            'facebook' => $this->exchangeFacebookCode($code, $redirectUri),
            'instagram' => $this->exchangeFacebookCode($code, $redirectUri), // Same as Facebook
            'spotify' => $this->exchangeSpotifyCode($code, $redirectUri),
            'tiktok' => $this->exchangeTikTokCode($code, $redirectUri),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
    
    private function exchangeFacebookCode(string $code, string $redirectUri): array
    {
        $cfg = $this->config['facebook'];
        $url = self::META_GRAPH_BASE . '/' . self::META_API_VERSION . '/oauth/access_token?' . http_build_query([
            'client_id' => $cfg['app_id'],
            'client_secret' => $cfg['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to Facebook'];
        }
        
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown error'];
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    private function exchangeSpotifyCode(string $code, string $redirectUri): array
    {
        $cfg = $this->config['spotify'];
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
                'Authorization: Basic ' . base64_encode($cfg['client_id'] . ':' . $cfg['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Failed to exchange code'];
        }
        
        return ['success' => true, 'data' => json_decode($response, true)];
    }
    
    private function exchangeTikTokCode(string $code, string $redirectUri): array
    {
        $cfg = $this->config['tiktok'];
        $ch = curl_init('https://open.tiktokapis.com/v2/oauth/token/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_key' => $cfg['client_key'],
                'client_secret' => $cfg['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Failed to exchange code'];
        }
        
        return ['success' => true, 'data' => json_decode($response, true)];
    }
    
    /**
     * Exchange short-lived token for long-lived token (Facebook/Instagram only)
     */
    public function getLongLivedToken(string $shortToken): array
    {
        $cfg = $this->config['facebook'];
        $url = self::META_GRAPH_BASE . '/' . self::META_API_VERSION . '/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $cfg['app_id'],
            'client_secret' => $cfg['app_secret'],
            'fb_exchange_token' => $shortToken,
        ]);
        
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to Facebook'];
        }
        
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown error'];
        }
        
        return ['success' => true, 'data' => $data];
    }
}

