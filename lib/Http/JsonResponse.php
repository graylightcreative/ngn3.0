<?php

namespace NGN\Lib\Http;

/**
 * JSON Response Helper
 *
 * Provides a convenient way to return JSON responses from API endpoints
 */
class JsonResponse
{
    private array $payload;
    private int $statusCode;
    private array $headers;

    public function __construct(array $payload, int $statusCode = 200, array $headers = [])
    {
        $this->payload = $payload;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Send the JSON response to the client
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: application/json; charset=utf-8');

            foreach ($this->headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }

        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get the status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
