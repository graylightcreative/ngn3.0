<?php
// Todo: Replace credentials with client credentials

function getToken(){
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://api.usps.com/oauth2/v3/token',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'{
    "client_id": "I37DnR0QBPL3kAGL8DDyeZhmMUQLg9Tm",
    "client_secret": "uA0r3FspZdUbSsVf",
    "grant_type": "client_credentials",
    "scope": "addresses prices labels tracking"
}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		),
	));

	$response = curl_exec($curl);
	curl_close($curl);

	$response = json_decode($response, true);
	return $response['access_token'];
}