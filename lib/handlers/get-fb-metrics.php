<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root.'lib/definitions/site-settings.php';

$response = new Response();


if(!isset($_SESSION['User'])) $response->killWithMessage($response->message="No session data available");
$user = read('users','Id',$_SESSION['User']['Id']);
if(!$user) $response->killWithMessage($response->message="Could not find user");
$fbPageToken = read('Tokens','UserId',$user['Id']);
if(!$fbPageToken) $response->killWithMessage($response->message="This user does not have a valid page token");

if($fbPageToken){
    $fb = new Facebook();
    $fb->token = $fbPageToken['Token'];
    $fb->pageId = $fbPageToken['PageId'];
    $requiredScopes = ['read_insights', 'pages_read_engagement'];

    $all_metrics = [
        'page_fan_adds',
        'page_video_views',
        'page_impressions',
        'page_engaged_users',
        'page_consumptions',
        'page_positive_feedback',
        'page_negative_feedback',
        'page_total_actions',
        'page_views_total',
        'page_post_engagements',
        'page_reactions_anger_total',
        'page_reactions_love_total',
        'page_reactions_wow_total',
        'page_reactions_haha_total',
        'page_reactions_sorry_total',
        'page_reactions_like_total',
        'page_fans_online',
        'page_fans_by_country',
        'page_fans_by_city',
        'page_fans_gender_age',
        'page_posts_impressions',
        'page_posts_impressions_organic',
        'page_posts_impressions_paid'
    ];
    $all_metrics = $fb->fetchAvailableMetrics($all_metrics);


    if ($fb->hasScopes($requiredScopes)) {
        $insights = $fb->getPageInsights('day', date('Y-m-d', strtotime('-365 days')),'',$all_metrics);
//        $lifetime_insights = $fb->getPageInsights('lifetime', null, null, ['page_fan_adds', 'page_video_views']);
//        $weekly_insights = $fb->getPageInsights('week', '2024-12-01', '2024-12-18',['page_fan_adds', 'page_video_views']);
//        $monthly_insight = $fb->getPageInsights('month', '2024-12-01', '2024-12-18',['page_fan_adds', 'page_video_views']);
// Print the insights data
        echo json_encode($insights);
//        print_r($lifetime_insights);
//        print_r($weekly_insights);

    } else {
        $response->killWithMessage($response->message="Token does not have the require scopes");
    }
}


