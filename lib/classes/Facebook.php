<?php

class Facebook {
    public $token;
    public $facebookPageId;
    public $instagramPageId;
    public $longLivedToken;
    public $shortLivedToken;
    public $pageAccessToken;
    public $redirectUri;
    public $incomingCode;
    public $userAccessToken;
    private $redis;

    public function __construct($token='',$facebookPageId='', $instagramPageId = '', $longLivedToken='',$shortLivedToken='',$pageAccessToken='', $redirectUri='',$incomingCode='', $userAccessToken = '', $redis='')
    {
        $this->token = $token;
        $this->facebookPageId = $facebookPageId;
        $this->instagramPageId = $instagramPageId;

        $this->longLivedToken = $longLivedToken;
        $this->shortLivedToken = $shortLivedToken;
        $this->pageAccessToken = $pageAccessToken;
        $this->redirectUri = $redirectUri;
        $this->incomingCode = $incomingCode;
        $this->userAccessToken = $userAccessToken;
        $this->redis = new Predis\Client([
            'scheme' => 'tcp',
            'host' =>  'localhost',
            'port' => 6379,
            'password' => '',
        ]);
    }


    public function getInsightsByPageId($metric, $period = 'day', $since = '', $until = '')
    {
        $url = "https://graph.facebook.com/v22.0/{$this->facebookPageId}/insights";
        $params = [
            'metric' => $metric,
            'period' => $period,
            'since' => $since,
            'until' => $until,
            'access_token' => $this->pageAccessToken, // Use the Page Access Token here
        ];

        // Fetch API response
        $response = $this->fetchApiResponse($url, $params);

        // Check if data exists and return response
        return (!empty($response) && isset($response['data'])) ? $response['data'] : [];
    }

    public function returnFacebookMetricList()
    {
        return [
            [
                'name' => 'page_fan_adds_by_paid_non_paid_unique',
                'weight' => 2,
                'scorable' => true,
                'tracks_paid' => true
            ],
            [
                'name' => 'page_post_engagements',
                'weight' => .8,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_lifetime_engaged_followers_unique',
                'weight' => .7,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_daily_follows',
                'weight' => 1,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_daily_unfollows_unique',
                'weight' => -5,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_impressions',
                'weight' => .4,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_impressions_paid',
                'weight' => .8,
                'scorable' => true,
                'tracks_paid' => true
            ],
            [
                'name' => 'post_impressions_viral',
                'weight' => .6,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_reactions_like_total',
                'weight' => 1,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_reactions_anger_total',
                'weight' => -1,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_video_views_paid',
                'weight' => .8,
                'scorable' => true,
                'tracks_paid' => true
            ],
            [
                'name' => 'page_video_views_organic',
                'weight' => .6,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_video_complete_views_paid',
                'weight' => .9,
                'scorable' => true,
                'tracks_paid' => true
            ],
            [
                'name' => 'post_video_complete_views_organic',
                'weight' => .8,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_views_total',
                'weight' => .7,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'creator_monetization_qualified_views',
                'weight' => .9,
                'scorable' => true,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_follows',
                'weight' => .4,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_impressions_unique',
                'weight' => .5,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_clicks_by_type',
                'weight' => .6,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_impressions_unique',
                'weight' => .7,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_reactions_by_type_total',
                'weight' => .8,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_video_views_unique',
                'weight' => .9,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_video_complete_views_30s_unique',
                'weight' => 1.0,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'page_video_views_10s_unique',
                'weight' => .8,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_video_views_unique',
                'weight' => .7,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_video_views_10s_unique',
                'weight' => .6,
                'scorable' => false,
                'tracks_paid' => false
            ],
            [
                'name' => 'post_activity_by_action_type_unique',
                'weight' => .5,
                'scorable' => false,
                'tracks_paid' => false
            ]
        ];
    }
    
    // Metrics
    public function fetchAvailableMetrics()
    {



        $availableMetrics = [];

        foreach ($this->returnFacebookMetricList() as $metric) {
            $url = "https://graph.facebook.com/v22.0/{$this->facebookPageId}/insights";
//            echo "[DEBUG] Checking metric: {$metric}" . PHP_EOL;
//            echo "[DEBUG] Using URL: {$url}" . PHP_EOL;
            $data = [
                'metric' => $metric,
                'period' => 'day',
                'since' => date('Y-m-d', strtotime('-7 days')),
                'until' => '',
                'access_token' => $this->pageAccessToken, // The token provided
            ];

            // Add query parameters to the URL
            $urlWithParams = $url . '?' . http_build_query($data);
//            echo "[DEBUG] Full URL: {$urlWithParams}" . PHP_EOL;

            $results = $this->fetchApiResponse($url, $data);
            if (!empty($results)) {
                $availableMetrics[$metric] = $results;
//                echo "[DEBUG] Results for metric '{$metric}': " . print_r($results, true) . PHP_EOL;
            }
        }

        return $availableMetrics;
    }
    public function fetchInsightsByMetric($metric,$period='day',$since='',$until=''){
        $url = "https://graph.facebook.com/v22.0/{$this->facebookPageId}/insights";
        $data = [
            'metric' => $metric,
            'period' => $period,
            'since' => $since,
            'until' => $until,
            'access_token' => $this->pageAccessToken, // The token provided
        ];
        // Add query parameters to the URL
        $urlWithParams = $url . '?' . http_build_query($data);

        $results = $this->fetchApiResponse($url,$data);
        if(!empty($results)) return $results;
        return false;
    }
    public function getFacebookInsights($metric, $start='', $end='', $type='day')
    {
        $url = "https://graph.facebook.com/v22.0/{$this->facebookPageId}/insights";
        $data = [
            'metric' => $metric,
            'period' => $type,
            'since' => $start,
            'until' => $end,
            'access_token' => $this->pageAccessToken, // The token provided
        ];

        $results = $this->fetchApiResponse($url, $data);
        return $results;

    }
    public function getInstagramInsights($metric, $start='', $end='', $type='day')
    {
        $url = "https://graph.facebook.com/v22.0/{$this->instagramPageId}/insights";
        $data = [
            'metric' => $metric,
            'period' => $type,
            'since' => $start,
            'until' => $end,
            'access_token' => $this->pageAccessToken, // The token provided
        ];

        $results = $this->fetchApiResponse($url, $data);
        return $results;

    }
    // TOKENS
    public function createLongAccessToken(){
        $url = 'https://graph.facebook.com/v22.0/oauth/access_token';
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $_ENV['FACEBOOK_APP_ID'],
            'client_secret' => $_ENV['FACEBOOK_APP_SECRET'],
            'fb_exchange_token' => $this->shortLivedToken,
        ];
        $data = $this->fetchApiResponse($url, $params);
        return $data;
    }
    public function createFacebookPageToken(){
        $url = "https://graph.facebook.com/v22.0/" . $this->facebookPageId . "?fields=access_token&access_token=" . $this->longLivedToken;
        $params = [
        ];
        $data = $this->fetchApiResponse($url, $params);
        return $data;
    }

    public function fetchInstagramBusinessAccountId(){
        $url = "https://graph.facebook.com/v22.0/" . $this->facebookPageId . "?fields=instagram_business_account&access_token=" . $this->longLivedToken;
        $params = [
        ];
        $data = $this->fetchApiResponse($url, $params);
        if(!empty($data['instagram_business_account']['id'])){
            return $data['instagram_business_account']['id'];
        }
        return false;
    }

    // HELPERS
    public function getMetrics()
    {
        $possibleMetrics = [
            'page_fan_adds_by_paid_non_paid_unique',
            'page_post_engagements',
            'page_lifetime_engaged_followers_unique',
            'page_daily_follows',
            'page_daily_follows_unique',
            'page_daily_unfollows_unique',
            'page_follows',
            'page_impressions',
            'page_impressions_unique',
            'page_impressions_paid',
            'page_impressions_paid_unique',
            'page_impressions_viral',
            'page_impressions_viral_unique',
            'page_impressions_nonviral',
            'page_impressions_nonviral_unique',
            'page_posts_impressions',
            'page_posts_impressions_unique',
            'page_posts_impressions_paid',
            'page_posts_impressions_paid_unique',
            'page_posts_impressions_organic_unique',
            'page_posts_served_impressions_organic_unique',
            'page_posts_impressions_viral',
            'page_posts_impressions_viral_unique',
            'page_posts_impressions_nonviral',
            'page_posts_impressions_nonviral_unique',
            'post_clicks',
            'post_clicks_by_type',
            'hide_clicks',
            'hide_all_clicks',
            'report_spam_clicks',
            'unlike_page_clicks',
            'post_impressions',
            'post_impressions_unique',
            'post_impressions_paid',
            'post_impressions_paid_unique',
            'post_impressions_fan',
            'post_impressions_fan_unique',
            'post_impressions_organic',
            'post_impressions_organic_unique',
            'post_impressions_viral',
            'post_impressions_viral_unique',
            'post_impressions_nonviral',
            'post_impressions_nonviral_unique',
            'post_reactions_like_total',
            'post_reactions_love_total',
            'post_reactions_wow_total',
            'post_reactions_haha_total',
            'post_reactions_sorry_total',
            'post_reactions_anger_total',
            'post_reactions_by_type_total',
            'page_actions_post_reactions_like_total',
            'page_actions_post_reactions_love_total',
            'page_actions_post_reactions_wow_total',
            'page_actions_post_reactions_haha_total',
            'page_actions_post_reactions_sorry_total',
            'page_actions_post_reactions_anger_total',
            'page_actions_post_reactions_total',
            'page_fans',
            'page_fans_locale',
            'page_fans_city',
            'page_fans_country',
            'page_fan_adds',
            'page_fan_adds_unique',
            'page_fan_removes',
            'page_fan_removes_unique',
            'page_video_views',
            'page_video_views_by_uploaded_hosted',
            'page_video_views_paid',
            'page_video_views_organic',
            'page_video_views_by_paid_non_paid',
            'page_video_views_autoplayed',
            'page_video_views_click_to_play',
            'page_video_views_unique',
            'page_video_repeat_views',
            'page_video_complete_views_30s',
            'page_video_complete_views_30s_paid',
            'page_video_complete_views_30s_organic',
            'page_video_complete_views_30s_autoplayed',
            'page_video_complete_views_30s_click_to_play',
            'page_video_complete_views_30s_unique',
            'page_video_complete_views_30s_repeat_views',
            'post_video_complete_views_30s_autoplayed',
            'post_video_complete_views_30s_clicked_to_play',
            'post_video_complete_views_30s_organic',
            'post_video_complete_views_30s_paid',
            'post_video_complete_views_30s_unique',
            'page_video_views_10s',
            'page_video_views_10s_paid',
            'page_video_views_10s_organic',
            'page_video_views_10s_autoplayed',
            'page_video_views_10s_click_to_play',
            'page_video_views_10s_unique',
            'page_video_views_10s_repeat',
            'page_video_view_time',
            'page_uploaded_3s_to_15s_views_rate',
            'page_uploaded_views_15s_count',
            'page_uploaded_views_60s_excludes_shorter_unique_count_by_is_60s_returning_viewer',
            'page_views_total',
            'post_video_avg_time_watched',
            'post_video_complete_views_organic',
            'post_video_complete_views_organic_unique',
            'post_video_complete_views_paid',
            'post_video_complete_views_paid_unique',
            'post_video_retention_graph',
            'post_video_retention_graph_clicked_to_play',
            'post_video_retention_graph_autoplayed',
            'post_video_views_organic',
            'post_video_views_organic_unique',
            'post_video_views_paid',
            'post_video_views_paid_unique',
            'post_video_length',
            'post_video_views',
            'post_video_views_unique',
            'post_video_views_autoplayed',
            'post_video_views_clicked_to_play',
            'post_video_views_15s',
            'post_video_views_60s_excludes_shorter',
            'post_video_views_10s',
            'post_video_views_10s_unique',
            'post_video_views_10s_autoplayed',
            'post_video_views_10s_clicked_to_play',
            'post_video_views_10s_organic',
            'post_video_views_10s_paid',
            'post_video_views_10s_sound_on',
            'post_video_views_sound_on',
            'post_video_view_time',
            'post_video_view_time_organic',
            'post_video_view_time_by_age_bucket_and_gender',
            'post_video_view_time_by_region_id',
            'post_video_views_by_distribution_type',
            'post_video_view_time_by_distribution_type',
            'post_video_view_time_by_country_id',
            'post_video_views_live',
            'post_video_social_actions_count_unique',
            'post_video_live_current_viewers',
            'post_video_15s_to_60s_excludes_shorter_views_rate',
            'post_video_views_by_live_status',
            'post_activity_by_action_type',
            'post_activity_by_action_type_unique',
            'page_daily_video_ad_break_ad_impressions_by_crosspost_status',
            'page_daily_video_ad_break_cpm_by_crosspost_status',
            'page_daily_video_ad_break_earnings_by_crosspost_status',
            'post_video_ad_break_ad_impressions',
            'post_video_ad_break_earnings',
            'post_video_ad_break_ad_cpm',
            'creator_monetization_qualified_views'
        ];
        $wanted = [
            'page_post_engagements',
            'page_lifetime_engaged_followers_unique',
            'page_impressions_unique',
            'page_posts_impressions_unique',
            'post_clicks_by_type',
            'post_impressions_unique',
            'post_reactions_by_type_total',
            'page_fans',
            'page_video_views_unique',
            'page_video_complete_views_30s_unique',
            'page_video_views_10s_unique',
            'post_video_views_unique',
            'post_video_views_10s_unique',
            'post_activity_by_action_type_unique'
        ];
        return $wanted;
    }
    private function fetchApiResponse(string $url, array $params): array
    {
        $apiUrl = $url . '?' . http_build_query($params);

        try {

            // Verify URL is reachable
            $headers = @get_headers($apiUrl);
            if ($headers === false || strpos($headers[0], '200') === false) {
                return [];
            }
            $response = file_get_contents($apiUrl);

            if ($response === false) {
                throw new RuntimeException("Failed to connect to Facebook API.");
            }

            $data = json_decode($response, true);

            // Handle API errors
            if (isset($data['error'])) {
                // Log Facebook's error response
//                error_log("Facebook API Error: " . json_encode($data['error']));
                throw new RuntimeException("Facebook API returned an error: " . $data['error']['message']);
            }

            return $data ?? [];
        } catch (Exception $e) {
            // Log internal exception
            echo "Exception in fetchApiResponse: " . $e->getMessage();
            return [];
        }
    }
    public function getPermissions(){
        // Graph API endpoint for retrieving permissions
        $url = "https://graph.facebook.com/v22.0/me/permissions";
        $data = [
            'access_token' => $this->token, // Your stored access token
        ];

        // Make the API request
        $response = @file_get_contents($url . '?' . http_build_query($data));

        if ($response === FALSE) {
            // Handle errors gracefully
            $error = error_get_last();
            echo "Error retrieving permissions: " . $error['message'];
            return null;
        }

        // Decode the response
        $result = json_decode($response, true);

        // Check for errors in the API response
        if (isset($result['error'])) {
            echo "API Error: " . $result['error']['message'];
            return null;
        }

        // Return the list of permissions
        return isset($result['data']) ? $result['data'] : null;
    }
    public function debugToken($token){

        // Generate an App Access Token
        $appAccessToken = $_ENV['FACEBOOK_APP_ID'] . '|' . $_ENV['FACEBOOK_APP_SECRET'];

        // Debug Token API endpoint
        $url = "https://graph.facebook.com/v22.0/debug_token";

        // Parameters
        $data = [
            'input_token' => $token, // The user's token to debug
            'access_token' => $appAccessToken, // App access token
        ];

        // Make API Request
        $response = @file_get_contents($url . '?' . http_build_query($data));

        if ($response === FALSE) {
            $error = error_get_last();
//            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/facebook_errors.log', "Error debugging token: " . $error['message'] . PHP_EOL, FILE_APPEND);
//            echo "Error debugging token: " . $error['message'];
            return false;
        }

        // Decode the response
        $result = json_decode($response, true);

        // Check for errors in API response
        if (isset($result['error'])) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/facebook_errors.log', "API Error: " . $result['error']['message'] . PHP_EOL, FILE_APPEND);
            echo "API Error: " . $result['error']['message'];
            return false;
        }

        // Return the token debug information
        return isset($result['data']) ? $result['data'] : false;
    }
    public function hasScopes(array $requiredScopes): bool {
        // Assuming $this->debugToken() returns the debug token information
        $tokenDebugInfo = $this->debugToken();

        if (!$tokenDebugInfo || !isset($tokenDebugInfo['scopes'])) {
            return false; // Token invalid or no scopes found
        }

        $grantedScopes = $tokenDebugInfo['scopes'];

        // Check if all required scopes are included in the granted scopes
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $grantedScopes, true)) {
                return false; // Missing at least one scope
            }
        }

        return true; // All required scopes are present
    }

    public function isDataAccessExpiringSoon($token)
    {

        $tokenDebugInfo = $this->debugToken($token);

        if (!$tokenDebugInfo || !isset($tokenDebugInfo['data_access_expires_at'])) {
            return false; // Can't determine expiration
        }

        $expiresAt = $tokenDebugInfo['data_access_expires_at'];
        $daysRemaining = ($expiresAt - time()) / 86400;

        return $daysRemaining <= 30; // Warn if less than 30 days remaining
    }

    public function createScoreFromMetrics($insightsData, $metric, $weight=1)
    {
        $activeScore = 0;
        $historicScore = 0;

        foreach ($insightsData as $insights) {
            if(isset($insights['data'])){
                foreach($insights['data'] as $data){
                    $values = $data['values'];
                    foreach($values as $value){
                        if($value['value'] > 0){
                            if(strtotime($value['end_time']) >= strtotime('-90 days')){
                                $activeScore += $value['value'] * $weight;
                            } else {
                                $historicScore += $value['value'] * $weight;
                            }
                        }
                    }
                }
            }
        }


        return [
            'active' => $activeScore,
            'historic' => $historicScore
        ];
    }

// 1. Define the specific Facebook Page Insights metrics we need for scoring.
//    - Based on the available metrics, we'll prioritize those that reflect engagement, reach, and audience growth:
//        - page_post_engagements
//        - page_lifetime_engaged_followers_unique
//        - page_impressions_unique
//        - page_posts_impressions_unique
//        - post_clicks_by_type
//        - post_impressions_unique
//        - post_reactions_by_type_total
//        - page_fans
//        - page_video_views_unique
//        - page_video_complete_views_30s_unique
//        - page_video_views_10s_unique
//        - post_video_views_unique
//        - post_video_views_10s_unique
//        - post_activity_by_action_type_unique

// 2. Develop efficient API calls to the Facebook Graph API to fetch the required data for each artist and label.
//    - Use batch requests to minimize the number of API calls.
//    - Only request the specific metrics needed for scoring.
//    - Implement error handling and retry mechanisms to handle API failures gracefully.


}