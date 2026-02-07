<?php
namespace NGN\Lib\HTTP;

/**
 * Subdomain Router
 * Routes requests from subdomains to appropriate entry points
 */
class SubdomainRouter
{
    private static $routes = [
        'api' => '/api/index.php',
        'admin' => '/admin/index.php',
        'legal' => '/legal/index.php',
        'help' => '/help/index.php',
        'my' => '/dashboard/index.php',
    ];

    /**
     * Extract subdomain from host header
     *
     * @param string $host HTTP_HOST value (e.g., "api.nextgennoise.com")
     * @return string|null Subdomain name or null if none found
     */
    private static function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = explode(':', $host)[0];

        // Split by dots
        $parts = explode('.', $host);

        if (count($parts) >= 3) {
            // Check if second-level domain is nextgennoise.com
            if ($parts[1] . '.' . $parts[2] === 'nextgennoise.com') {
                return $parts[0];
            }
        }

        return null;
    }

    /**
     * Route request to correct handler based on subdomain
     * Intended for early invocation in public/index.php or bootstrap
     */
    public static function route(): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $subdomain = self::extractSubdomain($host);

        if ($subdomain && isset(self::$routes[$subdomain])) {
            $targetPath = __DIR__ . '/../../public' . self::$routes[$subdomain];
            if (file_exists($targetPath)) {
                $_SERVER['SUBDOMAIN_TARGET'] = $subdomain;
                require $targetPath;
                exit;
            }
        }
    }

    /**
     * Get current subdomain (if any)
     *
     * @return string|null Current subdomain or null
     */
    public static function current(): ?string
    {
        return $_SERVER['SUBDOMAIN_TARGET'] ?? null;
    }

    /**
     * Check if request is to a specific subdomain
     *
     * @param string $subdomain Subdomain name to check
     * @return bool True if current request is to that subdomain
     */
    public static function is(string $subdomain): bool
    {
        return self::current() === $subdomain;
    }

    /**
     * Get all registered subdomains
     *
     * @return array Array of subdomain names
     */
    public static function all(): array
    {
        return array_keys(self::$routes);
    }
}
