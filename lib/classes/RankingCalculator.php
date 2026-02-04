<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

class RankingCalculator
{
    private $pdo;
    private $smr_pdo;
    private $spins_pdo;
    public $analyze;
    public $startDate;
    public $endDate;

    public function __construct($analyze=null, $startDate=null, $endDate=null)
    {
        $this->analyze = $analyze;
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
        $this->smr_pdo = ConnectionFactory::named($config, 'SMR2025');
        $this->spins_pdo = ConnectionFactory::named($config, 'SPINS2025');
    }

    public function checkCache()
    {
        // Check our cache
        $cache = readByDB($this->pdo,'Cache', 'Id', 1);
        $timestamp = $cache['CachedAt'] ?? false;
        if ($timestamp) {
            // If timestamp is < 3 hours old, we begin calculations
            if (strtotime($timestamp) < strtotime('-3 hours', strtotime($this->startDate))) {
                return true;
            }
        }
        return false;
    }

    public function analyzeArtists($artists){
        $analytics = [];
        foreach($artists as $artist){
            $analytics[] = $this->analyzeArtist($artist);
        }
        return $analytics;
    }

    public function analyzeArtist($artist){
        $token = read('Tokens','UserId',$artist['Id']);

        /////////////////////////////////////////
        // SPIN COUNTS
        /////////////////////////////////////////

        $spinScoreActive = 0;
        $spinScoreHistoric = 0;

        $handleSpins = $this->handleArtistSpinsCount($artist['Title'], $this->startDate,$this->endDate);
        if($handleSpins){
            $spinScoreActive = $handleSpins['active'];
            $spinScoreHistoric = $handleSpins['historic'];
        }

        /////////////////////////////////////////
        // Mentions
        /////////////////////////////////////////
        $posts = browse('posts');
        // Iterate all posts and find mentions in title,tags,summary,body
        $mentionScoreActive = 0;
        $mentionScoreHistoric = 0;
        $artistsMentionedPosts = [];
        $handlePostMentions = $this->handleArtistPostMentions($posts,$artist,$this->startDate,$this->endDate);
        if($handlePostMentions){
            $mentionScoreActive = $handlePostMentions['active'];
            $mentionScoreHistoric = $handlePostMentions['historic'];
            $artistsMentionedPosts = $handlePostMentions['posts'];
        }

        //////////////////////
        // Views
        /////////////////////
        $viewsScoreActive = 0;
        $viewsScoreHistoric = 0;

        $handleArtistViews = $this->handleArtistViews($artist,$artistsMentionedPosts,$this->startDate,$this->endDate);
        if($handleArtistViews){
            $viewsScoreActive = $handleArtistViews['active'];
            $viewsScoreHistoric = $handleArtistViews['historic'];
        }

        // Social
        $socialScoreActive = 0;
        $socialScoreHistoric = 0;

        if($token){
            $artistSocial = $this->handleSocial($artist);
            if($artistSocial){
                $socialScoreActive = $artistSocial['active'];
                $socialScoreHistoric = $artistSocial['historic'];
            }
        }

        // Videos
        $videosScoreActive = 0;
        $videosScoreHistoric = 0;
        $handleVideos = $this->handleVideos($artist,$this->startDate,$this->endDate);
        if($handleVideos){
            $videosScoreActive = $handleVideos['active'];
            $videosScoreHistoric = $handleVideos['historic'];
        }

        // Releases
        $releasesScoreActive = 0;
        $releasesScoreHistoric = 0;
        $handleReleases = $this->handleReleases($artist,$this->startDate,$this->endDate);
        if($handleReleases){
            $releasesScoreActive = $handleReleases['active'];
            $releasesScoreHistoric = $handleReleases['historic'];
        }

        $postsScoreActive = 0;
        $postsScoreHistoric = 0;
        $handlePosts = $this->handlePosts($artist,$this->startDate,$this->endDate);
        if($handlePosts){
            $postsScoreActive = $handlePosts['active'];
            $postsScoreHistoric = $handlePosts['historic'];
        }

        // Boosters
        // Label Booster
        $boostScore = 0;
        $booster = $this->getLabelBoostAgeReputationFromArtist($artist,$this->startDate,$this->endDate);
        if($booster){
            $boostScore = $booster['boost'];
            $ageScore = $booster['age'];
            $reputationScore = $booster['reputation'];
        }


        $activeScore = $postsScoreActive+$mentionScoreActive+$viewsScoreActive+$socialScoreActive+$videosScoreActive+$spinScoreActive+$releasesScoreActive+$boostScore;
        $historicScore = ($postsScoreHistoric+$mentionScoreHistoric+$viewsScoreHistoric+$socialScoreHistoric+$videosScoreHistoric+$spinScoreHistoric+$releasesScoreHistoric) / .25;

        // PREPARE RANKINGS
        $newRankings = [
            'ArtistId'=>$artist['Id'],
            'Score' => round($activeScore+$historicScore),
            'Label_Boost_Score'=>round($boostScore,2),
            'SMR_Score_Active'=>0,
            'SMR_Score_Historic'=>0,
            'Post_Mentions_Score_Active'=>round($mentionScoreActive,2),
            'Post_Mentions_Score_Historic'=>round($mentionScoreHistoric,2),
            'Views_Score_Active'=>round($viewsScoreActive,2),
            'Views_Score_Historic'=>round($viewsScoreHistoric,2),
            'Social_Score_Active'=>round($socialScoreActive,2),
            'Social_Score_Historic'=>round($socialScoreHistoric,2),
            'Videos_Score_Active'=>round($videosScoreActive,2),
            'Videos_Score_Historic'=>round($videosScoreHistoric,2),
            'Spins_Score_Active'=>round($spinScoreActive,2),
            'Spins_Score_Historic'=>round($spinScoreHistoric,2),
            'Releases_Score_Active'=>round($releasesScoreActive,2),
            'Releases_Score_Historic'=>round($releasesScoreHistoric,2),
            'Posts_Score_Active'=>round($postsScoreActive,2),
            'Posts_Score_Historic'=>round($postsScoreHistoric,2)
        ];


//        $q = 'SELECT * FROM NGNArtistRankingsDaily WHERE ArtistId = ? ORDER BY Timestamp DESC LIMIT 1';
//        $recentRankings = query($q, [$artist['Id']]);
//
//        if (!$recentRankings) {
//            $q = 'SELECT * FROM NGNArtistRankingsWeekly WHERE ArtistId = ? ORDER BY Timestamp DESC LIMIT 1';
//            $recentRankings = query($q, [$artist['Id']]);
//        }
//
//        if (!$recentRankings) {
//            $q = 'SELECT * FROM NGNArtistRankingsMonthly WHERE ArtistId = ? ORDER BY Timestamp DESC LIMIT 1';
//            $recentRankings = query($q, [$artist['Id']]);
//        }
//
//        if (!$recentRankings) {
//            $q = 'SELECT * FROM NGNArtistRankingsYearly WHERE ArtistId = ? ORDER BY Timestamp DESC LIMIT 1';
//            $recentRankings = query($q, [$artist['Id']]);
//        }
//        $changed = false;
//        if ($recentRankings) {
//            // Check if our currentRankings have changed from our recentRankings
//            $scoreChange = $newRankings['Score'] - $recentRankings[0]['Score'];
//            $newRankings['Change'] = $scoreChange;
//            if (abs($scoreChange) >= 100) {
//                // Huge jump detected, adjust accordingly
//                $change = 0;
//                if ($scoreChange > 0) {
//                    $change += ($_ENV['CHART_GAIN_WEIGHT'] * log(1 + $scoreChange));
//                } else {
//                    $change += ($scoreChange / log(1 + abs($scoreChange)));
//                }
//
//            }
//        }
        return $newRankings;
//    return add('NGNArtistRankings',$newRankings);
    }

    public function analyzeLabels($labels){
        $analytics = [];
        foreach($labels as $label){
            $analytics[] = $this->analyzeLabel($label);
        }
        return $analytics;
    }

    public function analyzeLabel($label){
        $token = read('Tokens','UserId',$label['Id']);

        // Label Artists
        $labelArtists = readMany('users','label_id', $label['id']);

        $labelArtistsTitles = [];
        if ($labelArtists) foreach ($labelArtists as $artist) $labelArtistsTitles[] = $artist['Title'];
        $labelArtistsTitles = array_unique($labelArtistsTitles);
        $labelArtistsTitles = array_values($labelArtistsTitles);
        $labelPosts = readMany('posts','author', $label['id']);

        $ageScore = 0;
        $reputationScore = 0;
        $daysOld = 0;
        $bornDate = false;

        $chartingArtists = [];
        $labelTotalSpinsCountActive = 0;
        $labelTotalSpinsCountHistoric = 0;

        $labelSMREntries = $this->getSMREntriesByLabel($label['Title']);
        if($labelSMREntries){
            $labelSMREntries = sortByColumnIndex($labelSMREntries,'Timestamp', SORT_ASC);
            $bornDate = $labelSMREntries[0]['Timestamp'];

        }

        //////////////////////////////
        /// POST MENTIONS SCORE
        //////////////////////////////
        $postMentionsScoreActive = 0;
        $postMentionsScoreHistoric = 0;
        $mentions = [];
        $posts = browse('posts');
        foreach($posts as $post){
            if(strpos($post['Body'],$label['Title']) !== false){
                $mentions[] = [
                    'found_in' => 'body',
                    'post_id' => $post['Id'],
                    'timestamp' => $post['PublishedDate']
                ];
            } else if(strpos($post['Title'],$label['Title']) !== false){
                $mentions[] = [
                    'found_in' => 'title',
                    'post_id' => $post['Id'],
                    'timestamp' => $post['PublishedDate']
                ];
            } else if(strpos($post['Summary'],$label['Title']) !== false){
                $mentions[] = [
                    'found_in' => 'summary',
                    'post_id' => $post['Id'],
                    'timestamp' => $post['PublishedDate']
                ];
            } else if(strpos($post['Tags'],$label['Title']) !== false){
                $mentions[] = [
                    'found_in' => 'tags',
                    'post_id' => $post['Id'],
                    'timestamp' => $post['PublishedDate']
                ];
            }
            if(count($mentions)>0){
                foreach($mentions as $mention){
                    switch($mention['found_in']){
                        case "body":
                            if($mention['timestamp'] >= $this->startDate && $mention['timestamp'] <= $this->endDate){
                                $postMentionsScoreActive += $_ENV['MENTIONS_BODY_WEIGHT'];
                            } else {
                                $postMentionsScoreHistoric += $_ENV['MENTIONS_BODY_WEIGHT']/2;
                            }
                            break;
                        case "title":
                            if($mention['timestamp'] >= $this->startDate && $mention['timestamp'] <= $this->endDate){
                                $postMentionsScoreActive += $_ENV['MENTIONS_TITLE_WEIGHT'];
                            } else {
                                $postMentionsScoreHistoric += $_ENV['MENTIONS_TITLE_WEIGHT']/2;
                            }
                            break;
                        case "summary":
                            if($mention['timestamp'] >= $this->startDate && $mention['timestamp'] <= $this->endDate){
                                $postMentionsScoreActive += $_ENV['MENTIONS_SUMMARY_WEIGHT'];
                            } else {
                                $postMentionsScoreHistoric += $_ENV['MENTIONS_SUMMARY_WEIGHT']/2;
                            }
                            break;
                        case "tags":
                            if($mention['timestamp'] >= $this->startDate && $mention['timestamp'] <= $this->endDate){
                                $postMentionsScoreActive += $_ENV['MENTIONS_TAGS_WEIGHT'];
                            } else {
                                $postMentionsScoreHistoric += $_ENV['MENTIONS_TAGS_WEIGHT']/2;
                            }
                    }
                }
            }

        }

        //////////////////////////////
        /// VIEWS SCORE
        //////////////////////////////

        $viewsScoreActive = 0;
        $viewsScoreHistoric = 0;
        $viewsQuery = 'SELECT Timestamp,ViewCount FROM hits WHERE Action = ? AND EntityId = ?';
        $views = query($viewsQuery,['label_view',$label['Id']]);
        if($views){
            foreach($views as $view){
                if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                    $viewsScoreActive += $view['ViewCount'];
                } else {
                    $viewsScoreHistoric += $view['ViewCount'];
                }
            }
        }

        // POST VIEWS
        $postViewsScoreActive = 0;
        $postViewsScoreHistoric = 0;
        $postsScoreActive = 0;
        $postsScoreHistoric = 0;
        foreach($posts as $post){
            if($post['Author'] == $label['Id']){
                if($post['PublishedDate']>= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                    $postsScoreActive += $_ENV['LABEL_POST_COUNT_WEIGHT'];
                } else {
                    $postsScoreHistoric += $_ENV['LABEL_POST_COUNT_WEIGHT']/2;
                }
                // get post views
                $postViews = readMany('hits','EntityId',$post['Id']);
                if($postViews){
                    foreach($postViews as $postView){
                        if($postView['Timestamp'] >= $this->startDate && $postView['Timestamp'] <= $this->endDate){
                            $postViewsScoreActive += $postView['ViewCount'] * $_ENV['LABEL_POST_VIEW_WEIGHT'];
                        } else {
                            $postViewsScoreHistoric += $postView['ViewCount'] * $_ENV['LABEL_POST_VIEW_WEIGHT']/2;
                        }
                    }
                }
            }
        }

        $viewsScoreActive += $postViewsScoreActive;
        $viewsScoreHistoric += $postViewsScoreHistoric;

        $socialScoreActive = 0;
        $socialScoreHistoric = 0;
        if($token){
            $labelSocial = handleSocial($artist);
            if($labelSocial){
                $socialScoreActive = $labelSocial['active'];
                $socialScoreHistoric = $labelSocial['historic'];
            }
        }

        $spinningArtists = [];
        $mentionedArtists = [];
        $videoCountActive = 0;
        $videoCountHistoric = 0;
        $releaseCountActive = 0;
        $releaseCountHistoric = 0;


        foreach($labelArtists as $labelArtist){
            $artistSMRSpins = $this->getSMREntriesByTitle($labelArtist['Title']);
            $artistSpins = $this->getRadioSpinsByTitle($labelArtist['Title']);
            if($artistSpins){
                foreach($artistSpins as $spin){
                    if(!$bornDate){
                        $bornDate = $spin['Timestamp'];
                    } else if($bornDate > $spin['Timestamp']){
                        $bornDate = $spin['Timestamp'];
                    }
                    if(!in_array($labelArtist['Title'],$spinningArtists)){
                        $spinningArtists[]=$labelArtist['Title'];
                    }
                    if($spin['Timestamp']>= $this->startDate && $spin['Timestamp'] <= $this->endDate){
                        $labelTotalSpinsCountActive+=$spin['TWS'];
                    } else {
                        $labelTotalSpinsCountHistoric+=$spin['TWS'];
                    }
                }
            }
            if($artistSMRSpins){
                foreach($artistSMRSpins as $spin){
                    if(!in_array($labelArtist['Title'],$spinningArtists)){
                        // Doesn't exist
                        $spinningArtists[]=$labelArtist['Title'];
                    }
                    if($spin['Timestamp'] >= $this->startDate && $spin['Timestamp'] <= $this->endDate){
                        $labelTotalSpinsCountActive+=$spin['TWS'];
                    } else {
                        $labelTotalSpinsCountHistoric+=$spin['TWS'];
                    }
                }
            }

            $labelPostsActive = [];
            $labelPostsHistoric = [];

            foreach($posts as $post){
                if($post['Author'] != $labelArtist['Id']){
                    // Check Mentions (Artist)
                    if(strpos($post['Body'],$labelArtist['Title']) !== false){
                        if(!in_array($labelArtist['Title'],$mentionedArtists)){
                            $mentionedArtists[]=$labelArtist['Title'];
                        }
                    } else if(strpos($post['Title'],$labelArtist['Title']) !== false){
                        if(!in_array($labelArtist['Title'],$mentionedArtists)){
                            $mentionedArtists[]=$labelArtist['Title'];
                        }
                    } else if(strpos($post['Summary'],$labelArtist['Title']) !== false){
                        if(!in_array($labelArtist['Title'],$mentionedArtists)){
                            $mentionedArtists[]=$labelArtist['Title'];
                        }
                    } else if(strpos($post['Tags'],$labelArtist['Title']) !== false){
                        if(!in_array($labelArtist['Title'],$mentionedArtists)){
                            $mentionedArtists[]=$labelArtist['Title'];
                        }
                    }
                }

            }

            $labelTotalPostsCount = count($labelPostsHistoric + $labelPostsActive);
            $postsCountScoreActive = count($labelPostsActive) * $_ENV['LABEL_POST_COUNT_WEIGHT'];
            $postsCountScoreHistoric = count($labelPostsHistoric) * ($_ENV['LABEL_POST_COUNT_WEIGHT'] / 2);
            $postViewsScoreActive = 0;
            $postViewsScoreHistoric = 0;
            foreach($labelPostsActive as $labelPost){
                // get views for post
                $views = readMany('hits','EntityId',$labelPost);
                if($views){
                    foreach($views as $view){
                        $postViewsScoreActive += $view['ViewCount'] * $_ENV['LABEL_POST_VIEW_WEIGHT'];
                    }
                }
            }
            foreach($labelPostsHistoric as $labelPost){
                // get views for post
                $views = readMany('hits','EntityId',$labelPost);
                if($views){
                    foreach($views as $view){
                        $postViewsScoreHistoric += $view['ViewCount'] * ($_ENV['LABEL_POST_VIEW_WEIGHT']/2);
                    }
                }
            }

            $videos = readMany('videos','ArtistId',$labelArtist['Id']);
            if($videos){
                foreach($videos as $video){
                    if($video['ReleaseDate'] >= $this->startDate && $video['ReleaseDate'] <= $this->endDate){
                        $videoCountActive++;
                    } else {
                        $videoCountHistoric++;
                    }
                }
            }

            $releases = readMany('releases','ArtistId',$labelArtist['Id']);
            if($releases){
                foreach($releases as $release){
                    if($release['ReleaseDate'] >= $this->startDate && $release['ReleaseDate'] <= $this->endDate){
                        $releaseCountActive++;
                    } else {
                        $releaseCountHistoric++;
                    }
                }
            }



        }

        $daysOld = (new DateTime())->diff(new DateTime($bornDate))->days;
        $ageScore = $daysOld * $_ENV['LABEL_AGE_WEIGHT'];
        $spinsScoreActive = $labelTotalSpinsCountActive * $_ENV['LABEL_SPIN_WEIGHT'];
        $spinsScoreHistoric = $labelTotalSpinsCountHistoric * ($_ENV['LABEL_SPIN_WEIGHT']/4);
        $releasesScoreActive = $releaseCountActive * $_ENV['ARTIST_RELEASE_COUNT_WEIGHT'];
        $releasesScoreHistoric = $releaseCountHistoric * ($_ENV['ARTIST_RELEASE_COUNT_WEIGHT']/2);
        $videoScoreActive = $videoCountActive * ($_ENV['ARTIST_VIDEO_COUNT_WEIGHT']);
        $videoScoreHistoric = $videoCountHistoric * ($_ENV['ARTIST_VIDEO_COUNT_WEIGHT']/2);

        if(count($spinningArtists)>0){
            $chartingScore = count($spinningArtists)*$_ENV['LABEL_ARTISTS_TOTAL_CHARTING_WEIGHT'];
            $chartBoost = $chartingScore / count($spinningArtists);
        }

        $booster = 0;
        if(count($mentionedArtists)>0){
            if($postMentionsScoreActive>0 && count($mentionedArtists))
                $booster += $postMentionsScoreActive / count($mentionedArtists);
        }
        if(count($spinningArtists)>0){
            if($chartingScore >0)
                $booster += $chartingScore / count($spinningArtists);
        }

        $reputationScore = round(($booster + $ageScore + $spinsScoreActive + $spinsScoreHistoric + $videoScoreActive + $videoScoreHistoric + $releasesScoreActive + $releasesScoreHistoric) / 1000,2);

        $score = $postsScoreActive+$postMentionsScoreActive+$viewsScoreActive+$socialScoreActive+$videoScoreActive+$releasesScoreActive+$spinsScoreActive+$booster;
        $score += ($postsScoreActive+$postMentionsScoreHistoric+$viewsScoreHistoric+$socialScoreHistoric+$videoScoreHistoric+$spinsScoreHistoric+$releasesScoreHistoric)/4;
        $newRanking = [
            'LabelId'=>$label['Id'],
            'Score' => round($score,2),
            'Artist_Boost_Score' => round($booster,2),
            'SMR_Score_Active' => 0,
            'SMR_Score_Historic' => 0,
            'Post_Mentions_Score_Active' => round($postMentionsScoreActive,2),
            'Post_Mentions_Score_Historic' => round($postMentionsScoreHistoric,2),
            'Views_Score_Active' => round($viewsScoreActive,2),
            'Views_Score_Historic' => round($viewsScoreHistoric,2),
            'Social_Score_Active' => 0,
            'Social_Score_Historic' => 0,
            'Videos_Score_Active' => round($videoScoreActive,2),
            'Videos_Score_Historic' => round($videoScoreHistoric,2),
            'Spins_Score_Active' => round($spinsScoreActive,2),
            'Spins_Score_Historic' => round($spinsScoreHistoric,2),
            'Releases_Score_Active' => round($releasesScoreActive,2),
            'Releases_Score_Historic' => round($releasesScoreHistoric,2),
            'Posts_Score_Active' => round($postsScoreActive,2),
            'Posts_Score_Historic' => round($postsScoreHistoric,2),
            'AgeScore' => round($ageScore,2),
            'ReputationScore' => round($reputationScore,2)
        ];
        return $newRanking;
//    return add('NGNLabelRankings',$newRanking);
    }

    public function backupCurrentRankings(){
        $tempHourlyArtistRankings = browseByDB($this->pdo,'Artists');
        $tempHourlyLabelRankings = browseByDB($this->pdo,'Labels');
        $chartCache = browseByDB($this->pdo,'Cache');

        $q = 'TRUNCATE TABLE Artists';
        queryByDB($this->pdo,$q,[]);
        $q = 'TRUNCATE TABLE Labels';
        queryByDB($this->pdo,$q,[]);
        $q = 'TRUNCATE TABLE Cache';
        queryByDB($this->pdo,$q,[]);

        $checked = 0;
        $backedUp = 0;
        foreach($tempHourlyArtistRankings as $artistRanking){
            $checked++;
            if(addByDB($this->pdo, 'ranking_items', $this->createArtistData($artistRanking))) $backedUp++;
        }
        if($checked !== $backedUp) {
            // Todo: Revert Rankings
            return false;
        }

        $checked = 0;
        $backedUp = 0;
        foreach($tempHourlyLabelRankings as $labelRanking){
            $checked++;
            if(addByDB($this->pdo, 'ranking_items', $this->createLabelData($labelRanking))) $backedUp++;
        }
        if($checked !== $backedUp) {
            // Todo: Revert Rankings
            return false;
        }

        return true;

    }

    public function updateRankings($artistResults,$labelResults){
        foreach($artistResults as $result) addByDB($this->pdo,'Artists', $result);
        foreach($labelResults as $result) addByDB($this->pdo,'Labels', $result);
        addByDB($this->pdo,'Cache',[]);
        return true;
    }

    /////////////
    // HELPERS //
    /////////////

    private function createArtistData($value){

        return [
            'ArtistId'=>$value['ArtistId'],
            'Score'=>$value['Score'],
            'SMR_Score_Active'=>$value['SMR_Score_Active'],
            'SMR_Score_Historic'=>$value['SMR_Score_Historic'],
            'Post_Mentions_Score_Active'=>$value['Post_Mentions_Score_Active'],
            'Post_Mentions_Score_Historic'=>$value['Post_Mentions_Score_Historic'],
            'Views_Score_Active'=>$value['Views_Score_Active'],
            'Views_Score_Historic'=>$value['Views_Score_Historic'],
            'Social_Score_Active'=>$value['Social_Score_Active'],
            'Social_Score_Historic'=>$value['Social_Score_Historic'],
            'Videos_Score_Active'=>$value['Videos_Score_Active'],
            'Videos_Score_Historic'=>$value['Videos_Score_Historic'],
            'Spins_Score_Active'=>$value['Spins_Score_Active'],
            'Spins_Score_Historic'=>$value['Spins_Score_Historic'],
            'Label_Boost_Score'=>$value['Label_Boost_Score'],
            'Timestamp'=>$value['Timestamp']
        ];
    }

    private function createLabelData($value){

        return [
            'LabelId'=>$value['LabelId'],
            'Score'=>$value['Score'],
            'Post_Mentions_Score_Active'=>$value['Post_Mentions_Score_Active'],
            'Post_Mentions_Score_Historic'=>$value['Post_Mentions_Score_Historic'],
            'Views_Score_Active'=>$value['Views_Score_Active'],
            'Views_Score_Historic'=>$value['Views_Score_Historic'],
            'Social_Score_Active'=>$value['Social_Score_Active'],
            'Social_Score_Historic'=>$value['Social_Score_Historic'],
            'Releases_Score_Active'=>$value['Releases_Score_Active'],
            'Releases_Score_Historic'=>$value['Releases_Score_Historic'],
            'Videos_Score_Active'=>$value['Videos_Score_Active'],
            'Videos_Score_Historic'=>$value['Videos_Score_Historic'],
            'Spins_Score_Active'=>$value['Spins_Score_Active'],
            'Spins_Score_Historic'=>$value['Spins_Score_Historic'],
            'AgeScore'=>$value['AgeScore'],
            'ReputationScore'=>$value['ReputationScore'],
            'Artist_Boost_Score'=>$value['Artist_Boost_Score'],
            'Timestamp'=>$value['Timestamp']

        ];
    }

    private function getSMREntriesByTitle($title){
        $query = 'SELECT Timestamp,TWS from ChartData WHERE LOWER(Artists) LIKE ?';
        $results = queryByDB($this->smr_pdo,$query,['%'.strtolower($title).'%']);
        return $results;
    }

    private function getSMREntriesByLabel($title){
        $query = 'SELECT Artists,Timestamp,TWS from ChartData WHERE LOWER(Label) LIKE ?';
        $results = queryByDB($this->smr_pdo,$query,['%'.strtolower($title).'%']);
        return $results;
    }

    private function getRadioSpinsByTitle($title){
        $query = 'SELECT Timestamp,TWS from SpinData WHERE LOWER(Artist) LIKE ?';
        $results = queryByDB($this->spins_pdo,$query,['%'.strtolower($title).'%']);
        return $results;
    }

    private function getFacebookInsights($artistId,$pageId,$token,$since='',$until=''){

        if($since == '') $since = date('Y-m-d', strtotime('-365 Days'));
        if($until == '') $until = date('Y-m-d');

        $fb = new Facebook();
        $fb->token = $token;
        $fb->pageId = $pageId;
        $requiredScopes = ['read_insights', 'pages_read_engagement'];

        // TODO
        $all_metrics = $fb->fetchAvailableMetrics();

        if ($fb->hasScopes($requiredScopes)) {
            $insights = $fb->getPageInsights('day', date('Y-m-d', strtotime('-365 days')),'', $all_metrics);
            if(is_array($insights)){
                if(count($insights)>0){
                    // cache insights
                    $misc = [];
                    $misc['facebookCache'] = [
                        'Timestamp' => date('Y-m-d H:i:s'),
                        'Insights' => $insights
                    ];
                    $data = [
                        'Misc' => json_encode($misc)
                    ];
                    if(!edit('users',$artistId, $data)) die('Could not store cache');

                    return $insights;
                } else {
                    return false;
                }
            } else {
                die('FACEBOOK METRIC ERROR: '.$insights);
            }
        } else {
            return false;
        }
    }

    private function calculateScores(array $insights, string $metric, string $weightEnvKey, string $since, string $until): array
    {
        $compareStartDate = date('Y-m-d', strtotime('-90 days'));
        $compareEndDate = date('Y-m-d');
        $activeScore = 0;
        $historicScore = 0;
        foreach($insights as $insight){
            if($insight['name'] == $metric){
                $values = $insight['values'];
                $weight = $_ENV[$weightEnvKey];
                foreach ($values as $value) {
                    $endTime = $value['end_time'];
                    // Ensure end_time is valid and calculate scores
                    if ($endTime >= $compareStartDate && $endTime <= $compareEndDate) {
                        $activeScore += $value['value'] * $weight;
                    } else {
                        $historicScore += $value['value'] * ($weight / 2);
                    }
                }
            }
        }


        return ['active' => $activeScore, 'historic' => $historicScore];

    }

    private function getSMRResultsByLabel($title){
        $q = 'SELECT * FROM smr_chart WHERE LOWER(Label) LIKE ?';
        $results = queryByDB($this->smr_pdo, $q,['%'.strtolower($title).'%']);
        return $results;
    }

    private function handleArtistSpinsCount($title, $startDate, $endDate){
        $spinScoreActive = 0;
        $spinScoreHistoric = 0;

        // a.SMR
        $smrEntries = $this->getSMREntriesByTitle($title);
        foreach($smrEntries as $spin){
            if($spin['Timestamp'] >= $this->startDate && $spin['Timestamp'] <= $this->endDate){
                $spinScoreActive += $spin['TWS'] * $_ENV['ARTIST_SPIN_COUNT_WEIGHT'];
            } else {
                $spinScoreHistoric += $spin['TWS'] * ($_ENV['ARTIST_SPIN_COUNT_WEIGHT']/4);
            }
        }
        // b.RadioSpins
        $radioSpins = $this->getRadioSpinsByTitle($title);
        foreach($radioSpins as $spin){
            if($spin['Timestamp'] >= $this->startDate && $spin['Timestamp'] <= $this->endDate){
                $spinScoreActive += $spin['TWS'] * $_ENV['ARTIST_SPIN_COUNT_WEIGHT'];
            } else {
                $spinScoreHistoric += $spin['TWS'] * ($_ENV['ARTIST_SPIN_COUNT_WEIGHT']/4);
            }
        }
        return [
            'active' => $spinScoreActive,
            'historic' => $spinScoreHistoric,
        ];
    }

    private function handleArtistPostMentions($posts,$artist,$startDate,$endDate){
        $mentionScoreActive = 0;
        $mentionScoreHistoric = 0;
        $mentionedPosts = [];
        if(!$posts) die('We need posts to analyze');
        foreach($posts as $post){
            if($post['Author'] !== $artist['Id']){
                // TITLE
                $found = false;
                if (strpos($post['Title'], $artist['Title']) !== false) {
                    $found = true;
                    if($post['PublishedDate'] >= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                        $mentionScoreActive += $_ENV['MENTIONS_TITLE_WEIGHT'];
                    } else {
                        $mentionScoreHistoric += $_ENV['MENTIONS_TITLE_WEIGHT']/2;
                    }
                }
                // TAGS
                if (strpos($post['Tags'], $artist['Title']) !== false) {
                    $found = true;
                    if($post['PublishedDate'] >= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                        $mentionScoreActive += $_ENV['MENTIONS_TAGS_WEIGHT'];
                    } else {
                        $mentionScoreHistoric += $_ENV['MENTIONS_TAGS_WEIGHT']/2;
                    }
                }
                // SUMMARY
                if (strpos($post['Summary'], $artist['Title']) !== false) {
                    $found = true;
                    if($post['PublishedDate'] >= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                        $mentionScoreActive += $_ENV['MENTIONS_SUMMARY_WEIGHT'];
                    } else {
                        $mentionScoreHistoric += $_ENV['MENTIONS_SUMMARY_WEIGHT']/2;
                    }
                }
                // SUMMARY
                if (strpos($post['Body'], $artist['Title']) !== false) {
                    $found = true;
                    if($post['PublishedDate'] >= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                        $mentionScoreActive += $_ENV['MENTIONS_BODY_WEIGHT'];
                    } else {
                        $mentionScoreHistoric += $_ENV['MENTIONS_BODY_WEIGHT']/2;
                    }
                }

                if($found) $mentionedPosts[] = $post['Id'];

            }
        }

        return [
            'active' => $mentionScoreActive,
            'historic' => $mentionScoreHistoric,
            'posts' => $mentionedPosts,
        ];
    }

    private function handleArtistViews($artist,$artistsMentionedPosts,$startDate,$endDate, ){
        $viewsScoreActive = 0;
        $viewsScoreHistoric = 0;

        // 1. Page Views
        $artistPageViewsActive = 0;
        $artistPageViewsHistoric = 0;
        $q = 'SELECT ViewCount,Timestamp FROM hits WHERE EntityId = ? AND Action = ?';
        $artistPageViews = query($q,[$artist['Id'],'artist_view']);
        foreach($artistPageViews as $view){
            if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                $artistPageViewsActive += $view['ViewCount'] * $_ENV['ARTIST_VIEW_WEIGHT'];
            } else {
                $artistPageViewsHistoric += $view['ViewCount'] * ($_ENV['ARTIST_VIEW_WEIGHT']/2);
            }
        }
        $viewsScoreActive += $artistPageViewsActive;
        $viewsScoreHistoric += $artistPageViewsHistoric;

        // 2. Release Views
        $releaseViewsActive = 0;
        $releaseViewsHistoric = 0;
        $q = 'SELECT ReleaseDate,Id FROM releases WHERE ArtistId = ?';
        $releases = query($q,[$artist['Id']]);
        if($releases){
            foreach($releases as $release){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $releaseViews = query($q,[$release['Id'],'release_view']);
                if($releaseViews){
                    foreach($releaseViews as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $releaseViewsActive += $_ENV['ARTIST_RELEASE_VIEW_WEIGHT'];
                        } else {
                            $releaseViewsHistoric += $_ENV['ARTIST_RELEASE_VIEW_WEIGHT']/2;
                        }
                    }
                }
            }
        }
        $viewsScoreActive += $releaseViewsActive;
        $viewsScoreHistoric += $releaseViewsHistoric;

        // 3. Video Views
        $videoViewsActive = 0;
        $videoViewsHistoric = 0;
        $q = 'SELECT ReleaseDate,Id FROM videos WHERE ArtistId = ?';
        $videos = query($q,[$artist['Id']]);
        if($videos){
            foreach($videos as $video){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $videoViews = query($q,[$video['Id'],'video_view']);
                if($videoViews){
                    foreach($videoViews as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $videoViewsActive += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT'];
                        } else {
                            $videoViewsHistoric += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT']/2;
                        }
                    }
                }
            }
        }
        $viewsScoreActive += $videoViewsActive;
        $viewsScoreHistoric += $videoViewsHistoric;

        // 4. Mentioned Post Views
        $mentionedPostViewsScoreActive = 0;
        $mentionedPostViewsScoreHistoric = 0;
        if($artistsMentionedPosts){
            foreach($artistsMentionedPosts as $post){
                $post = read('posts','id',$post);
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $views = query($q,[$post['Id'],'article_view']);
                if($views){
                    foreach($views as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $mentionedPostViewsScoreActive += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT'];
                        } else {
                            $mentionedPostViewsScoreHistoric += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT']/2;
                        }
                    }
                }
            }
        }
        $viewsScoreActive += $mentionedPostViewsScoreActive;
        $viewsScoreHistoric += $mentionedPostViewsScoreHistoric;

        // 5. Posted Post Views
        $postedPostViewsScoreActive = 0;
        $postedPostViewsScoreHistoric = 0;
        $q = 'SELECT PublishedDate,Id FROM posts WHERE Author = ?';
        $posts = query($q,[$artist['Id']]);
        if($posts){
            foreach($posts as $post){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $views = query($q,[$post['Id'],'article_view']);
                if($views){
                    foreach($views as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $postedPostViewsScoreActive += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT'];
                        } else {
                            $postedPostViewsScoreHistoric += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT']/2;
                        }
                    }
                }
            }
        }
        $viewsScoreActive += $postedPostViewsScoreActive;
        $viewsScoreHistoric += $postedPostViewsScoreHistoric;

        return [
            'active' => $viewsScoreActive,
            'historic' => $viewsScoreHistoric
        ];
    }

    public function handleSocial($user){

        $socialScoreActive = 0;
        $socialScoreHistoric = 0;

        // Get token from the database
        $token = read('Tokens','UserId',$user['Id']);

        if($token) {
            $pageId = $token['PageId'];
            $pageAccessToken = $token['PageAccessToken'];
            $igPageId = $token['IGBusinessAccount']; // Instagram Business Account ID

            // Instantiate MetaAnalyticsService instead of Facebook.php
            // Assuming $config is accessible globally or via a singleton like Config::getInstance()
            // If $config is not directly available, it needs to be retrieved.
            // Example: $config = Config::getInstance();
            // For now, assuming access to a $config object. If not, this line will fail and needs adjustment.
            $config = Config::getInstance(); // Placeholder for actual Config retrieval
            $metaAnalyticsService = new MetaAnalyticsService($config);

            // Check token validity/expiry using MetaAnalyticsService
            // isDataAccessExpiringSoon expects the token string.
            $isTokenExpiringSoon = $metaAnalyticsService->isDataAccessExpiringSoon($token['access_token']);

            if (!$isTokenExpiringSoon) {
                // Get the list of metrics and their definitions (including weights) from MetaAnalyticsService
                $metricDefinitions = $metaAnalyticsService->returnFacebookMetricList(); // This method returns metric names with weights

                // Extract just the metric names to fetch
                $metricsToFetch = array_column($metricDefinitions, 'name');

                // Fetch insights for all specified metrics in one API call
                // MetaAnalyticsService::getPageInsights expects metrics as a comma-separated string.
                $metricsParam = implode(',', $metricsToFetch);

                // Fetch insights for the specified page and token.
                // The original code fetched data for -365 days. Adjust period/days if needed for scoring.
                $fetchedInsightsResponse = $metaAnalyticsService->getPageInsights(
                    $pageId,
                    $pageAccessToken,
                    'day', // Period for insights
                    365 // Number of days to look back
                );

                // Process the fetched insights and calculate scores
                if ($fetchedInsightsResponse['success'] && !empty($fetchedInsightsResponse['data']['insights'])) {
                    $allInsightsData = $fetchedInsightsResponse['data']['insights']; // This is an array of metric data, each entry is ['metric' => ..., 'values' => [...]]

                    // Iterate through each metric definition to calculate its score
                    foreach ($metricDefinitions as $metricDef) {
                        $metricName = $metricDef['name'];
                        $metricWeight = $metricDef['weight']; // Use the weight directly from the metric definition

                        // Find the specific metric's data within the fetched insights
                        $specificMetricInsights = null;
                        foreach ($allInsightsData as $insightEntry) {
                            if (isset($insightEntry['metric']) && $insightEntry['metric'] === $metricName) {
                                $specificMetricInsights = $insightEntry;
                                break;
                            }
                        }

                        if ($specificMetricInsights) {
                            // Calculate scores for this metric using the local calculateScores method
                            // Pass the specific metric's insights data, the metric name, and its weight.
                            // The calculateScores method expects the full set of insights to search within, the specific metric name, and the weight.
                            $scores = $this->calculateScores($allInsightsData, $metricName, $metricWeight);

                            $socialScoreActive += $scores['active'];
                            $socialScoreHistoric += $scores['historic'];
                        }
                    }
                } else {
                    // Log error or handle case where insights could not be fetched
                    // error_log("Failed to fetch social insights for user ID: " . $user['Id']);
                }
            } else {
                // Handle token expiry or invalid token case
                // error_log("Facebook token expiring soon or invalid for user ID: " . $user['Id']);
            }
        } else {
            // Handle case where no token is found for the user
            // error_log("No Facebook token found for user ID: " . $user['Id']);
        }

        return [
            'active' => $socialScoreActive,
            'historic' => $socialScoreHistoric,
        ];
    }
    public function handleSocialByTimestamp($user,$timestamp){

        $socialScoreActive = 0;
        $socialScoreHistoric = 0;

        $token = read('Tokens','UserId',$user['Id']);
        if($token) {
//            echo 'We have a token<br>';
            $pageId = $token['PageId'];
            $pageAccessToken = $token['PageAccessToken'];
            $igPageId = $token['IGBusinessAccount'];

            $fb = new Facebook();
            $fb->token = $pageAccessToken;
            $fb->pageAccessToken = $pageAccessToken;
            $fb->facebookPageId = $pageId;
            $fb->instagramPageId = $igPageId;
            $check = $fb->isDataAccessExpiringSoon($token);

            $metricAssociations = [
                'page_post_engagements' => 'FACEBOOK_PAGE_POST_ENGAGEMENTS_WEIGHT',
                'page_lifetime_engaged_followers_unique' => 'FACEBOOK_PAGE_LIFETIME_ENGAGED_FOLLOWERS_UNIQUE_WEIGHT',
                'page_impressions_unique' => 'FACEBOOK_PAGE_IMPRESSIONS_UNIQUE_WEIGHT',
                'page_posts_impressions_unique' => 'FACEBOOK_PAGE_POSTS_IMPRESSIONS_UNIQUE_WEIGHT',
                'post_clicks_by_type' => 'FACEBOOK_POST_CLICKS_BY_TYPE_WEIGHT',
                'post_impressions_unique' => 'FACEBOOK_POST_IMPRESSIONS_UNIQUE_WEIGHT',
                'post_reactions_by_type_total' => 'FACEBOOK_POST_REACTIONS_BY_TYPE_TOTAL_WEIGHT',
                'page_fans' => 'FACEBOOK_PAGE_FANS_WEIGHT',
                'page_video_views_unique' => 'FACEBOOK_PAGE_VIDEO_VIEWS_UNIQUE_WEIGHT',
                'page_video_complete_views_30s_unique' => 'FACEBOOK_PAGE_VIDEO_COMPLETE_VIEWS_30S_UNIQUE_WEIGHT',
                'page_video_views_10s_unique' => 'FACEBOOK_PAGE_VIDEO_VIEWS_10S_UNIQUE_WEIGHT',
                'post_video_views_unique' => 'FACEBOOK_POST_VIDEO_VIEWS_UNIQUE_WEIGHT',
                'post_video_views_10s_unique' => 'FACEBOOK_POST_VIDEO_VIEWS_10S_UNIQUE_WEIGHT',
                'post_activity_by_action_type_unique' => 'FACEBOOK_POST_ACTIVITY_BY_ACTION_TYPE_UNIQUE_WEIGHT'
            ];

            // If facebook token is not expiring soon
            if(!$check) {
//                echo 'Token is not expiring soon<br>';
                $metrics = $fb->getMetrics(); // get list of metrics
                $insightCollection = [];
                // Iterate each metric (fb)
//                echo 'Creating empty insight collection<br>';
//                echo 'Checking metrics:<br>';
                foreach($metrics as $metric){
//                    echo $metric.'<br>';
                    $insights = $fb->getFacebookInsights($metric,'',date('Y-m-d',strtotime($timestamp)));
                    if($insights){
//                        echo 'Found insights for '.$metric.'<br>';
                        $insightCollection[$metric] = $insights['data'];
//                        var_dump($insights['data']);
//                        echo '<hr>';

                        if(!isset($metricAssociations[$metric])) die('Missing weight for ' . $metric.'<br>');
//                        echo 'We have our weight for ' . $metric . '<br>';
                        $scores = $fb->createScoreFromMetrics($insights,$metric,$_ENV[$metricAssociations[$metric]]);
                        $socialScoreActive += $scores['active'];
                        $socialScoreHistoric += $scores['historic'];
                    }
                }
            }
        }

        return [
            'active' => $socialScoreActive,
            'historic' => $socialScoreHistoric,
        ];
    }

    private function handleVideos($artist,$startDate,$endDate){
        $videosScoreActive = 0;
        $videosScoreHistoric = 0;
        $q = 'SELECT ReleaseDate,Id FROM videos WHERE ArtistId = ?';
        $videos = query($q,[$artist['Id']]);
        if($videos){
            foreach($videos as $video){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $views = query($q,[$video['Id'],'video_view']);
                if($views){
                    foreach($views as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $videosScoreActive += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT'];
                        } else {
                            $videosScoreHistoric += $_ENV['ARTIST_VIDEO_VIEW_WEIGHT']/2;
                        }
                    }
                }
                if($video['ReleaseDate'] >= $this->startDate && $video['ReleaseDate'] <= $this->endDate){
                    $videosScoreActive += $_ENV['ARTIST_VIDEO_COUNT_WEIGHT'];
                } else {
                    $videosScoreHistoric += $_ENV['ARTIST_VIDEO_COUNT_WEIGHT']/2;
                }
            }
        }
        return [
            'active' => $videosScoreActive,
            'historic' => $videosScoreHistoric
        ];
    }

    private function handleReleases($artist,$startDate,$endDate){
        $releasesScoreActive = 0;
        $releasesScoreHistoric = 0;

        $q = 'SELECT ReleaseDate,Id FROM releases WHERE ArtistId = ?';
        $releases = query($q,[$artist['Id']]);
        if($releases){
            foreach($releases as $release){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $views = query($q,[$release['Id'],'release_view']);
                if($views){
                    foreach($views as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $releasesScoreActive += $_ENV['ARTIST_RELEASE_VIEW_WEIGHT'];
                        } else {
                            $releasesScoreHistoric += $_ENV['ARTIST_RELEASE_VIEW_WEIGHT']/2;
                        }
                    }
                }
                if($release['ReleaseDate'] >= $this->startDate && $release['ReleaseDate'] <= $this->endDate){
                    $releasesScoreActive += $_ENV['ARTIST_RELEASE_COUNT_WEIGHT'];
                } else {
                    $releasesScoreHistoric += $_ENV['ARTIST_RELEASE_COUNT_WEIGHT']/2;
                }
            }
        }
        return [
            'active' => $releasesScoreActive,
            'historic' => $releasesScoreHistoric
        ];
    }

    private function handlePosts($user,$startDate,$endDate){
        $postsScoreActive = 0;
        $postsScoreHistoric = 0;

        $q = 'SELECT * FROM posts WHERE Author = ?';
        $posts = query($q,[$user['Id']]);
        if($posts){
            foreach($posts as $post){
                $q = 'SELECT * FROM hits WHERE EntityId = ? AND Action = ?';
                $views = query($q,[$post['Id'],'article_view']);
                if($views){
                    foreach($views as $view){
                        if($view['Timestamp'] >= $this->startDate && $view['Timestamp'] <= $this->endDate){
                            $postsScoreActive += $_ENV['ARTIST_POST_VIEW_WEIGHT'];
                        } else {
                            $postsScoreHistoric += $_ENV['ARTIST_POST_VIEW_WEIGHT']/2;
                        }
                    }
                }
                if($post['PublishedDate'] >= $this->startDate && $post['PublishedDate'] <= $this->endDate){
                    $postsScoreActive += $_ENV['LABEL_POST_COUNT_WEIGHT'];
                } else {
                    $postsScoreHistoric += $_ENV['LABEL_POST_COUNT_WEIGHT']/2;
                }
            }
        }
        return [
            'active' => $postsScoreActive,
            'historic' => $postsScoreHistoric
        ];
    }

    private function getLabelBoostAgeReputationFromArtist($artist,$startDate,$endDate){
        $boostScore = 0;
        $ageScore = 0;
        $reputationScore = 0;

        $label = read('users','id',$artist['label_id']);
        if($label){
            // get label boost score

            // Age Score?
            $ageScore = 0;
            $smrEntries = $this->getSMRResultsByLabel($label['Title']);
            if($smrEntries) $smrEntries = sortByColumnIndex($smrEntries,'Timestamp', SORT_ASC);
            $firstEntry = $smrEntries[0];
            $daysOld = 0;
            if (isset($firstEntry['Timestamp'])) {
                $firstEntryDate = new DateTime($firstEntry['Timestamp']);
                $currentDate = new DateTime();
                $daysOld = $currentDate->diff($firstEntryDate)->days;
            }
            $ageScore = $daysOld * $_ENV['LABEL_AGE_WEIGHT'];

            // Reputation Score?
            // How many Artists?
            $labelArtists = readMany('users','LabelId',$label['Id']);
            $labelArtistsCount = count($labelArtists);

            // How many artists charting?
            $chartingCount = 0;
            foreach($labelArtists as $a){
                $q = 'SELECT * FROM smr_chart WHERE LOWER(Artists) LIKE ?';
                $r = queryByDB($this->smr_pdo,$q,['%'.strtolower($a['Title']).'%']);
                if($r) $chartingCount++;
            }



            // Calculate reputation based on these metrics based on how old the label is
            $reputationScore = 0;

            if ($daysOld > 0) {
                $baseReputation = $labelArtistsCount * $_ENV['LABEL_REPUTATION_WEIGHT'];

                // Metrics for charting artists
                $chartingReputation = $chartingCount * $_ENV['LABEL_ARTISTS_TOTAL_CHARTING_WEIGHT'];
                $top10Bonus = ($chartingCount >= 10) ? 100 : 0;
                $top50Bonus = ($chartingCount >= 50) ? 250 : 0;
                $top100Bonus = ($chartingCount >= 100) ? 500 : 0;

                // Age impact multiplier
                $ageImpact = 1 + (log($daysOld, $_ENV['AGE_LOG_BASE']) * $_ENV['AGE_IMPACT_MULTIPLIER']);

                // Final reputation calculation
                $reputationScore = ($baseReputation + $chartingReputation + $top10Bonus + $top50Bonus + $top100Bonus) * $ageImpact;
            }
            // Older the label we expect higher metrics to obtain a good reputation

            $boostScore = ($ageScore/$reputationScore) * $_ENV['LABEL_BOOST_WEIGHT'];

        }

        return [
            'boost' => $boostScore,
            'age' => $ageScore,
            'reputation' => $reputationScore
        ];
    }

}