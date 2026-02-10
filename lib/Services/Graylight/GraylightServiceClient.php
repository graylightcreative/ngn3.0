<?php

namespace NGN\Lib\Services\Graylight;

use NGN\Lib\Config;
use Exception;

/**
 * GraylightServiceClient
 *
 * Bridge to the Graylight Sovereign API. 
 * Handles HMAC signing and HTTP communication.
 */
class GraylightServiceClient
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Call a Graylight Sovereign API endpoint
     *
     * @param string $endpoint The API endpoint (e.g., 'auth/verify')
     * @param array $payload The JSON payload to send
     * @return array The decoded JSON response
     * @throws Exception
     */
    public function call(string $endpoint, array $payload = []): array
    {
        $baseUrl = $this->config->glBaseUrl();
        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        $timestamp = time();
        $jsonPayload = json_encode($payload);
        
        // HMAC SHA-256: Payload + Timestamp signed with Secret Key
        $signature = hash_hmac('sha256', $jsonPayload . $timestamp, $this->config->glSecretKey());

        $headers = [
            'X-GL-API-KEY: ' . $this->config->glApiKey(),
            'X-GL-TIMESTAMP: ' . $timestamp,
            'X-GL-SIGNATURE: ' . $signature,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NGN-Tenant-Client/2.0.3');
        curl_setopt($ch, CURLOPT_HEADER, true); // We need headers for rate limit check

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerContent = substr($response, 0, $headerSize);
        $bodyContent = substr($response, $headerSize);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        // Parse headers for rate limiting
        $remaining = $this->parseRateLimit($headerContent);
        if ($remaining !== null && $remaining < 5) {
            $backoff = (5 - $remaining) * 2;
            error_log("Graylight API Rate Limit Warning: {$remaining} remaining. Backing off for {$backoff}s.");
            sleep($backoff);
        }

        if ($httpCode === 403) {
            throw new Exception("ACCESS_REVOKED: Tenant is suspended or signature invalid.");
        }

        if ($response === false) {
            throw new Exception("Graylight API Connection Error: " . $error);
        }

        $decoded = json_decode($bodyContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Graylight API returned invalid JSON: " . $bodyContent);
        }

        return $decoded;
    }

    private function parseRateLimit(string $headers): ?int
    {
        if (preg_match('/X-RateLimit-Remaining:\s*(\d+)/i', $headers, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
}
