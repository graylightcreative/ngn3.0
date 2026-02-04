<?php

class MailchimpMarketing extends MailchimpApi {

    public function addListMember($audienceId, $memberData) {
        $url = "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/lists/{$audienceId}/members/";
        return $this->makeRequest($url, 'POST', $memberData);
    }
	public function updateListMember($listId, $subscriberHash, $memberData, CurlHandler $curlHandler) {
		// Set up the API endpoint
		$endpoint = "lists/$listId/members/$subscriberHash";

		// Prepare the request data (only include fields that need updating)
		$requestData = [];
		if (isset($memberData['email_address'])) {
			$requestData['email_address'] = $memberData['email_address'];
		}
		if (isset($memberData['status'])) {
			$requestData['status'] = $memberData['status'];
		}
		if (isset($memberData['merge_fields'])) {
			$requestData['merge_fields'] = $memberData['merge_fields'];
		}
		// Add other fields as needed based on your requirements

		// Use the CurlHandler to make the request
		$curlHandler->setOption(CURLOPT_URL, "https://{$this->serverPrefix}.api.mailchimp.com/3.0/$endpoint");
		$curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
		$curlHandler->setOption(CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH method
		$curlHandler->setOption(CURLOPT_POSTFIELDS, json_encode($requestData));
		$curlHandler->setOption(CURLOPT_HTTPHEADER, [
			'Authorization: apikey ' . $this->apiKey,
			'Content-Type: application/json'
		]);

		$response = $curlHandler->execute();
		$this->lastResponse = $response;

		$this->lastRequest = [
			'method' => 'PATCH',
			'endpoint' => $endpoint,
			'data' => $requestData
		];

		// Check for cURL errors
		if ($curlHandler->getErrorCode() !== 0) {
			throw new \Exception('cURL Error: ' . $curlHandler->getError());
		}

		// Decode the JSON response
		$decodedResponse = json_decode($response, true);

		// Check for Mailchimp API errors
		if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
			throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
		}

		return $decodedResponse;
	}

    public function getAllListMembers($listId, CurlHandler $curlHandler)
    {
        // Set up the API endpoint
        $endpoint = "lists/$listId/members";

        // Use the CurlHandler to make the request
        $curlHandler->setOption(CURLOPT_URL, "https://{$this->serverPrefix}.api.mailchimp.com/3.0/$endpoint");
        $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
        $curlHandler->setOption(CURLOPT_HTTPHEADER, [
            'Authorization: apikey ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        $response = $curlHandler->execute();
        $this->lastResponse = $response;

        $this->lastRequest = [
            'method' => 'GET',
            'endpoint' => $endpoint,
            'data' => []
        ];

        // Check for cURL errors
        if ($curlHandler->getErrorCode() !== 0) {
            throw new \Exception('cURL Error: ' . $curlHandler->getError());
        }

        // Decode the JSON response
        $decodedResponse = json_decode($response, true);

        // Check for Mailchimp API errors
        if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
        }

        return $decodedResponse;
    }




    public function createAndSendCampaign($listId, $campaignData, CurlHandler $curlHandler)
    {
        // Fetch all list members
        $members = $this->getAllAudienceMembers($listId, $curlHandler);

        // Create a new campaign
        $campaignEndpoint = "campaigns";
        $campaignPayload = [
            'type' => 'regular',
            'recipients' => [
                'list_id' => $listId
            ],
            'settings' => $campaignData['settings']
        ];

        // Use the CurlHandler to create the campaign
        $curlHandler->setOption(CURLOPT_URL, "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/$campaignEndpoint");
        $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
        $curlHandler->setOption(CURLOPT_POST, true);
        $curlHandler->setOption(CURLOPT_POSTFIELDS, json_encode($campaignPayload));
        $curlHandler->setOption(CURLOPT_HTTPHEADER, [
            'Authorization: apikey ' . $_ENV['MAILCHIMP_KEY'],
            'Content-Type: application/json'
        ]);

        $response = $curlHandler->execute();

        // Check for cURL errors
        if ($curlHandler->getErrorCode() !== 0) {
            throw new \Exception('cURL Error: ' . $curlHandler->getError());
        }

        // Decode the JSON response
        $decodedCampaignResponse = json_decode($response, true);

        // Check for Mailchimp API errors
        if (isset($decodedCampaignResponse['status']) && $decodedCampaignResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error: ' . $decodedCampaignResponse['detail']);
        }

        // Send the campaign
        $campaignId = $decodedCampaignResponse['id'];
        $sendEndpoint = "campaigns/$campaignId/actions/send";

        $curlHandler->setOption(CURLOPT_URL, "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/$sendEndpoint");
        $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
        $curlHandler->setOption(CURLOPT_POST, true);
        $curlHandler->setOption(CURLOPT_HTTPHEADER, [
            'Authorization: apikey ' . $_ENV['MAILCHIMP_KEY'],
            'Content-Type: application/json'
        ]);

        $response = $curlHandler->execute();

        if ($curlHandler->getErrorCode() !== 0) {
            throw new \Exception('cURL Error: ' . $curlHandler->getError());
        }

        $decodedSendResponse = json_decode($response, true);

        if (isset($decodedSendResponse['status']) && $decodedSendResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error: ' . $decodedSendResponse['detail']);
        }

        return $decodedSendResponse;
    }

    ///////////////
    /// AUDIENCE
    ///////////////


    public function sendEmailToSegmentById($listId, $segmentId, $campaignData)
    {
        $campaignEndpoint = "campaigns";
        $campaignPayload = [
            'type' => 'regular',
            'recipients' => [
                'list_id' => $listId,
                'segment_opts' => [
                    'saved_segment_id' => $segmentId
                ]
            ],
            'settings' => $campaignData['settings'],
        ];

        // Create the campaign
        $response = $this->makeRequest("https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/$campaignEndpoint", 'POST', $campaignPayload);

        if (isset($response['status']) && $response['status'] === 'error') {
            throw new \Exception('Mailchimp API Error (Create Campaign): ' . ($response['detail'] ?? 'Unknown error'));
        }

        $campaignId = $response['id'] ?? null;
        if (!$campaignId) {
            throw new \Exception('Mailchimp API Error: Campaign ID could not be retrieved.');
        }

        // Prepare the content for the campaign
        // Use Mailchimp's `/campaigns/{campaign_id}/content` API endpoint
        $contentEndpoint = "campaigns/$campaignId/content";
        $contentPayload = [
            'html' => $campaignData['content']['html']
        ];

        $contentResponse = $this->makeRequest("https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/$contentEndpoint", 'PUT', $contentPayload);

        if (isset($contentResponse['status']) && $contentResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error (Add Content): ' . ($contentResponse['detail'] ?? 'Unknown error'));
        }

        // Send the campaign
        $sendEndpoint = "campaigns/$campaignId/actions/send";
        $sendResponse = $this->makeRequest("https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/$sendEndpoint", 'POST');

        if (isset($sendResponse['status']) && $sendResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error (Send Campaign): ' . ($sendResponse['detail'] ?? 'Unknown error'));
        }

        return ['status' => 'success', 'message' => 'Campaign has been sent successfully.'];
    }
    public function addAudienceMember($audienceId, $memberData) {
        $url = "https://us12.api.mailchimp.com/3.0/lists/{$audienceId}/members/";

        return $this->makeRequest($url, 'POST', $memberData);

    }
    public function updateAudienceMember($listId, $subscriberHash, $memberData, CurlHandler $curlHandler) {
        // Set up the API endpoint
        $endpoint = "lists/$listId/members/$subscriberHash";

        // Prepare the request data (only include fields that need updating)
        $requestData = [];
        if (isset($memberData['email_address'])) {
            $requestData['email_address'] = $memberData['email_address'];
        }
        if (isset($memberData['status'])) {
            $requestData['status'] = $memberData['status'];
        }
        if (isset($memberData['merge_fields'])) {
            $requestData['merge_fields'] = $memberData['merge_fields'];
        }
        // Add other fields as needed based on your requirements

        // Use the CurlHandler to make the request
        $curlHandler->setOption(CURLOPT_URL, "https://{$this->serverPrefix}.api.mailchimp.com/3.0/$endpoint");
        $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);
        $curlHandler->setOption(CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH method
        $curlHandler->setOption(CURLOPT_POSTFIELDS, json_encode($requestData));
        $curlHandler->setOption(CURLOPT_HTTPHEADER, [
            'Authorization: apikey ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        $response = $curlHandler->execute();
        $this->lastResponse = $response;

        $this->lastRequest = [
            'method' => 'PATCH',
            'endpoint' => $endpoint,
            'data' => $requestData
        ];

        // Check for cURL errors
        if ($curlHandler->getErrorCode() !== 0) {
            throw new \Exception('cURL Error: ' . $curlHandler->getError());
        }

        // Decode the JSON response
        $decodedResponse = json_decode($response, true);

        // Check for Mailchimp API errors
        if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
        }

        return $decodedResponse;
    }

    public function getAllAudienceMembers($listId)
    {
        // Set up the API endpoint
        $endpoint = "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/lists/$listId/members";

        // Use the makeRequest method to make the request
        $response = $this->makeRequest($endpoint, 'GET');
        return $response;
        // Decode the JSON response
        $decodedResponse = json_decode($response, true);

        // Check for Mailchimp API errors
        if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
            throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
        }

        return $decodedResponse;
    }


    public function getAudienceSegments($listId)
    {
        // Set up the API endpoint
        $endpoint = "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/lists/$listId/segments";

        // Create a Guzzle HTTP client
        $client = new \GuzzleHttp\Client();

        try {
            // Send the GET request
            $response = $client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'apikey ' . $_ENV['MAILCHIMP_KEY'],
                    'Content-Type' => 'application/json',
                ],
            ]);

            // Decode the JSON response
            $decodedResponse = json_decode($response->getBody()->getContents(), true);

            // Check for Mailchimp API errors
            if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
                throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
            }

            // Return the decoded response (list of segments)
            return $decodedResponse;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Catch and handle HTTP exceptions
            throw new \Exception('HTTP Request Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Catch other exceptions
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function getAvailableAudiences()
    {
        // Set up the API endpoint
        $endpoint = "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/lists";

        // Create a Guzzle HTTP client
        $client = new \GuzzleHttp\Client();

        try {
            // Send the GET request
            $response = $client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'apikey ' . $_ENV['MAILCHIMP_KEY'],
                    'Content-Type' => 'application/json',
                ],
            ]);

            // Decode the JSON response
            $decodedResponse = json_decode($response->getBody()->getContents(), true);

            // Check for Mailchimp API errors
            if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
                throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
            }

            // Return the decoded response (list of audiences)
            return $decodedResponse;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Catch and handle HTTP exceptions
            throw new \Exception('HTTP Request Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Catch other exceptions
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }
    public function createAudience(array $audienceData)
    {
        // Set up the API endpoint
        $endpoint = "https://{$_ENV['MAILCHIMP_SERVER_PREFIX']}.api.mailchimp.com/3.0/lists";

        // Create a Guzzle HTTP client
        $client = new \GuzzleHttp\Client();

        try {
            // Send the POST request
            $response = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'apikey ' . $_ENV['MAILCHIMP_KEY'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $audienceData, // Automatically encodes the data to JSON
            ]);

            // Save the last response and request details
            $this->lastResponse = $response->getBody()->getContents();
            $this->lastRequest = [
                'method' => 'POST',
                'endpoint' => $endpoint,
                'data' => $audienceData,
            ];

            // Decode the JSON response
            $decodedResponse = json_decode($this->lastResponse, true);

            // Check for Mailchimp API errors
            if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
                throw new \Exception('Mailchimp API Error: ' . $decodedResponse['detail']);
            }

            // Return the decoded response
            return $decodedResponse;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Catch and handle HTTP exceptions
            throw new \Exception('HTTP Request Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Catch other exceptions
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    ///////////////
    /// WEBHOOKS
    ///////////////
    public function handleWebhook(array $webhookData)
    {
        // Log the webhook request for debugging purposes (optional)
        $this->lastRequest = [
            'method' => 'POST',
            'endpoint' => 'Webhook',
            'data' => $webhookData,
        ];

        // Validate the webhook data to ensure it contains the necessary fields
        if (!isset($webhookData['type']) || !isset($webhookData['data'])) {
            throw new \Exception('Invalid webhook payload: Missing type or data');
        }

        // Handle different webhook event types
        switch ($webhookData['type']) {
            case 'subscribe':
                return $this->handleSubscribeEvent($webhookData['data']);
            case 'unsubscribe':
                return $this->handleUnsubscribeEvent($webhookData['data']);
            case 'update':
                return $this->handleUpdateEvent($webhookData['data']);
            case 'cleaned':
                return $this->handleCleanedEvent($webhookData['data']);
            default:
                // Log or ignore unknown webhook types
                throw new \Exception('Unknown webhook event type: ' . $webhookData['type']);
        }
    }

    private function handleSubscribeEvent(array $eventData)
    {
        // Logic for handling a "subscribe" event
        $email = $eventData['email'] ?? null;

        if ($email === null) {
            throw new \Exception('Missing email in subscribe event data');
        }

        // Example: Log, notify, or process a new subscriber
        return "Handled subscribe event for email: $email";
    }

    private function handleUnsubscribeEvent(array $eventData)
    {
        // Logic for handling an "unsubscribe" event
        $email = $eventData['email'] ?? null;

        if ($email === null) {
            throw new \Exception('Missing email in unsubscribe event data');
        }

        // Example: Log, notify, or process an unsubscription
        return "Handled unsubscribe event for email: $email";
    }

    private function handleUpdateEvent(array $eventData)
    {
        // Logic for handling an "update" event
        $email = $eventData['email'] ?? null;

        if ($email === null) {
            throw new \Exception('Missing email in update event data');
        }

        // Example: Log, notify, or process an update in member information
        return "Handled update event for email: $email";
    }

    private function handleCleanedEvent(array $eventData)
    {
        // Logic for handling a "cleaned" event
        $email = $eventData['email'] ?? null;

        if ($email === null) {
            throw new \Exception('Missing email in cleaned event data');
        }

        // Example: Log, notify, or process a cleaned email address
        return "Handled cleaned event for email: $email";
    }
    
}