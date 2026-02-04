<?php

class Webhook {
    private $facebookAppSecret;
    private $facebookVerifyToken;
    private $instagramVerifyToken; //If you use separate verify tokens

    public function __construct($facebookAppSecret, $facebookVerifyToken, $instagramVerifyToken = null) {
        $this->facebookAppSecret = $_ENV['FACEBOOK_APP_SECRET'];
        $this->facebookVerifyToken = $_ENV['FACEBOOK_VERIFY_TOKEN'];
        $this->instagramVerifyToken = $_ENV['FACEBOOK_VERIFY_TOKEN'];
    }

    public function handleWebhook() {
        // Log the incoming webhook request
        error_log("Incoming webhook request: " . json_encode($_SERVER));

        $hubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
        $hubMode = $_GET['hub_mode'];
        $hubVerifyToken = $_GET['hub_verify_token'];
        $hubChallenge = $_GET['hub_challenge'];
        $data = file_get_contents('php://input');

        // Verification request
        if ($hubMode === 'subscribe') {
            if (($this->instagramVerifyToken && $hubVerifyToken === $this->instagramVerifyToken) ||
                ($hubVerifyToken === $this->facebookVerifyToken)) {
                echo $hubChallenge;
                exit;
            } else {
                http_response_code(403);
                exit;
            }
        }

        $source = 'unknown';
        if (str_starts_with($hubSignature, 'sha1=')) {
            $source = 'facebook';
        } else if (str_starts_with($hubSignature, 'sha256=')) {
            $source = 'instagram';
        }

        // Verify signature based on source
        if ($source === 'facebook' && !$this->verifySignature($data, $hubSignature, $this->facebookAppSecret)) {
            http_response_code(403);
            exit;
        } else if ($source === 'instagram' && !$this->verifySignature($data, $hubSignature, $this->instagramAppSecret, 'sha256')) {
            http_response_code(403);
            exit;
        }

        $payload = json_decode($data, true);

        // Validate payload (add your validation logic here)
        if (!$this->validatePayload($payload)) {
            error_log("Invalid webhook payload: " . json_encode($payload));
            http_response_code(400); // Bad Request
            exit;
        }

        try {
            if ($source === 'facebook') {
                $this->processFacebookWebhook($payload);
            } else if ($source === 'instagram') {
                $this->processInstagramWebhook($payload);
            }
        } catch (Exception $e) {
            error_log("Webhook processing error: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            exit;
        }

        http_response_code(200);
    }
    private function validatePayload($payload) {
        // Basic validation for Facebook webhooks
        if (isset($payload['object']) && $payload['object'] === 'page') {
            if (isset($payload['entry']) && is_array($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    if (isset($entry['id']) && isset($entry['time']) && isset($entry['changes']) && is_array($entry['changes'])) {
                        // Further validation can be added here for specific changes
                        return true;
                    }
                }
            }
        }

        // Basic validation for Instagram webhooks
        if (isset($payload['object']) && $payload['object'] === 'instagram') {
            if (isset($payload['entry']) && is_array($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    if (isset($entry['id']) && isset($entry['time']) && isset($entry['changes']) && is_array($entry['changes'])) {
                        // Further validation can be added here for specific changes
                        return true;
                    }
                }
            }
        }

        // Add more validation rules for other webhook types or data structures as needed

        return false; // Invalid payload if none of the conditions are met
    }
    private function verifySignature($data, $signature, $appSecret, $algo = 'sha1') {
        $signature = explode('=', $signature, 2)[1];
        $expectedSignature = hash_hmac($algo, $data, $appSecret);
        return hash_equals($signature, $expectedSignature);
    }

    private function processFacebookWebhook($payload) {
        foreach ($payload['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                if (!isset($change['value']['page_id'])) {
                    continue; // Skip if not page-related
                }

                $pageId = $change['value']['page_id'];
                $changeType = $change['value']['verb'];

                switch ($changeType) {
                    case 'add':
                        // ... handle page item creation
                        break;
                    case 'edit':
                        // ... handle page item update
                        break;
                    case 'remove':
                        // ... handle page item removal
                        break;
                    // ... handle other page-related change types
                }
            }
        }
    }
    private function processInstagramWebhook($payload) {
        if (isset($payload['entry'][0]['changes'][0]['field'])) {
            $changedField = $payload['entry'][0]['changes'][0]['field'];

            switch ($changedField) {
                case 'media':
                    // ... handle media changes
                    break;
                case 'comments':
                    // ... handle comment changes
                    break;
                // ... add more cases for other webhook types
            }
        }
    }}

