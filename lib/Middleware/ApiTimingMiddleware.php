<?php

namespace NGN\Lib\Middleware;

use NGN\Lib\Services\MetricsService;
use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;

/**
 * API Timing Middleware
 *
 * Wraps API requests to measure and record request duration.
 * Enables P95 latency monitoring for Bible Ch. 12 alert requirements.
 *
 * Usage:
 *   $middleware = new ApiTimingMiddleware($metricsService);
 *   $middleware->wrap($request, $userId, function() use ($router) {
 *       return $router->dispatch();
 *   });
 */
class ApiTimingMiddleware
{
    private MetricsService $metricsService;
    private float $startTime;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Wrap a request handler and measure its execution time
     *
     * @param Request $request The HTTP request object
     * @param int|null $userId Authenticated user ID (if applicable)
     * @param callable $handler The request handler function
     * @return JsonResponse The response from the handler
     */
    public function wrap(Request $request, ?int $userId, callable $handler): JsonResponse
    {
        // Start timing
        $this->startTime = microtime(true);

        // Execute the handler
        try {
            $response = $handler();

            // Ensure we have a JsonResponse
            if (!($response instanceof JsonResponse)) {
                $response = new JsonResponse(['success' => false, 'message' => 'Invalid response type'], 500);
            }
        } catch (\Throwable $e) {
            // Catch any uncaught exceptions and return 500 response
            error_log("ApiTimingMiddleware caught exception: " . $e->getMessage());
            $response = new JsonResponse([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }

        // Calculate duration
        $endTime = microtime(true);
        $durationMs = ($endTime - $this->startTime) * 1000;

        // Record metrics
        $this->recordMetrics($request, $response, $durationMs, $userId);

        return $response;
    }

    /**
     * Record request metrics to database
     *
     * @param Request $request The HTTP request
     * @param JsonResponse $response The HTTP response
     * @param float $durationMs Request duration in milliseconds
     * @param int|null $userId Authenticated user ID
     */
    private function recordMetrics(
        Request $request,
        JsonResponse $response,
        float $durationMs,
        ?int $userId
    ): void {
        try {
            // Extract endpoint path (remove query string)
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $endpoint = strtok($uri, '?');

            // Normalize endpoint to remove IDs (e.g., /api/v1/posts/123 -> /api/v1/posts/:id)
            $endpoint = $this->normalizeEndpoint($endpoint);

            // Get HTTP method
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            // Get status code from response
            $statusCode = $response->getStatusCode();

            // Record to database (non-blocking, failures are logged)
            $this->metricsService->recordRequest(
                $endpoint,
                $method,
                $statusCode,
                $durationMs,
                $userId
            );
        } catch (\Throwable $e) {
            // Don't let metrics recording break the request
            error_log("Failed to record request metrics: " . $e->getMessage());
        }
    }

    /**
     * Normalize endpoint by replacing numeric IDs with :id placeholder
     *
     * Examples:
     *   /api/v1/posts/123 -> /api/v1/posts/:id
     *   /api/v1/users/456/posts/789 -> /api/v1/users/:id/posts/:id
     *
     * @param string $endpoint The raw endpoint path
     * @return string Normalized endpoint
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        // Replace numeric segments with :id
        $normalized = preg_replace('/\/\d+/', '/:id', $endpoint);

        // Replace UUID-like segments with :id
        $normalized = preg_replace('/\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/:id', $normalized);

        return $normalized;
    }

    /**
     * Get the current request duration (useful for debugging)
     *
     * @return float Duration in milliseconds since start
     */
    public function getCurrentDuration(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }
}
