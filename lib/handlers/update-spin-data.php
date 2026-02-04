<?php

$root = $_SERVER['DOCUMENT_ROOT'] .'/';
require $root.'lib/definitions/site-settings.php';

$response = new Response();

$_POST = json_decode(file_get_contents('php://input'), true);

// IF we have a session id then we have a session
if(!isset($_POST)) $response->killWithMessage($response->message = 'No data sent');
$sessionId = $_POST['session_id'] ?? $response->killWithMessage($response->message = 'No session ID');
$userId = $_POST['user_id'] ?? 0;
$songId = $_POST['song_id'] ?? $response->killWithMessage($response->message="No Song ID Provided");
$startTime = $_POST['start'] ?? $response->killWithMessage($response->message="No Spin Start Provided");
$endTime = $_POST['end'] ?? $response->killWithMessage($response->message="No Spin end Provided");
$durationListened = $_POST['duration_listened'] ?? $response->killWithMessage($response->message="No Duration");
$percentagePlayed = $_POST['percentage_played'] ?? $response->killWithMessage($response->message="No % Played");
$skipStatus = $_POST['skip_status'] ?? $response->killWithMessage($response->message="No skipped data provided");
$isRepeat = isset($_POST['repeat']) ? 1 : 0;
$playbackPosition = $_POST['playback_position'] ?? 0;
$platform = $_POST['platform'] ?? 'web';

$deviceType = isset($_SERVER['HTTP_USER_AGENT'])
    ? $response->message = "User Device: " . $_SERVER['HTTP_USER_AGENT']
    : $response->killWithMessage($response->message = "Unable to determine device type");
$ipAddress = getUserIP();
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
$networkType =
    isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? 'Mobile Network'
        : ((strpos($userAgent, 'WiFi') !== false || strpos($userAgent, 'WLAN') !== false)
        ? 'WiFi'
        : 'Ethernet or Unknown');
$connectionSpeed = $_SERVER['HTTP_X_CONNECTION_SPEED'] ?? 'Unknown';
$bufferingTime = $_POST['buffering_time'] ?? $response->killWithMessage($response->message = "No buffering time provided");

$isFullPlay = $percentagePlayed === '100' ? 1 : 0;

$data = [
    'UserId' => $userId,
    'SongId' => $songId,
    'StartTime' => $startTime,
    'EndTime' => $endTime,
    'DurationListened' => $durationListened,
    'PercentagePlayed' => $percentagePlayed,
    'IsFullPlay' => $isFullPlay,
    'SkipStatus' => $skipStatus,
    'IsRepeat' => $isRepeat,
    'PlaybackPosition' => $playbackPosition,
    'Platform' => $platform,
    'DeviceType' => $deviceType,
    'IpAddress' => $ipAddress,
    'NetworkType' => $networkType,
    'ConnectionSpeed' => $connectionSpeed,
    'BufferingTime' => $bufferingTime
];


$spin = read('Spins','SessionId',$sessionId);

if($spin){
    // Update our current spin
    $data['Updated'] = date('Y-m-d H:i:s');
    if(!edit('Spins',$spin['Id'],$data)) $response->killWithMessage($response->message='Could not update spin data');
    $response->success = true;
    $response->code = 200;
    $response->message = 'Spin data updated';
} else {
    // We have to create new spin data
    $data['SessionId'] = $sessionId;
    if(!add('Spins',$data)) $response->killWithMessage($response->message='Could not add spin data');
    $response->success = true;
    $response->code = 200;
    $response->content = $sessionId;
    $response->message = 'Spin data created';
}

echo json_encode($response);





