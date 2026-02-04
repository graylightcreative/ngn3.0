<?php

$root = '../';
require $root.'definitions/site-settings.php';
require $root.'controllers/ResponseController.php';
require $root.'controllers/GraphController.php';

$response = makeResponse();

!isset($_POST['return'])
	? killWithMessage('there was an issue with your request. Please try again', $response)
	: $return = $_POST['return'];

$redirectUri = $GLOBALS['Default']['Baseurl'] .'facebook-login-return.php?r='.urlencode($return);