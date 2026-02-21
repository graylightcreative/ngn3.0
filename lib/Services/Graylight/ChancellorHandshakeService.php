<?php
namespace NGN\Lib\Services\Graylight;

/**
 * Chancellor Handshake Service
 * Orchestrates secure transaction authorization with the Graylight Mothership.
 * Bible Ref: BFL 3.2 // Private Stripe App Alignment
 */

use NGN\Lib\Config;
use NGN\Lib\Env;

class ChancellorHandshakeService
{
    private $config;
    private $apiKey;
    private $secretKey;
    private $chancellorUrl;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->apiKey = Env::get('GL_API_KEY', '');
        $this->secretKey = Env::get('GL_SECRET_KEY', '');
        $this->chancellorUrl = 'https://graylightcreative.com/v1/chancellor/authorize-checkout';
    }

    /**
     * Authorize a checkout session via the Chancellor Node
     */
    public function authorizeCheckout(array $payload): array
    {
        // 1. Prepare Payload with Mandatory tenant_id and Sovereign Stripe Keys
        if (!isset($payload['metadata'])) $payload['metadata'] = [];
        $payload['metadata']['tenant_id'] = 23; // NextGenNoise Fixed ID

        // Attach Custom Sovereign Stripe Keys for BYOK processing
        $payload['stripe_config'] = [
            'secret_key' => Env::get('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => Env::get('STRIPE_WEBHOOK_SECRET', ''),
            'publishable_key' => Env::get('STRIPE_PUBLISHABLE_KEY', '')
        ];

        $jsonPayload = json_encode($payload);
        $timestamp = time();

        // 2. Generate HMAC Signature
        $signature = hash_hmac('sha256', $timestamp . $jsonPayload, $this->secretKey);

        // 3. Dispatch to Chancellor
        $ch = curl_init($this->chancellorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-GL-API-KEY: ' . $this->apiKey,
            'X-GL-TIMESTAMP: ' . $timestamp,
            'X-GL-SIGNATURE: ' . $signature
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            error_log("Chancellor Error ({$httpCode}): " . ($data['error'] ?? 'Unknown error'));
            return ['success' => false, 'error' => $data['error'] ?? 'Handshake failed'];
        }

        return $data;
    }
}
