<?php

declare(strict_types=1);

namespace App\Services;

use NGN\Lib\Config;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client; // Assuming GuzzleHttp is available for API calls

class QrCodeMeService
{
    private LoggerInterface $logger;
    private Config $config;
    private Client $httpClient;

    // me-qr.com API Endpoint and API Key configuration will be loaded from environment variables or config.
    private const MEQR_API_URL = 'https://api.me-qr.com/v1/create';

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
        // Initialize HTTP client. It might need base URI, default headers, etc.
        $this->httpClient = new Client();
    }

    /**
     * Generates a QR code using the me-qr.com API.
     *
     * @param string $url The URL to encode in the QR code.
     * @param array $options Optional parameters for QR code generation (e.g., color, format).
     *                       Refer to me-qr.com API documentation for available options.
     *                       Example options: ['file_format' => 'png', 'color' => '#000000']
     * @return array|null Returns the API response (likely containing qr_image_url) on success, or null on failure.
     */
    public function generateCode(string $url, array $options = []): ?array
    {
        // Retrieve API key from configuration
        $apiKey = $this->config->get('meqr.api_key');

        if (empty($apiKey)) {
            $this->logger->error('MEQR API key is not configured. Cannot generate QR code.');
            return null;
        }

        // Prepare API request parameters
        $apiParams = [
            'text' => $url,
            'file' => $options['file_format'] ?? 'png', // Default to PNG
            'color' => $options['color'] ?? '#000000',   // Default to black
            // Add other me-qr.com API options as needed, e.g., size, margin, etc.
        ];

        try {
            $response = $this->httpClient->request('POST', self::MEQR_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $apiParams,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $responseData = json_decode($body, true);

            if ($statusCode === 200 && $responseData && isset($responseData['url'])) {
                $this->logger->info(sprintf('QR code generated successfully for URL: %s. Image URL: %s', $url, $responseData['url']));
                return $responseData;
            } else {
                $this->logger->error(sprintf('MEQR API error for URL %s. Status: %d, Response: %s', $url, $statusCode, $body));
                return null;
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logger->error(sprintf('MEQR API request failed for URL %s: %s', $url, $e->getMessage()));
            if ($e->hasResponse()) {
                $this->logger->error('MEQR API response body: ' . $e->getResponse()->getBody()->getContents());
            }
            return null;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('An unexpected error occurred during QR code generation for URL %s: %s', $url, $e->getMessage()));
            return null;
        }
    }

    // Potentially add methods for cleaning up QR codes if the API supports it, or handle cleanup via DB jobs.
}
