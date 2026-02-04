<?php
/**
 * SlugGenerator - URL slug generation and management
 *
 * Handles:
 * - Auto-generation of URL slugs from names
 * - Uniqueness checking across entity types
 * - Reserved word validation
 * - Manual slug override for Pro/Premium tiers
 */

namespace NGN\Lib\URL;

use PDO;
use Exception;

class SlugGenerator
{
    private PDO $pdo;
    private array $reservedWords = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadReservedWords();
    }

    /**
     * Generate a URL slug from an entity name
     *
     * Examples:
     * - "John Doe" → "john-doe"
     * - "The Beatles" → "the-beatles"
     * - "UPPERCASE NAME!!!" → "uppercase-name"
     *
     * @param string $name
     * @param string $entityType
     * @param int|null $entityId - If set, checks current slug to avoid re-counting self
     * @return string
     */
    public function generateSlug(string $name, string $entityType, ?int $entityId = null): string
    {
        // Step 1: Normalize the name
        $slug = $this->normalizeToSlug($name);

        // Step 2: Check uniqueness
        $uniqueSlug = $this->ensureUnique($slug, $entityType, $entityId);

        // Step 3: Validate against reserved words
        if ($this->isReserved($uniqueSlug)) {
            // If the slug itself is reserved, append -profile
            $uniqueSlug = $this->ensureUnique($uniqueSlug . '-profile', $entityType, $entityId);
        }

        return $uniqueSlug;
    }

    /**
     * Validate a manually-claimed slug (Pro/Premium tier)
     *
     * Checks:
     * - Is it available?
     * - Is it not reserved?
     * - Is it safe (no XSS/injection)?
     *
     * @param string $desiredSlug
     * @param string $entityType
     * @param int|null $currentEntityId - Current owner if updating
     * @param string $tier - 'free', 'pro', 'premium'
     * @return array ['valid' => bool, 'error' => string|null, 'slug' => string]
     */
    public function validateSlug(string $desiredSlug, string $entityType, ?int $currentEntityId = null, string $tier = 'free'): array
    {
        // Sanitize input
        $sanitized = $this->normalizeToSlug($desiredSlug);

        // Slug too short?
        if (strlen($sanitized) < 2) {
            return [
                'valid' => false,
                'error' => 'Slug must be at least 2 characters',
                'slug' => $sanitized
            ];
        }

        // Slug too long?
        if (strlen($sanitized) > 50) {
            return [
                'valid' => false,
                'error' => 'Slug must be 50 characters or less',
                'slug' => $sanitized
            ];
        }

        // Reserved word?
        if ($this->isReserved($sanitized)) {
            return [
                'valid' => false,
                'error' => 'This slug is reserved',
                'slug' => $sanitized
            ];
        }

        // Already claimed?
        if (!$this->isAvailable($sanitized, $entityType, $currentEntityId)) {
            return [
                'valid' => false,
                'error' => 'This slug is already claimed',
                'slug' => $sanitized
            ];
        }

        // Pro tier can only claim vanity slugs (no @ or special prefix)
        if ($tier === 'pro' && (strpos($sanitized, '@') === 0 || strpos($sanitized, 'admin') === 0)) {
            return [
                'valid' => false,
                'error' => 'This slug pattern is not available for your tier',
                'slug' => $sanitized
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'slug' => $sanitized
        ];
    }

    /**
     * Normalize a name/slug string to URL-safe format
     *
     * - Lowercase
     * - Remove special characters (keep only alphanumeric, hyphens)
     * - Replace spaces with hyphens
     * - Remove consecutive hyphens
     *
     * @param string $str
     * @return string
     */
    private function normalizeToSlug(string $str): string
    {
        // Lowercase
        $slug = strtolower($str);

        // Replace non-alphanumeric characters (except hyphen) with hyphen
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);

        // Remove consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start/end
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Ensure slug is globally unique across all entity types
     *
     * If "john-doe" exists, returns "john-doe-2", "john-doe-3", etc.
     *
     * @param string $slug
     * @param string $entityType
     * @param int|null $currentEntityId
     * @return string
     */
    private function ensureUnique(string $slug, string $entityType, ?int $currentEntityId = null): string
    {
        // Check if available
        if ($this->isAvailable($slug, $entityType, $currentEntityId)) {
            return $slug;
        }

        // Try appending numbers: john-doe-2, john-doe-3, etc.
        for ($i = 2; $i <= 100; $i++) {
            $candidate = "{$slug}-{$i}";
            if ($this->isAvailable($candidate, $entityType, $currentEntityId)) {
                return $candidate;
            }
        }

        // Fallback: use entity ID as suffix
        return "{$slug}-{$currentEntityId}";
    }

    /**
     * Check if a slug is available
     *
     * A slug is available if:
     * 1. Not in url_routes
     * 2. Not in any entity's url_slug column
     * 3. Not reserved
     *
     * @param string $slug
     * @param string $entityType
     * @param int|null $excludeEntityId
     * @return bool
     */
    private function isAvailable(string $slug, string $entityType, ?int $excludeEntityId = null): bool
    {
        try {
            // Check url_routes table (cross-entity check)
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `ngn_2025`.`url_routes` WHERE url_slug = ?'
            );
            $stmt->execute([$slug]);
            if ((int)$stmt->fetchColumn() > 0) {
                return false;
            }

            // Check entity-specific slug columns
            if ($entityType === 'artist') {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `ngn_2025`.`artists` WHERE url_slug = ?' .
                    ($excludeEntityId ? ' AND id != ?' : '')
                );
                $stmt->execute($excludeEntityId ? [$slug, $excludeEntityId] : [$slug]);
                return (int)$stmt->fetchColumn() === 0;
            }

            if ($entityType === 'label') {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `ngn_2025`.`labels` WHERE url_slug = ?' .
                    ($excludeEntityId ? ' AND id != ?' : '')
                );
                $stmt->execute($excludeEntityId ? [$slug, $excludeEntityId] : [$slug]);
                return (int)$stmt->fetchColumn() === 0;
            }

            if ($entityType === 'venue') {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `ngn_2025`.`venues` WHERE url_slug = ?' .
                    ($excludeEntityId ? ' AND id != ?' : '')
                );
                $stmt->execute($excludeEntityId ? [$slug, $excludeEntityId] : [$slug]);
                return (int)$stmt->fetchColumn() === 0;
            }

            if ($entityType === 'station') {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `ngn_2025`.`stations` WHERE url_slug = ?' .
                    ($excludeEntityId ? ' AND id != ?' : '')
                );
                $stmt->execute($excludeEntityId ? [$slug, $excludeEntityId] : [$slug]);
                return (int)$stmt->fetchColumn() === 0;
            }

            if ($entityType === 'fan') {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `ngn_2025`.`users` WHERE username = ?' .
                    ($excludeEntityId ? ' AND id != ?' : '')
                );
                $stmt->execute($excludeEntityId ? [$slug, $excludeEntityId] : [$slug]);
                return (int)$stmt->fetchColumn() === 0;
            }

            return true;

        } catch (Exception $e) {
            error_log("SlugGenerator::isAvailable error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a slug is in the reserved words list
     *
     * @param string $slug
     * @return bool
     */
    private function isReserved(string $slug): bool
    {
        return isset($this->reservedWords[strtolower($slug)]);
    }

    /**
     * Load reserved words from database into memory
     *
     * @return void
     */
    private function loadReservedWords(): void
    {
        try {
            $stmt = $this->pdo->query('SELECT word FROM `ngn_2025`.`url_reserved_words`');
            $words = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->reservedWords = array_fill_keys(array_map('strtolower', $words), true);
        } catch (Exception $e) {
            error_log("SlugGenerator::loadReservedWords error: " . $e->getMessage());
            // Continue without reserved words list
        }
    }

    /**
     * Get available slug alternatives for a given base slug
     *
     * Useful for showing users "john-doe-2", "john-doe-music", etc.
     *
     * @param string $baseSlug
     * @param string $entityType
     * @param int $count - How many alternatives to return
     * @return array
     */
    public function getSuggestedAlternatives(string $baseSlug, string $entityType, int $count = 5): array
    {
        $alternatives = [];

        // Try numeric suffixes
        for ($i = 2; $i <= $count + 1 && count($alternatives) < $count; $i++) {
            $candidate = "{$baseSlug}-{$i}";
            if ($this->isAvailable($candidate, $entityType)) {
                $alternatives[] = $candidate;
            }
        }

        // Try descriptive suffixes
        $suffixes = ['music', 'artist', 'official', 'pro', 'studio', 'records'];
        foreach ($suffixes as $suffix) {
            if (count($alternatives) >= $count) {
                break;
            }
            $candidate = "{$baseSlug}-{$suffix}";
            if ($this->isAvailable($candidate, $entityType)) {
                $alternatives[] = $candidate;
            }
        }

        return array_slice($alternatives, 0, $count);
    }

    /**
     * Migrate existing entities to have URL slugs
     *
     * Run once during migration to populate url_slug for all existing entities
     *
     * @return array ['processed' => int, 'errors' => array]
     */
    public function migrateExistingEntities(): array
    {
        $result = [
            'processed' => 0,
            'errors' => []
        ];

        try {
            // Artists
            $stmt = $this->pdo->query('SELECT id, name AS Name FROM `ngn_2025`.`artists` WHERE url_slug IS NULL LIMIT 500');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $slug = $this->generateSlug($row['Name'], 'artist', (int)$row['id']);
                    $updateStmt = $this->pdo->prepare(
                        'UPDATE artists SET url_slug = ? WHERE id = ?'
                    );
                    $updateStmt->execute([$slug, $row['id']]);
                    $result['processed']++;
                } catch (Exception $e) {
                    $result['errors'][] = "Artist {$row['id']}: " . $e->getMessage();
                }
            }

            // Labels
            $stmt = $this->pdo->query('SELECT id, name AS Name FROM `ngn_2025`.`labels` WHERE url_slug IS NULL LIMIT 500');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $slug = $this->generateSlug($row['Name'], 'label', (int)$row['id']);
                    $updateStmt = $this->pdo->prepare(
                        'UPDATE labels SET url_slug = ? WHERE id = ?'
                    );
                    $updateStmt->execute([$slug, $row['id']]);
                    $result['processed']++;
                } catch (Exception $e) {
                    $result['errors'][] = "Label {$row['id']}: " . $e->getMessage();
                }
            }

            // Venues
            $stmt = $this->pdo->query('SELECT id, name AS Name FROM `ngn_2025`.`venues` WHERE url_slug IS NULL LIMIT 500');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $slug = $this->generateSlug($row['Name'], 'venue', (int)$row['id']);
                    $updateStmt = $this->pdo->prepare(
                        'UPDATE venues SET url_slug = ? WHERE id = ?'
                    );
                    $updateStmt->execute([$slug, $row['id']]);
                    $result['processed']++;
                } catch (Exception $e) {
                    $result['errors'][] = "Venue {$row['id']}: " . $e->getMessage();
                }
            }

            // Stations
            $stmt = $this->pdo->query('SELECT id, name AS Name FROM `ngn_2025`.`stations` WHERE url_slug IS NULL LIMIT 500');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $slug = $this->generateSlug($row['Name'], 'station', (int)$row['id']);
                    $updateStmt = $this->pdo->prepare(
                        'UPDATE stations SET url_slug = ? WHERE id = ?'
                    );
                    $updateStmt->execute([$slug, $row['id']]);
                    $result['processed']++;
                } catch (Exception $e) {
                    $result['errors'][] = "Station {$row['id']}: " . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $result['errors'][] = "Migration error: " . $e->getMessage();
        }

        return $result;
    }
}
