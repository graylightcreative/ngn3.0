<?php

class MailChimpApi {

	protected $apiKey;
	protected $serverPrefix;
	protected $lastResponse;
	protected $lastRequest;

	public function __construct() {
		// Initialize properties to null initially
		$this->apiKey = null;
		$this->serverPrefix = null;
		$this->lastResponse = null;
		$this->lastRequest = null;
	}

	// Main method to handle API requests using Guzzle and CurlHandler
	protected function makeRequest($method, $endpoint, $data = []) {
		// Initialize a CurlHandler instance
		$curl = new CurlHandler();

		// Set cURL options
		$curl->setOption(CURLOPT_URL, "https://{$this->serverPrefix}.api.mailchimp.com/3.0/$endpoint");
		$curl->setOption(CURLOPT_RETURNTRANSFER, true);
		$curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
		$curl->setOption(CURLOPT_HTTPHEADER, [
			'Authorization: apikey ' . $this->apiKey,
			'Content-Type: application/json'
		]);

		if (!empty($data)) {
			$curl->setOption(CURLOPT_POSTFIELDS, json_encode($data));
		}

		// Execute the request and store the response
		$response = $curl->execute();
		$this->lastResponse = $response;

		// Store the last request details
		$this->lastRequest = [
			'method' => $method,
			'endpoint' => $endpoint,
			'data' => $data
		];

		// Check for cURL errors
		if ($curl->getErrorCode() !== 0) {
			throw new \Exception('cURL Error: ' . $curl->getError());
		}

		// Decode the JSON response
		$decodedResponse = json_decode($response, true);

		// Check for Mailchimp API errors
		if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
			throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
		}

		return $decodedResponse;
	}
	protected function handleErrors($exception)
	{
		// Log the error (adjust based on your logging mechanism)
		error_log('Mailchimp API Error: ' . $exception->getMessage());

		// Attempt to extract a more user-friendly error message
		$errorMessage = 'An error occurred while communicating with Mailchimp. Please try again later.';
		if ($exception instanceof \GuzzleHttp\Exception\ClientException) {
			$response = $exception->getResponse();
			if ($response && $response->getStatusCode() === 400) { // Bad Request
				$responseBody = json_decode($response->getBody(), true);
				if (isset($responseBody['detail'])) {
					$errorMessage = $responseBody['detail'];
				}
			} else if ($response && $response->getStatusCode() === 401) { // Unauthorized
				$errorMessage = 'Invalid Mailchimp API key. Please check your configuration.';
			}
		}

		// Throw a custom exception with the formatted error message
		throw new MailchimpApiException($errorMessage);
	}

}