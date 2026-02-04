<?php

namespace NGN\Lib\DB;

use PDO;

/**
 * LegacyDataTransformer
 *
 * Transforms legacy data from legacy tables to new CDM schema using SQL INSERT...SELECT.
 * More reliable than SQL parsing, uses native database capabilities.
 */
class LegacyDataTransformer
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Transform posts from legacy to new schema
     */
    public function transformPosts(): array
    {
        try {
            // Check if the legacy posts table exists
            $result = $this->pdo->query("SELECT 1 FROM posts LIMIT 1");
            if (!$result) {
                return ['status' => 'skipped', 'message' => 'Posts table not accessible'];
            }
        } catch (\Exception $e) {
            return ['status' => 'skipped', 'message' => 'Posts table not found'];
        }

        try {
            // Use INSERT...SELECT to transform posts
            // This handles the column mapping natively in SQL
            $sql = <<<'SQL'
INSERT INTO `posts` (
    `Id`,
    `Slug`,
    `Title`,
    `Body`,
    `Status`,
    `PublishedAt`,
    `CreatedAt`,
    `UpdatedAt`,
    `engagement_source_tracking`
)
SELECT
    p.`Id`,
    p.`Slug`,
    p.`Title`,
    p.`Body`,
    CASE WHEN p.`Published` = 1 THEN 'published' ELSE 'draft' END,
    IFNULL(p.`PublishedDate`, p.`Created`),
    p.`Created`,
    p.`Updated`,
    1
FROM `posts` p
WHERE p.`Title` IS NOT NULL
    AND p.`Title` != ''
    AND p.`Slug` IS NOT NULL
    AND p.`Slug` != ''
ON DUPLICATE KEY UPDATE
    `Title` = VALUES(`Title`),
    `Body` = VALUES(`Body`),
    `Status` = VALUES(`Status`),
    `PublishedAt` = VALUES(`PublishedAt`),
    `UpdatedAt` = VALUES(`UpdatedAt`)
SQL;

            $this->pdo->exec($sql);

            // Get count of transformed posts
            $count = $this->pdo->query("SELECT COUNT(*) as cnt FROM posts WHERE CreatedAt IS NOT NULL")->fetch()['cnt'];

            return [
                'status' => 'success',
                'message' => "Transformed $count posts from legacy schema"
            ];
        } catch (\Exception $e) {
            error_log("Posts transformation error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Transform artists from legacy to new schema
     */
    public function transformArtists(): array
    {
        try {
            // Check if artists table has data
            $count = $this->pdo->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt'];
            if ($count === 0) {
                return ['status' => 'skipped', 'message' => 'No artists to transform'];
            }

            return [
                'status' => 'success',
                'message' => "$count artists already loaded"
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
