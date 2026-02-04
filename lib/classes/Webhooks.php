<?php

class WebhookHandler {
    private $facebookAppSecret;
    private $facebookVerifyToken;
    private $instagramVerifyToken; //If you use separate verify tokens

    public function __construct($facebookAppSecret, $facebookVerifyToken, $instagramVerifyToken = null) {
        $this->facebookAppSecret = $facebookAppSecret;
        $this->facebookVerifyToken = $facebookVerifyToken;
        $this->instagramVerifyToken = $instagramVerifyToken;
    }

    public function handleWebhook() {
        $hubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
        $hubMode = $_GET['hub_mode'];
        $hubVerifyToken = $_GET['hub_verify_token'];
        $hubChallenge = $_GET['hub_challenge'];
        $data = file_get_contents('php://input');

        //Verification request
        if ($hubMode === 'subscribe') {

            if($this->instagramVerifyToken && $hubVerifyToken === $this->instagramVerifyToken){
                echo $hubChallenge;
                exit;
            } else if ($hubVerifyToken === $this->facebookVerifyToken){
                echo $hubChallenge;
                exit;
            } else {
                http_response_code(403);
                exit;
            }

        }


        $source = 'unknown';
        if(str_starts_with($hubSignature, 'sha1=')){
            $source = 'facebook';
        } else if(str_starts_with($hubSignature, 'sha256=')) {
            $source = 'instagram';
        }


        if (!$this->verifySignature($data, $hubSignature, $this->facebookAppSecret)) {
            http_response_code(403);
            exit;
        }

        $payload = json_decode($data, true);

        if ($source === 'facebook') {
            $this->processFacebookWebhook($payload);
        } else if ($source === 'instagram') {
            $this->processInstagramWebhook($payload);
        }

        http_response_code(200);
    }



    private function verifySignature($data, $signature, $appSecret) {
        $signature = explode('=', $signature, 2)[1]; //get value after '='
        $expectedSignature = hash_hmac(explode('=', $signature, 2)[0], $data, $appSecret); // Get hashing algo from request
        return hash_equals($signature,$expectedSignature);


    }

    private function processFacebookWebhook($payload) {
        // Example: Handling a 'page' object change
        if (isset($payload['entry'][0]['changes'][0]['value']['page_id'])) {
            $pageId = $payload['entry'][0]['changes'][0]['value']['page_id'];
            $changeType = $payload['entry'][0]['changes'][0]['value']['verb']; // E.g., 'add', 'edit', etc.

            switch ($changeType) {
                case 'add':
                    // Handle page creation/addition
                    error_log("Page added: " . $pageId);
                    break;

                case 'edit':
                    // Handle page edits/updates
                    error_log("Page updated: " . $pageId);
                    break;
                // ... handle other change types as needed
            }

        } else {
            // Handle other Facebook webhook types (e.g., feed, mentions, etc.)
            error_log("Unhandled Facebook Webhook Type:".print_r($payload,true));
        }
    }

    private function processInstagramWebhook($payload) {

        if (isset($payload['entry'][0]['changes'][0]['field']) ) {
            $changedField = $payload['entry'][0]['changes'][0]['field'];

            switch ($changedField) {
                case 'media':
                    // Handle media changes
                    $mediaId = $payload['entry'][0]['changes'][0]['value']['media_id'];
                    error_log("Media changed: " . $mediaId); // Or store, process, etc.
                    break;

                case 'comments':
                    // Handle comment changes
                    error_log("Comment changed"); // Process comment data
                    break;
                // Add more cases for other webhook types as needed...
            }
        }
    }
}

// Example usage:
//$webhookHandler = new WebhookHandler($_ENV['FACEBOOK_APP_SECRET'], $_ENV['FACEBOOK_VERIFY_TOKEN'], $_ENV['INSTAGRAM_VERIFY_TOKEN'] ?? null); // Pass the verify token
//$webhookHandler->handleWebhook();