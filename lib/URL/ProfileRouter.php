<?php
/**
 * ProfileRouter - URL resolution service
 *
 * Resolves clean URLs to the correct entity:
 * - /john-doe → artist/label/venue/station with slug "john-doe"
 * - /@john_smith → fan user with username "john_smith"
 * - john.ngn.io → subdomain routing (premium tier)
 * - johnny.com → custom domain routing (premium tier)
 *
 * Returns: ['entity_type' => '...', 'entity_id' => N, 'slug' => '...', ...]
 */

namespace NGN\Lib\URL;

use PDO;
use Exception;

class ProfileRouter
{
    private PDO $pdo;
    private array $cache = [];
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Resolve a URL slug to an entity
     *
     * @param string $slug - e.g., "john-doe"
     * @return array|null
     */
    public function resolveBySlug(string $slug): ?array
    {
        // Check cache
        $cacheKey = "slug:{$slug}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    entity_type, entity_id, url_slug, canonical_url,
                    custom_domain, custom_domain_verified
                FROM url_routes
                WHERE url_slug = ? AND deleted_at IS NULL
                LIMIT 1'
            );
            $stmt->execute([$slug]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$route) {
                return $this->cache[$cacheKey] = null;
            }

            // Get entity data
            $entity = $this->getEntity($route['entity_type'], $route['entity_id']);
            if (!$entity) {
                return $this->cache[$cacheKey] = null;
            }

            $result = array_merge($route, $entity);
            return $this->cache[$cacheKey] = $result;

        } catch (Exception $e) {
            error_log("ProfileRouter::resolveBySlug error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve a username to a fan user
     *
     * @param string $username - e.g., "john_smith"
     * @return array|null
     */
    public function resolveByUsername(string $username): ?array
    {
        $cacheKey = "username:{$username}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            // Users table has username column
            $stmt = $this->pdo->prepare(
                'SELECT id, username, Email, FirstName, LastName, avatar_url, bio, status
                FROM users
                WHERE username = ? AND status = ?
                LIMIT 1'
            );
            $stmt->execute([$username, 'active']);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return $this->cache[$cacheKey] = null;
            }

            // Return standardized format
            $result = [
                'entity_type' => 'fan',
                'entity_id' => (int)$user['id'],
                'username' => $user['username'],
                'canonical_url' => "/@{$user['username']}",
                'name' => trim("{$user['FirstName']} {$user['LastName']}"),
                'email' => $user['Email'],
                'avatar' => $user['avatar_url'],
                'bio' => $user['bio'] ?? '',
            ];

            return $this->cache[$cacheKey] = $result;

        } catch (Exception $e) {
            error_log("ProfileRouter::resolveByUsername error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve a subdomain to an entity
     *
     * @param string $subdomain - e.g., "john" from john.ngn.io
     * @return array|null
     */
    public function resolveBySubdomain(string $subdomain): ?array
    {
        $cacheKey = "subdomain:{$subdomain}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // For ngn.io, subdomains are first part of slug
        // e.g., john.ngn.io → look for slug starting with "john"
        return $this->resolveBySlug($subdomain);
    }

    /**
     * Resolve a custom domain to an entity
     *
     * @param string $domain - e.g., "johnny.com"
     * @return array|null
     */
    public function resolveByCustomDomain(string $domain): ?array
    {
        $cacheKey = "domain:{$domain}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            // Check if domain is verified
            $stmt = $this->pdo->prepare(
                'SELECT
                    entity_type, entity_id, url_slug, canonical_url,
                    custom_domain, custom_domain_verified
                FROM url_routes
                WHERE custom_domain = ?
                  AND custom_domain_verified = 1
                  AND (custom_domain_expires_at IS NULL OR custom_domain_expires_at > NOW())
                  AND deleted_at IS NULL
                LIMIT 1'
            );
            $stmt->execute([$domain]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$route) {
                return $this->cache[$cacheKey] = null;
            }

            // Get entity data
            $entity = $this->getEntity($route['entity_type'], $route['entity_id']);
            if (!$entity) {
                return $this->cache[$cacheKey] = null;
            }

            $result = array_merge($route, $entity, [
                'canonical_url' => "https://{$domain}"
            ]);
            return $this->cache[$cacheKey] = $result;

        } catch (Exception $e) {
            error_log("ProfileRouter::resolveByCustomDomain error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve by legacy ID (for backwards compatibility)
     *
     * @param string $entityType - 'artist', 'label', 'venue', 'station'
     * @param int $entityId
     * @return array|null
     */
    public function resolveById(string $entityType, int $entityId): ?array
    {
        try {
            // Get entity
            $entity = $this->getEntity($entityType, $entityId);
            if (!$entity) {
                return null;
            }

            // Get url_routes entry
            $stmt = $this->pdo->prepare(
                'SELECT * FROM url_routes
                WHERE entity_type = ? AND entity_id = ?
                LIMIT 1'
            );
            $stmt->execute([$entityType, $entityId]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$route) {
                // Create a temporary result from entity data
                return array_merge($entity, [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'canonical_url' => "/{$entity['url_slug'] ?? "{$entityType}/{$entityId}"}"
                ]);
            }

            return array_merge($route, $entity);

        } catch (Exception $e) {
            error_log("ProfileRouter::resolveById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get raw entity data from appropriate table
     *
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    private function getEntity(string $entityType, int $entityId): ?array
    {
        try {
            switch ($entityType) {
                case 'artist':
                    $stmt = $this->pdo->prepare(
                        'SELECT id, Name, Image, Bio, url_slug, claimed FROM artists WHERE id = ? LIMIT 1'
                    );
                    break;
                case 'label':
                    $stmt = $this->pdo->prepare(
                        'SELECT id, Name, Image, Bio, url_slug, claimed FROM labels WHERE id = ? LIMIT 1'
                    );
                    break;
                case 'venue':
                    $stmt = $this->pdo->prepare(
                        'SELECT id, Name, Image, Bio, url_slug, claimed FROM venues WHERE id = ? LIMIT 1'
                    );
                    break;
                case 'station':
                    $stmt = $this->pdo->prepare(
                        'SELECT id, Name, Image, Bio, url_slug, claimed FROM stations WHERE id = ? LIMIT 1'
                    );
                    break;
                default:
                    return null;
            }

            $stmt->execute([$entityId]);
            $entity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$entity) {
                return null;
            }

            return [
                'name' => $entity['Name'],
                'image' => $entity['Image'],
                'bio' => $entity['Bio'],
                'url_slug' => $entity['url_slug'],
                'claimed' => (bool)$entity['claimed'],
            ];

        } catch (Exception $e) {
            error_log("ProfileRouter::getEntity error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a route exists
     *
     * @param string $slug
     * @return bool
     */
    public function routeExists(string $slug): bool
    {
        return $this->resolveBySlug($slug) !== null;
    }

    /**
     * Create a new URL route
     *
     * @param string $entityType
     * @param int $entityId
     * @param string $urlSlug
     * @param string|null $canonicalUrl
     * @return bool
     */
    public function createRoute(string $entityType, int $entityId, string $urlSlug, ?string $canonicalUrl = null): bool
    {
        try {
            $canonicalUrl = $canonicalUrl ?? "/{$urlSlug}";

            $stmt = $this->pdo->prepare(
                'INSERT INTO url_routes
                (entity_type, entity_id, url_slug, canonical_url, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())'
            );

            $result = $stmt->execute([$entityType, $entityId, $urlSlug, $canonicalUrl]);

            if ($result) {
                $this->clearCache();
            }

            return $result;

        } catch (Exception $e) {
            error_log("ProfileRouter::createRoute error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a custom domain for a route
     *
     * @param string $slug
     * @param string|null $customDomain
     * @param bool $verified
     * @return bool
     */
    public function updateCustomDomain(string $slug, ?string $customDomain, bool $verified = false): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE url_routes
                SET custom_domain = ?,
                    custom_domain_verified = ?,
                    custom_domain_verified_at = IF(?, NOW(), NULL),
                    updated_at = NOW()
                WHERE url_slug = ?'
            );

            $result = $stmt->execute([$customDomain, $verified ? 1 : 0, $verified ? 1 : 0, $slug]);

            if ($result) {
                $this->clearCache($slug);
            }

            return $result;

        } catch (Exception $e) {
            error_log("ProfileRouter::updateCustomDomain error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all routes for an entity type
     *
     * @param string $entityType
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getRoutesByType(string $entityType, int $limit = 100, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM url_routes
                WHERE entity_type = ? AND deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?'
            );
            $stmt->execute([$entityType, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("ProfileRouter::getRoutesByType error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cache
     *
     * @param string|null $slug - Clear specific slug cache or all
     * @return void
     */
    private function clearCache(?string $slug = null): void
    {
        if ($slug) {
            unset($this->cache["slug:{$slug}"]);
            unset($this->cache["domain:{$slug}"]);
        } else {
            $this->cache = [];
        }
    }

    /**
     * Static helper: Resolve custom domain quickly with config-provided PDO
     * Useful for early routing in bootstrap or index.php
     *
     * @param string $domain - e.g., "johnny.com"
     * @param PDO|null $pdo - Optional PDO instance; uses Config default if not provided
     * @return array|null
     */
    public static function staticResolveCustomDomain(string $domain, ?PDO $pdo = null): ?array
    {
        if (!$pdo) {
            try {
                $config = new \NGN\Lib\Config();
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
            } catch (Exception $e) {
                error_log("ProfileRouter::staticResolveCustomDomain - Failed to get PDO: " . $e->getMessage());
                return null;
            }
        }

        $router = new self($pdo);
        return $router->resolveByCustomDomain($domain);
    }
}
