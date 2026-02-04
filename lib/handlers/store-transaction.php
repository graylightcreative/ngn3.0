<?php

$root = '../';
require $root.'definitions/site-settings.php';
require $root.'controllers/ResponseController.php';

$response = makeResponse();

!isset($_POST['session'])
	? killWithMessage('No session data available', $response)
	: $session = $_POST['session'];
!isset($_POST['email'])
	? killWithMessage('No email available', $response)
	: $email = $_POST['email'];

$data = [
	"session"=>$session,
	"email"=>$email
];
if(!add('Donations',$data)) killWithMessage('Could not add donation to our system', $response);

$response['success'] = true;
$response['code'] = 200;
$response['message'] = 'Donation added successfully';

header('Content-Type: application/json');
echo json_encode($response);