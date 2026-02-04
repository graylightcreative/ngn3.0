<?php
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\PredictRequest;
use Google\Protobuf\Struct;
use Google\Protobuf\Value;
use Google\Auth\Credentials\ServiceAccountCredentials;

/**
 * Intelligence - AI integration class for NGN
 *
 * Required environment variables:
 * - GOOGLE_APPLICATION_CREDENTIALS: Path to Google Cloud service account JSON file
 * - GOOGLE_CLOUD_PROJECT_ID: Google Cloud project ID (e.g., 'ngn2024')
 * - GEMINI_MODEL_NAME: (optional) Gemini model to use (default: 'gemini-1.5-flash-002')
 * - GOOGLE_CLOUD_API_ENDPOINT: (optional) API endpoint (default: 'us-central1-aiplatform.googleapis.com')
 */
class Intelligence
{
    private const LOW_COST_MODEL = 'gemini-2.5-flash-preview-09-2025';

    private $vertexEndpoint;
    private $vertexCredentials;
    private $geminiEndpoint;
    private $geminiAccessToken;
    private $startDate;
    private $endDate;
    private $projectId;
    private $region;
    private $modelId;
    private $vertexClient;
    private $endpointClient;

    public function __construct($vertexEndpoint = null, $vertexCredentials = null, $geminiEndpoint = null, $geminiAccessToken = null, $startDate = null, $endDate = null)
    {
        // Initialize connection properties from env vars with fallbacks
        $this->vertexEndpoint = $vertexEndpoint;
        $this->vertexCredentials = $vertexCredentials ?: $this->getEnv('GOOGLE_APPLICATION_CREDENTIALS');
        $this->geminiEndpoint = $geminiEndpoint ?: $this->getEnv('GOOGLE_CLOUD_API_ENDPOINT', 'us-central1-aiplatform.googleapis.com');
        $this->geminiAccessToken = $geminiAccessToken;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->projectId = $this->getEnv('GOOGLE_CLOUD_PROJECT_ID');
        $this->region = 'us-central1';
        $this->modelId = self::LOW_COST_MODEL;
    }

    /**
     * Get environment variable with fallback
     */
    private function getEnv(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Check if AI is properly configured
     */
    public function isConfigured(): bool
    {
        $credPath = $this->getEnv('GOOGLE_APPLICATION_CREDENTIALS');
        $projectId = $this->getEnv('GOOGLE_CLOUD_PROJECT_ID');
        return !empty($credPath) && !empty($projectId) && file_exists($credPath);
    }

    /**
     * Get configuration status for debugging
     */
    public function getConfigStatus(): array
    {
        $credPath = $this->getEnv('GOOGLE_APPLICATION_CREDENTIALS');
        return [
            'configured' => $this->isConfigured(),
            'project_id' => $this->getEnv('GOOGLE_CLOUD_PROJECT_ID') ?: '(not set)',
            'credentials_path' => $credPath ?: '(not set)',
            'credentials_exists' => !empty($credPath) && file_exists($credPath),
            'model' => $this->modelId,
            'endpoint' => $this->geminiEndpoint,
        ];
    }

    ///////////////////////////////////////////////////////////
    // --- Vertex AI Methods ---
    ///////////////////////////////////////////////////////////

    private function connectToVertex()
    {
        $credPath = $this->getEnv('GOOGLE_APPLICATION_CREDENTIALS');
        if (empty($credPath) || !file_exists($credPath)) {
            throw new \Exception('GOOGLE_APPLICATION_CREDENTIALS not set or file not found');
        }

        // Set the environment variable for your service account key file
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credPath);

        // Initialize the PredictionServiceClient
        $this->vertexClient = new PredictionServiceClient();

        // Initialize the EndpointServiceClient
        $this->endpointClient = new EndpointServiceClient();

        // Store project ID and region
        $this->projectId = $this->getEnv('GOOGLE_CLOUD_PROJECT_ID');
        if (empty($this->projectId)) {
            throw new \Exception('GOOGLE_CLOUD_PROJECT_ID not set');
        }
        $this->region = 'us-central1';
    }

    public function testVertexConnection()
    {
        try {
            $this->connectToVertex();
            $status = ($this->vertexClient && $this->endpointClient) ? 'Vertex AI connection succeeded' : 'Vertex AI connection failed';
            return $status;
        } catch (Exception $e) {
            return 'Vertex AI connection failed: ' . $e->getMessage();
        }
    }


    ///////////////////////////////////////////////////////////
    // --- Gemini Methods ---
    ///////////////////////////////////////////////////////////

    private function connectToGemini()
    {
        // Initialize HTTP client
        $client = new \GuzzleHttp\Client();

        // Set the Gemini endpoint if it's not already set
        if (!$this->geminiEndpoint) {
            throw new \Exception('Gemini endpoint is not defined');
        }
        try {
            // Request Gemini access token
            $response = $client->request('POST', $this->geminiEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->geminiAccessToken
                ]
            ]);

            // Check for successful response
            if ($response->getStatusCode() === 200) {
                $this->geminiAccessToken = json_decode((string)$response->getBody(), true)['access_token'];
            } else {
                throw new \Exception('Failed to obtain Gemini access token');
            }
        } catch (\Exception $e) {
            // Handle exceptions
            echo 'Gemini connection failed: ' . $e->getMessage();
        }
    }

    public function generateAIText($prompt)
    {
        // Check configuration first
        if (!$this->isConfigured()) {
            $status = $this->getConfigStatus();
            return 'AI not configured: ' . json_encode($status);
        }

        $credPath = $this->getEnv('GOOGLE_APPLICATION_CREDENTIALS');
        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/cloud-platform',
            $credPath
        );

        $accessToken = $credentials->fetchAuthToken()['access_token'];

        $projectId = $this->projectId ?: $this->getEnv('GOOGLE_CLOUD_PROJECT_ID');
        $locationId = $this->region;
        $modelId = $this->modelId;
        $apiUrl = "https://{$this->geminiEndpoint}/v1/projects/{$projectId}/locations/{$locationId}/publishers/google/models/{$modelId}:generateContent";

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (curl_errno($ch)) {
            return 'Curl error: ' . curl_error($ch);
        }

        if ($httpCode == 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return $responseData['candidates'][0]['content']['parts'][0]['text'];
            }
            return 'Unexpected response format: ' . json_encode($responseData);
        } else {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
            return "API error: HTTP {$httpCode} - {$errorMsg}";
        }
    }

    ///////////////////////////////////////////////////////////
    // --- Workflow Methods ---
    ///////////////////////////////////////////////////////////

    // 1. Predictive Rankings
    public function generateAIPredictiveRankings()
    {
        // Fetch historical chart data from database
        // Prepare data for Vertex AI prediction
        // Send prediction request to Vertex AI
        // Process predictions and format for display
        // Return formatted predictions
    }

    // 2. Artists You May Like
    public function getAIArtistRecommendations($userId)
    {
        // Fetch user data and listening history
        // Prepare data for Vertex AI prediction
        // Send prediction request to Vertex AI
        // Process predictions and return recommendations
    }

    // 3. AI-Generated Articles
    public function generateAIDailyArticles()
    {
        // Fetch AI writer data from database
        // Fetch trending data from Vertex AI
        // Generate articles for each AI writer using Gemini
        // Store or publish generated articles
    }

    // 4. Social Media Campaigns
    public function runAISocialMediaCampaign()
    {
        // Fetch trending data or predictions from Vertex AI
        // Generate social media posts using Gemini
        // Post to Facebook and Instagram
    }

    // 5. Email Marketing
    public function sendAIEmailCampaign()
    {
        // Segment users based on Vertex AI predictions
        // Generate email content for each segment using Gemini
        // Create and send email campaign via Mailchimp
    }



    ///////////////////////////////////////////////////////////
    // ANALYTIC ANALYSIS
    ///////////////////////////////////////////////////////////
    public function getHighestTierArtist()
    {
        $uniqueIds = [];
        $content = $this->handleNGNContent();
        foreach ($content['artists'] as $categoryName => $categoryResults) {
            // we have signifcant_jump,new_entries,high_ranking_debuts,top,trending,increased_mentions,highest_traffic,new_releases
            foreach ($categoryResults as $result) {
                $id = $result['artist_id'] ?? null;
                if ($id) {
                    if (!isset($uniqueIds[$id])) {
                        $uniqueIds[$id] = ['count' => 0, 'details' => $result];
                    }
                    $uniqueIds[$id]['count']++;
                    $uniqueIds[$id]['categories'][$categoryName] = true;
                }
            }


        }
        // Filter artists that appear in more than 2 categories
        foreach ($uniqueIds as $artistId => $artistData) {
            if ($artistData['count'] <= 2) {
                unset($uniqueIds[$artistId]);
            }
        }


        return $uniqueIds;
    }
    public function handleNGNContent($artists = true, $labels = false)
    {
        $data = $this->fetchData();
        $dataSet = [];
        if($artists){
            $dataSet['artists'] = [];
            $dataSet['artists']['significant_jump'] = $this->analyzeSignificantJumps($data['smr']);
            $dataSet['artists']['new_entries'] = $this->analyzeNewEntries($data['smr']);
            $dataSet['artists']['high_ranking_debuts'] = $this->analyzeHighRankingDebuts($data['smr']);
            $dataSet['artists']['top'] = $this->analyzeTopArtists($data['ngnArtistRankings']);
            $dataSet['artists']['trending'] = $this->analyzeTrendingArtists($data['ngnArtists'], $data['ngnArtistRankingsWeekly'], $data['ngnArtistRankingsMonthly']);
            $dataSet['artists']['increased_mentions'] = $this->analyzeArtistsWithIncreasedMentions($data['ngnArtists'], $data['postMentions']);
            $dataSet['artists']['highest_traffic'] = $this->analyzeArtistsWithHighTraffic($data['ngnArtists'], $data['hits']);
            $dataSet['artists']['new_releases'] = $this->getArtistsWithNewReleases($data);
        } else if($labels) {
            $dataSet['labels'] = [];
            $dataSet['labels']['with_top_trending_artists'] = $this->analyzeLabelsWithHighestTrendingArtists($data);
            $dataSet['labels']['increased_mentions'] = $this->analyzeLabelsWithHighestMentions($data['ngnLabels'], $data['postMentions']);
            $dataSet['labels']['top'] = $this->analyzeTopLabels($data['ngnLabelRankings']);
            $dataSet['labels']['trending'] = $this->analyzeTrendingLabels($data);
        }

        return $dataSet;
    }
    
    public function handleNGNLabelContent()
    {
        $data = $this->fetchData();
        $dataSet = [];

        return $dataSet;
    }

    public function isArtistTrending($artistId,$data){
        // we must pass in fetchData as $data
        $trendingArtists = $this->analyzeTrendingArtists($data['ngnArtists'], $data['ngnArtistRankingsWeekly'], $data['ngnArtistRankingsMonthly']);
        foreach($trendingArtists as $trendingArtist){
            if($trendingArtist['artist_id'] == $artistId){
                return true;
            }
        }
        return false;
    }


    public function getArtistsWithNewReleases($data){
        $newReleases = [];
        if($data['releases']){
            // we have releases
            foreach($data['releases'] as $release){
                if(strtotime($release['ReleaseDate']) >= $this->getStartDate() && strtotime($release['ReleaseDate'] <= $this->getEndDate())){
                    $newReleases[] = $release;
                }
            }
        }
        foreach($newReleases as $key => $newRelease){
            $artistId = $newRelease['ArtistId'];

            $artist = null;
            foreach ($data['ngnArtists'] as $ngnArtist) {
                if ($ngnArtist['artist_id'] == $artistId) {
                    $artist = $ngnArtist;
                    break;
                }
            }
            if($artist){
                // we have our artist
                $newReleases[$key]['artist_name'] = $artist['artist_name'];
            }
        }
        return $newReleases;
    }
    public function analyzeSignificantJumps($smr)
    {
        $significantJumps = [];
        $processedEntries = [];

        foreach ($smr as $chartEntry) {

            $artists = $this->handleSMRArtists($chartEntry['Artists']);
            $song = strtolower($chartEntry['Song']);

            foreach ($artists as $artistName) {
                $check = search('users', 'Title', strtolower($artistName));
                if ($check) {
                    $check = $check[0];
                    $entryKey = $check['Title'] . '_' . $song;

                    if (!in_array($entryKey, $processedEntries)) {

                        $entryTimestamp = strtotime($chartEntry['Timestamp']);
                        if ($entryTimestamp >= strtotime($this->getStartDate()) && $entryTimestamp <= strtotime($this->getEndDate())) {

                            $positionDifference = $this->calculateDifference($chartEntry['TWP'], $chartEntry['LWP']);
                            if ($positionDifference >= 10) {
                                $significantJumps[] = [
                                    'type' => 'artist',
                                    'artist_id' => $check['Id'],
                                    'artist_name' => $check['Title'],
                                    'song_title' => $chartEntry['Song'],
                                    'chart_name' => 'SMR Top 200',
                                    'timestamp' => $chartEntry['Timestamp'],
                                    'previous_position' => $chartEntry['LWP'],
                                    'current_position' => $chartEntry['TWP'],
                                ];
                            }
                        }
                        $processedEntries[] = $entryKey;
                    }
                }
            }
        }
        return $significantJumps;
    }
    public function analyzeNewEntries($smr)
    {
        $newEntries = [];
        $processedEntries = [];

        foreach ($smr as $chartEntry) {
            $artist = strtolower($chartEntry['Artists']);
            $artists = $this->handleSMRArtists($artist);
            $song = strtolower($chartEntry['Song']);
            foreach ($artists as $artistName) {
                $entryKey = $artistName . '_' . $song;
                $check = search('users', 'Title', strtolower($artistName));
                if ($check) {
                    $check = $check[0];
                    $entryTimestamp = strtotime($chartEntry['Timestamp']);
                    if ($entryTimestamp >= strtotime($this->getStartDate()) && $entryTimestamp <= strtotime($this->getEndDate()) && !in_array($entryKey, $processedEntries)) {
                        if ($chartEntry['TWP'] !== 0 && $chartEntry['LWP'] === 0) {
                            $newEntries[] = [
                                'type' => 'artist',
                                'artist_id' => $check['Id'],
                                'artist_name' => $check['Title'],
                                'song_title' => $chartEntry['Song'],
                                'timestamp' => $chartEntry['Timestamp'],
                                'chart_name' => 'SMR Top 200',
                            ];
                            $processedEntries[] = $entryKey;
                        }
                    }
                }
            }
        }
        return $newEntries;
    }
    public function analyzeHighRankingDebuts($smr)
    {
        $highRankingDebuts = [];
        foreach ($smr as $chartEntry) {
            $entryTimestamp = strtotime($chartEntry['Timestamp']);
            if ($entryTimestamp >= strtotime($this->getStartDate()) && $entryTimestamp <= strtotime($this->getEndDate())) {
                $artists = $this->handleSMRArtists($chartEntry['Artists']);
                foreach ($artists as $artistName) {
                    $check = search('users', 'Title', strtolower($artistName));
                    if ($check) {
                        $check = $check[0];
                        if ($chartEntry['LWP'] === 0 && $chartEntry['TWP'] <= 100 && $check) {
                            $highRankingDebuts[] = [
                                'type' => 'artist',
                                'artist_id' => $check['Id'],
                                'artist_name' => $check['Title'],
                                'song_title' => $chartEntry['Song'],
                                'chart_name' => 'SMR Top 200',
                                'timestamp' => $chartEntry['Timestamp'],
                                'debut_position' => $chartEntry['TWP'],
                            ];
                        }
                    }
                }
            }
        }
        return $highRankingDebuts;
    }
    public function analyzeTopLabels($ngnLabelRankings)
    {
        $ngnLabelRankings = sortByColumnIndex($ngnLabelRankings, 'Score', SORT_DESC);
        $topLabels = array_slice($ngnLabelRankings, 0, 25);
        $entries = [];
        foreach ($topLabels as $label) {
            $l = read('users', 'Id', $label['LabelId']);
            $entry = [
                'type' => 'label',
                'label_name' => $l['Title'],
                'label_id' => $l['Id'],
                'timestamp' => $label['Timestamp'],
            ];
            $entries[] = $entry;
        }
        return $entries;
    }
    public function analyzeTopArtists($ngnArtistRankings)
    {
        $ngnArtistRankings = sortByColumnIndex($ngnArtistRankings, 'Score', SORT_DESC);
        $topArtists = array_slice($ngnArtistRankings, 0, 25);
        $entries = [];
        foreach ($topArtists as $item) {
            $a = read('users', 'Id', $item['ArtistId']);
            $entry = [
                'type' => 'artist',
                'artist_name' => $a['Title'],
                'artist_id' => $a['Id'],
                'timestamp' => $item['Timestamp'],
            ];
            $entries[] = $entry;
        }
        return $entries;
    }
    public function analyzeTrendingArtists($ngnArtists, $ngnArtistRankingsWeekly, $ngnArtistRankingsMonthly)
    {
        $trendingArtists = [];
        $startTimestamp = strtotime($this->getStartDate());
        $endTimestamp = strtotime($this->getEndDate());

        foreach ($ngnArtists as $artist) {
            $a = read('NGNArtistRankings', 'ArtistId', $artist['Id']);
            if ($a) {
                $currentScore = $a['Score'];
                $weeklyRanking = array_filter($ngnArtistRankingsWeekly, function ($entry) use ($artist, $startTimestamp, $endTimestamp) {
                    return $entry['ArtistId'] == $artist['Id'] && strtotime($entry['Timestamp']) >= $startTimestamp && strtotime($entry['Timestamp']) <= $endTimestamp;
                });
                $monthlyRanking = array_filter($ngnArtistRankingsMonthly, function ($entry) use ($artist, $startTimestamp, $endTimestamp) {
                    return $entry['ArtistId'] == $artist['Id'] && strtotime($entry['Timestamp']) >= $startTimestamp && strtotime($entry['Timestamp']) <= $endTimestamp;
                });

                $weeklyRanking = sortByColumnIndex($weeklyRanking, 'Timestamp');
                $monthlyRanking = sortByColumnIndex($monthlyRanking, 'Timestamp');

                $latestWeeklyRanking = isset($weeklyRanking[0]) ? $weeklyRanking[0] : false;
                $latestMonthlyRanking = isset($monthlyRanking[0]) ? $monthlyRanking[0] : false;

                if ($latestWeeklyRanking && $latestMonthlyRanking) {
                    $weeklyIncrease = $this->calculatePercentageIncrease($latestWeeklyRanking['Score'], $currentScore);
                    $monthlyIncrease = $this->calculatePercentageIncrease($latestMonthlyRanking['Score'], $currentScore);

                    if ($weeklyIncrease >= 25 || $monthlyIncrease >= 50) {
                        $trendingArtists[] = [
                            'type' => 'artist',
                            'artist_id' => $artist['Id'],
                            'artist_name' => $artist['Title'],
                            'weekly_increase' => $weeklyIncrease,
                            'monthly_increase' => $monthlyIncrease,
                            'timestamp' => date('Y-m-d', strtotime('now'))
                        ];
                    }
                }
            }

        }
        return $trendingArtists;
    }
    public function analyzeArtistsWithIncreasedMentions($ngnArtists, $postMentions)
    {
        $artistsWithIncreasedMentions = [];
        $currentWeekMentions = [];
        $lastWeekMentions = [];
        $startTimestamp = strtotime($this->getStartDate());
        $endTimestamp = strtotime($this->getEndDate());
        $currentTime = strtotime('now');

        foreach ($postMentions as $mention) {
            $mentionTimestamp = strtotime($mention['Timestamp']);
            if ($mentionTimestamp >= $startTimestamp && $mentionTimestamp <= $endTimestamp) {
                $artistId = $mention['ArtistId'];
                $weeksAgo = floor(($currentTime - $mentionTimestamp) / (7 * 24 * 60 * 60));

                if ($weeksAgo == 0) {
                    $currentWeekMentions[$artistId] = ($currentWeekMentions[$artistId] ?? 0) + 1;
                } elseif ($weeksAgo == 1) {
                    $lastWeekMentions[$artistId] = ($lastWeekMentions[$artistId] ?? 0) + 1;
                }
            }
        }

        foreach ($ngnArtists as $artist) {
            $artistId = $artist['Id'];
            $currentMentions = $currentWeekMentions[$artistId] ?? 0;
            $lastMentions = $lastWeekMentions[$artistId] ?? 0;
            $mentionIncrease = $this->calculatePercentageIncrease($lastMentions, $currentMentions);

            if ($mentionIncrease >= 50) {
                $artistsWithIncreasedMentions[] = [
                    'type' => 'artist',
                    'artist_id' => $artistId,
                    'artist_name' => $artist['Title'],
                    'mention_increase' => $mentionIncrease,
                ];
            }
        }

        return $artistsWithIncreasedMentions;
    }
    public function analyzeArtistsWithHighTraffic($ngnArtists, $hits)
    {
        $artistsWithHighTraffic = [];
        $artistHits = [];
        $startTimestamp = strtotime($this->getStartDate());
        $endTimestamp = strtotime($this->getEndDate());

        foreach ($hits as $hit) {
            if ($hit['Action'] == 'artist_view') {
                $hitTimestamp = strtotime($hit['Timestamp']);
                if ($hitTimestamp >= $startTimestamp && $hitTimestamp <= $endTimestamp) {
                    $artistId = $hit['EntityId'];
                    if (!isset($artistHits[$artistId])) {
                        $artistHits[$artistId] = 0;
                    }
                    $artistHits[$artistId] += $hit['ViewCount'];
                }
            }
        }

        foreach ($artistHits as $artistId => $hitsCount) {
            if ($hitsCount >= 25) {
                $artist = read('users', 'Id', $artistId);
                if ($artist) {
                    $artistsWithHighTraffic[] = [
                        'type' => 'artist',
                        'artist_id' => $artistId,
                        'artist_name' => $artist['Title'],
                        'hits' => $hitsCount,
                    ];
                }
            }
        }

        return $artistsWithHighTraffic;
    }
    public function analyzeLabelsWithHighestTrendingArtists($data)
    {
        $labelDataStorage = [];
        foreach ($data['ngnLabels'] as $label) {
            $labelDataStorage[$label['Id']] = [];
            $labelArtists = readMany('users','LabelId',$label['Id']);

            $trendingArtists = [];
            if($labelArtists){
                // we have artists
                // check if trending
                foreach($labelArtists as $labelArtist){
                    if($this->isArtistTrending($labelArtist['Id'],$data)){
                        $trendingArtists[] = $labelArtist['Id'];
                    }
                }
            }
            $labelDataStorage[$label['Id']]['trending_artists'] = $trendingArtists;


        }
        return $labelDataStorage;
    }

    public function analyzeLabelsWithHighestMentions($ngnLabels, $postMentions)
    {
        $labelMentions = [];
        $startTimestamp = strtotime($this->getStartDate());
        $endTimestamp = strtotime($this->getEndDate());
        $currentTime = strtotime('now');
        foreach ($postMentions as $mention) {
            $mentionTimestamp = strtotime($mention['Timestamp']);
            if ($mentionTimestamp >= $startTimestamp && $mentionTimestamp <= $endTimestamp) {
                $labelId = $mention['LabelId'];
                $weeksAgo = floor(($currentTime - $mentionTimestamp) / (7 * 24 * 60 * 60));
                if ($weeksAgo == 0 || $weeksAgo == 1) {
                    $labelMentions[$labelId][] = [
                        'type' => 'label',
                        'artist_id' => $mention['ArtistId'],
                        'label_id' => $mention['LabelId'],
                        'found_in' => $mention['FoundIN'],
                        'timestamp' => $mention['Timestamp'],
                        'article_id' => $mention['PostId'],
                    ];
                }
            }
        }
        $labelsWithHighestMentions = [];
        foreach ($ngnLabels as $label) {
            $labelId = $label['Id'];
            $labelMentionsCount = count($labelMentions[$labelId] ?? []);
            if ($labelMentionsCount >= 5) {
                $labelsWithHighestMentions[] = [
                    'label_id' => $labelId,
                    'label_name' => $label['Title'],
                    'mentions' => $labelMentionsCount,
                ];
            }
        }
        return $labelsWithHighestMentions;
    }




    ///////////////////////////////////////////////////////////
    // SMR CHARTS
    ///////////////////////////////////////////////////////////
    public function handleSMRArtists($artists)
    {
        $artists = strtolower($artists); // Convert to lowercase for easier comparison

        // Split the string by comma first
        $artistList = explode(',', $artists);

        $finalArtistList = [];
        $knownArtists = ['hearts & hand grenades', 'of mice & men'];

        foreach ($artistList as $artist) {
            $artist = trim($artist); // Trim whitespace

            if (strpos($artist, ' ft. ') !== false) {
                // we have artist ft artist
                $subArtists = explode(' ft. ', $artist);
                foreach ($subArtists as $subArtist) {
                    $finalArtistList[] = trim($subArtist);
                }
            } else {
                // If there's an ampersand, check if it's part of a single artist's name
                if (strpos($artist, '&') !== false) {
                    // Hypothetical example: Assume you have a list of artist names in an array $knownArtists

                    if (in_array($artist, $knownArtists)) {
                        // It's a single artist with '&' in their name
                        $finalArtistList[] = $artist;
                    } else {
                        // It's multiple artists separated by '&'
                        $subArtists = explode('&', $artist);
                        foreach ($subArtists as $subArtist) {
                            $finalArtistList[] = trim($subArtist);
                        }
                    }
                } else {
                    $finalArtistList[] = $artist;
                }
            }

        }


        return $finalArtistList;
    }




    ///////////////////////////////////////////////////////////
    // HELPERS
    ///////////////////////////////////////////////////////////
    private function calculatePercentageIncrease($oldValue, $newValue)
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return (($newValue - $oldValue) / $oldValue) * 100;
    }
    private function generateTitle($baseTitle)
    {
        // Simulate generating a compelling, unique title using Gemini/Vertex
        return $baseTitle . " [Generated]";
    }
    private function calculateDifference($current, $previous)
    {
        return (int)$current - (int)$previous;
    }
    public function generateTitleBasedOnPosts($postTypes,$bestArticle, $posts)
    {
        return "AI Generated Title Here";
    }
    public function getWeight($entry)
    {
        // Implement a weight calculation logic based on the entry data
        // Example: higher positions in charts could mean higher weight
        if (isset($entry['current_position'])) {
            return 200 - $entry['current_position'];
        }
        return 0;
    }
    public function setStartDate($startDate)
    {
        $this->startDate = strtotime($startDate);
    }
    public function setEndDate($endDate)
    {
        $this->endDate = strtotime($endDate);
    }
    public function getStartDate()
    {
        return date('Y-m-d H:i:s', $this->startDate);
    }
    public function getEndDate()
    {
        return date('Y-m-d H:i:s', $this->endDate);
    }
    private function fetchData()
    {
        return [
            'smr' => browse('smr_chart'),
            'ngnArtistRankings' => browse('NGNArtistRankings'),
            'ngnLabelRankings' => browse('NGNLabelRankings'),
            'posts' => browse('posts'),
            'postMentions' => browse('post_mentions'),
            'hits' => browse('hits'),
            'ngnArtists' => readMany('users', 'RoleId', 3),
            'ngnLabels' => readMany('users', 'RoleId', 7),
            'ngnWriters' => readMany('users', 'RoleId', 8),
            'postTypes' => browse('PostTypes'),
            'spins' => browse('station_spins'),
            'releases' => browse('releases'),
            'videos' => browse('videos'),
            'shows' => browse('shows'),
            'venues' => readMany('users','RoleId',11),
            'ngnArtistRankingsWeekly' => browse('NGNArtistRankingsWeekly'),
            'ngnArtistRankingsMonthly' => browse('NGNArtistRankingsMonthly')
        ];
    }

}

?>