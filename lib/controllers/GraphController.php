<?php

function getToken($authCode){
	$curlHandler = new CurlHandler();
	$curlHandler->setOption(CURLOPT_URL, 'https://graph.facebook.com/v18.0/oauth/access_token');
	$curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
	$curlHandler->setOption(CURLOPT_POST, true);
	$curlHandler->setOption(CURLOPT_POSTFIELDS, [
		'client_id' => $_ENV['FACEBOOK_APP_ID'],
		'client_secret' => $_ENV['FACEBOOK_APP_SECRET'],
		'code' => $authCode, // Assuming you have the authorization code from the Facebook login redirect
		'redirect_uri' => 'your_redirect_uri' // Replace with your actual redirect URI
	]);

	$response = $curlHandler->execute();
	$accessTokenData = json_decode($response, true);

	// Check for errors
	if (isset($accessTokenData['error'])) {
		throw new \Exception('Error getting access token: ' . $accessTokenData['error']['message']);
	}

	$accessToken = $accessTokenData['access_token'];
	return $accessToken;
}

function authenticateWithFacebook($redirectUri)
{
	// Construct the Facebook Login URL
	$loginUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
			'client_id' => $_ENV['FACEBOOK_APP_ID'],
			'redirect_uri' => $redirectUri,
			'scope' => 'public_profile,email', // Request necessary permissions
			'response_type' => 'code'
		]);

	// Redirect the user to the Facebook Login dialog
	header("Location: $loginUrl");
	exit;
}