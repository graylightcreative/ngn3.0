<?php

class Instagram
{
    public $token;
    public $userId;
    public $appId;
    public $appSecret;
    public $pageId;
    public $pageName;
    public $shortLivedAccessToken;
    public $longLiveAccessToken;
    public $redirectUri;
    public $incomingCode;
    private $redis;

    public function __construct($token = '', $userId = '', $appId = '', $appSecret = '', $pageId = '', $pageName = '', $shortLivedAccessToken = '', $longLiveAccessToken = '', $redirectUri = '', $incomingCode = '', $redis = '')
    {
        $this->token = $token;
        $this->userId = $userId;
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->pageId = $pageId;
        $this->pageName = $pageName;
        $this->shortLivedAccessToken = $shortLivedAccessToken;
        $this->longLiveAccessToken = $longLiveAccessToken;
        $this->redirectUri = $redirectUri;
        $this->incomingCode = $incomingCode;
        $this->redis = new Predis\Client([
            'scheme' => 'tcp',
            'host' => 'localhost',
            'port' => 6379,
            'password' => '',
        ]);
    }
    
    public function exchangeToken()
    {
        if (empty($this->appSecret) || empty($this->shortLivedAccessToken)) {
            throw new InvalidArgumentException('App secret or short-lived access token is missing.');
        }

        $url = "https://graph.facebook.com/v22.0/oauth/access_token";
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $this->shortLivedAccessToken,
        ];

        $response = $this->fetchApiResponse($url, $params);

        if (empty($response) || !isset($response['access_token'])) {
            die("Token exchange failed. Response: " . json_encode($response));
        }

        $this->longLiveAccessToken = $response['access_token'];

        return true;
    }


    public function getPages()
    {
        if (empty($this->shortLivedAccessToken)) {
            throw new InvalidArgumentException('Long-lived access token is missing.');
        }

        $url = "https://graph.facebook.com/v22.0/me/accounts";
        $params = [
            'access_token' => $this->shortLivedAccessToken,
//            'fields' => 'id,name,access_token,instagram_business_account',
        ];

        $response = $this->fetchApiResponse($url, $params);

        if (empty($response) || !isset($response['data'])) {
            throw new UnexpectedValueException(
                "Invalid response from Graph API. Response: " . json_encode($response)
            );
        }

        return $response['data'];
    }

// Helper method for API requests
    private function fetchApiResponse(string $url, array $params): array
    {
        $query = http_build_query($params);
        $response = file_get_contents("{$url}?{$query}");

        if ($response === false) {
            throw new RuntimeException("Failed to fetch data from {$url}");
        }

        return json_decode($response, true);
    }
}