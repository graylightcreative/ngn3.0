<?php
namespace NGN\Lib\Services\Social;

/**
 * Vent Notification Service
 * Orchestrates fleet-wide transactional emails via the Vent Node.
 * Bible Ref: Chapter 22 // FLEET.md Section 2
 */

use NGN\Lib\Config;
use NGN\Lib\Env;

class VentNotificationService
{
    private $config;
    private $ventUrl;
    private $apiKey;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->ventUrl = 'https://vent.graylightcreative.com/v1/vent/relay';
        $this->apiKey = Env::get('GL_API_KEY', '');
    }

    /**
     * Dispatch a transactional email through the fleet relay
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        $payload = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'tenant_id' => 23, // NGN
            'template' => $options['template'] ?? 'transactional',
            'vars' => $options['vars'] ?? []
        ];

        $ch = curl_init($this->ventUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-GL-API-KEY: ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            error_log("Vent Relay Error ({$httpCode}): " . ($data['error'] ?? 'Handshake failed'));
            return ['success' => false, 'error' => $data['error'] ?? 'Relay failed'];
        }

        return ['success' => true, 'receipt' => $data['receipt_id'] ?? null];
    }

    /**
     * Specialized: Send Verification Email
     */
    public function sendVerification(string $email, string $token): bool
    {
        $url = Env::get('BASEURL') . "/verify?token=" . $token;
        $subject = "Verify Your NextGenNoise Profile";
        $body = "Welcome to the Fleet. Click here to verify your account: {$url}";
        
        $res = $this->send($email, $subject, $body, [
            'template' => 'user_verification',
            'vars' => ['verification_url' => $url]
        ]);

        return $res['success'];
    }
}
