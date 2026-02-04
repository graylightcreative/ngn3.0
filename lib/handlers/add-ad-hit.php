<?php

$root = $_SERVER['DOCUMENT_ROOT'].'/';
require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ListController.php';
$_POST = json_decode(file_get_contents('php://input'), true);

$response = new Response();

!isset($_POST['action'])
	? $response->kill('There was an issue with our action. Please try again')
	: $action = $_POST['action'];
!isset($_POST['current_page'])
	? $response->kill('There was an issue with our URL. Please try again')
	: $url = $_POST['current_page'];
$href= !isset($_POST['href']) ? $response->kill('There was an issue with our URL. Please try again') : $_POST['href'];

// add hit

$conditions = [
	'Id'=>1
];
$advertisement = read('Ads', 'Url', $href);
//$advertisement = $advertisement[0];

//logHit(getCurrentUser(),$action, [
//	'ad_id'=>$advertisement['Id']
//]);

$response->success = true;
$response->code = 200;
$response->message = 'Ad hit tracked successfully';
echo json_encode($response);