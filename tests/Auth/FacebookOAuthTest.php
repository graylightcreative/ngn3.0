<?php

namespace Tests\Auth;

use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// Dummy class to simulate Facebook OAuth client behavior for testing
class DummyFacebookOAuthClient
{
    private $appId;
    private $appSecret;
    private $redirectUri;
    private $httpFetcher;
    private $tokenStorage;

    public function __construct($appId, $appSecret, $redirectUri, $httpFetcher, $tokenStorage)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
        $this->httpFetcher = $httpFetcher; // Mocked dependency for HTTP requests
        $this->tokenStorage = $tokenStorage; // Mocked dependency for token storage
    }

    public function getAuthorizationUrl(array $scopes, $state)
    {
        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(',', $scopes),
            'state' => $state,
        ]);
    }

    public function handleCallback(string $code)
    {
        // Exchange code for short-lived token
        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        $response = $this->httpFetcher->fetch($tokenUrl);
        $data = json_decode($response, true);

        if (empty($data['access_token'])) {
            throw new \Exception('Failed to get short-lived token.');
        }

        // Exchange for long-lived token
        $longTokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $data['access_token'],
        ]);

        $longResponse = $this->httpFetcher->fetch($longTokenUrl);
        $longData = json_decode($longResponse, true);
        $accessToken = $longData['access_token'] ?? $data['access_token'];
        $expiresIn = $longData['expires_in'] ?? $data['expires_in'] ?? 5184000; // 60 days default

        // Get user profile
        $profileUrl = 'https://graph.facebook.com/v19.0/me?fields=id,name,email&access_token=' . $accessToken;
        $profile = json_decode($this->httpFetcher->fetch($profileUrl), true);

        // Get pages (for business accounts)
        $pagesUrl = 'https://graph.facebook.com/v19.0/me/accounts?access_token=' . $accessToken;
        $pages = json_decode($this->httpFetcher->fetch($pagesUrl), true);
        
        // Store token
        $this->tokenStorage->storeToken(
            1, // user_id (mocked)
            'artist', // entity_type (mocked)
            101, // entity_id (mocked)
            'facebook',
            $profile['id'] ?? null,
            $accessToken,
            'email,pages_show_list,pages_read_engagement,read_insights',
            $expiresIn,
            [
                'name' => $profile['name'] ?? null,
                'email' => $profile['email'] ?? null,
                'pages' => $pages['data'] ?? [],
            ]
        );

        return true;
    }
}

// Dummy interface for HTTP fetching for mock injection
interface HttpFetcher {
    public function fetch(string $url): string;
}

// Dummy interface for Token Storage for mock injection
interface TokenStorage {
    public function storeToken(int $userId, string $entityType, int $entityId, string $provider, ?string $providerUserId, string $accessToken, string $scope, int $expiresIn, array $meta): bool;
}

class FacebookOAuthTest extends TestCase
{
    use MockeryPHPUnitIntegration; // Integrates Mockery with PHPUnit for automatic verification/teardown

    private $appId = 'test_fb_app_id';
    private $appSecret = 'test_fb_app_secret';
    private $redirectUri = 'http://localhost/dashboard/lib/oauth/facebook.php';

    protected function setUp(): void
    {
        parent::setUp();
        // Clear any previous Mockery expectations
        \Mockery::close();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close(); // Close Mockery expectations after each test
    }

    public function testGetAuthorizationUrl()
    {
        $mockHttpFetcher = \Mockery::mock(HttpFetcher::class);
        $mockTokenStorage = \Mockery::mock(TokenStorage::class);

        $client = new DummyFacebookOAuthClient(
            $this->appId,
            $this->appSecret,
            $this->redirectUri,
            $mockHttpFetcher,
            $mockTokenStorage
        );

        $scopes = ['email', 'pages_show_list'];
        $state = 'random_state_string';
        $expectedUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'email,pages_show_list',
            'state' => $state,
        ]);

        $this->assertEquals($expectedUrl, $client->getAuthorizationUrl($scopes, $state));
    }

    public function testHandleCallbackSuccessful()
    {
        $mockHttpFetcher = \Mockery::mock(HttpFetcher::class);
        $mockTokenStorage = \Mockery::mock(TokenStorage::class);

        $shortLivedTokenResponse = json_encode(['access_token' => 'mock_short_lived_token', 'expires_in' => 3600]);
        $longLivedTokenResponse = json_encode(['access_token' => 'mock_long_lived_token', 'expires_in' => 5184000]);
        $profileResponse = json_encode(['id' => 'fb_user_123', 'name' => 'Test User', 'email' => 'test@example.com']);
        $pagesResponse = json_encode(['data' => [['id' => 'fb_page_456', 'name' => 'Test Page']]]);

        // Define expected URLs
        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => 'mock_auth_code',
        ]);
        $longTokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => 'mock_short_lived_token',
        ]);
        $profileUrl = 'https://graph.facebook.com/v19.0/me?fields=id,name,email&access_token=mock_long_lived_token';
        $pagesUrl = 'https://graph.facebook.com/v19.0/me/accounts?access_token=mock_long_lived_token';

        // Set up expectations for HttpFetcher
        $mockHttpFetcher->shouldReceive('fetch')->once()->with($tokenUrl)->andReturn($shortLivedTokenResponse);
        $mockHttpFetcher->shouldReceive('fetch')->once()->with($longTokenUrl)->andReturn($longLivedTokenResponse);
        $mockHttpFetcher->shouldReceive('fetch')->once()->with($profileUrl)->andReturn($profileResponse);
        $mockHttpFetcher->shouldReceive('fetch')->once()->with($pagesUrl)->andReturn($pagesResponse);

        // Set up expectations for TokenStorage
        $mockTokenStorage->shouldReceive('storeToken')->once()->with(
            1, // user_id
            'artist', // entity_type
            101, // entity_id
            'facebook',
            'fb_user_123',
            'mock_long_lived_token',
            'email,pages_show_list,pages_read_engagement,read_insights',
            5184000, // expiresIn
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'pages' => [['id' => 'fb_page_456', 'name' => 'Test Page']],
            ]
        )->andReturn(true);

        $client = new DummyFacebookOAuthClient(
            $this->appId,
            $this->appSecret,
            $this->redirectUri,
            $mockHttpFetcher,
            $mockTokenStorage
        );

        $this->assertTrue($client->handleCallback('mock_auth_code'));
    }

    public function testHandleCallbackFailsOnShortLivedTokenFetch()
    {
        $mockHttpFetcher = \Mockery::mock(HttpFetcher::class);
        $mockTokenStorage = \Mockery::mock(TokenStorage::class);

        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => 'mock_auth_code',
        ]);

        // HttpFetcher returns empty or invalid response for short-lived token
        $mockHttpFetcher->shouldReceive('fetch')->once()->with($tokenUrl)->andReturn(json_encode(['error' => 'invalid_code']));

        $client = new DummyFacebookOAuthClient(
            $this->appId,
            $this->appSecret,
            $this->redirectUri,
            $mockHttpFetcher,
            $mockTokenStorage
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to get short-lived token.');

        $client->handleCallback('mock_auth_code');
    }
}