<?php

function makeResponse(){
	$response = array();
	$response['message'] = 'An unknown error has occurred';
	$response['code'] = 500;
	$response['success'] = false;
	$response['content'] = '';
	return $response;
}
function killWithMessage($message, $response){
	$response['message'] = $message;
	die(json_encode($response));
}