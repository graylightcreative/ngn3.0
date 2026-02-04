<?php
/**
 * Station Playlist Service
 * Manages PLN (Playlist Network) playlists with BYOS content, geo-restrictions, and scheduling
 */

namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class StationPlaylistService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'station_playlist');
    }

    /**
     * Create a new PLN playlist for a station
     *
     * @param int $stationId Station ID
     * @param string $title Playlist title
     * @param array $items Array of items [{track_id: int, position: int}] or [{station_content_id: int, position: int}]
     * @param array|null $geoRestrictions Geo restrictions array
     * @param array|null $schedule Schedule rules
     * @return array Result with 'success', 'id', 'message'
     * @throws \InvalidArgumentException on validation failure
     */
    public function createPlaylist(
        int $stationId,
        string $title,
        array $items = [],
        ?array $geoRestrictions = null,
        ?array $schedule = null
    ): array {
        try {
            // Validate inputs
            if (empty($title)) {
                throw new \InvalidArgumentException('Playlist title is required');
            }

            // Generate unique slug
            $slug = $this->generateSlug($title, $stationId);

            // Create playlist
            $stmt = $this->write->prepare("
                INSERT INTO playlists
                (user_id, slug, title, station_id, playlist_type, geo_restrictions, schedule, visibility)
                VALUES (NULL, :slug, :title, :stationId, 'station_pln', :geoRestrictions, :schedule, 'public')
            ");

            $success = $stmt->execute([
                ':slug' => $slug,
                ':title' => substr($title, 0, 255),
                ':stationId' => $stationId,
                ':geoRestrictions' => $geoRestrictions ? json_encode($geoRestrictions) : null,
                ':schedule' => $schedule ? json_encode($schedule) : null
            ]);

            if (!$success) {
                throw new \RuntimeException('Failed to create playlist');
            }

            $playlistId = (int)$this->write->lastInsertId();

            // Add items if provided
            if (!empty($items)) {
                $this->addPlaylistItems($playlistId, $items);
            }

            $this->logger->info('playlist_created', [
                'playlist_id' => $playlistId,
                'station_id' => $stationId,
                'item_count' => count($items)
            ]);

            return [
                'success' => true,
                'id' => $playlistId,
                'message' => 'Playlist created successfully'
            ];

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('create_playlist_validation_failed', [
                'station_id' => $stationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('create_playlist_failed', [
                'station_id' => $stationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get playlist details
     *
     * @param int $playlistId Playlist ID
     * @param int|null $stationId Verify ownership (optional)
     * @return array|null Playlist details with items
     */
    public function getPlaylist(int $playlistId, ?int $stationId = null): ?array
    {
        try {
            $sql = "
                SELECT
                    p.id, p.slug, p.title, p.station_id,
                    p.playlist_type, p.geo_restrictions, p.schedule,
                    p.visibility, p.created_at, p.updated_at
                FROM `ngn_2025`.`playlists` p
                WHERE p.id = :id
            ";

            if ($stationId !== null) {
                $sql .= " AND p.station_id = :stationId";
            }

            // Use WRITE connection for development, READ for production
            $pdo = $this->write;
            $stmt = $pdo->prepare($sql);
            $params = [':id' => $playlistId];
            if ($stationId !== null) {
                $params[':stationId'] = $stationId;
            }
            $stmt->execute($params);
            $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$playlist) {
                return null;
            }

            // Parse JSON columns
            $playlist['geo_restrictions'] = json_decode($playlist['geo_restrictions'] ?? 'null', true);
            $playlist['schedule'] = json_decode($playlist['schedule'] ?? 'null', true);

            // Fetch playlist items
            $itemsStmt = $pdo->prepare("
                SELECT
                    pi.position,
                    pi.content_type,
                    pi.track_id,
                    pi.station_content_id,
                    CASE
                        WHEN pi.content_type = 'track' THEN t.title
                        WHEN pi.content_type = 'station_content' THEN sc.title
                    END as title,
                    CASE
                        WHEN pi.content_type = 'track' THEN a.name
                        WHEN pi.content_type = 'station_content' THEN sc.artist_name
                    END as artist,
                    pi.added_at
                FROM `ngn_2025`.`playlist_items` pi
                LEFT JOIN `ngn_2025`.`tracks` t ON pi.track_id = t.id
                LEFT JOIN `ngn_2025`.`station_content` sc ON pi.station_content_id = sc.id
                LEFT JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id
                WHERE pi.playlist_id = :playlistId
                ORDER BY pi.position ASC
            ");
            $itemsStmt->execute([':playlistId' => $playlistId]);
            $playlist['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return $playlist;

        } catch (\Throwable $e) {
            $this->logger->error('get_playlist_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * List station playlists
     *
     * @param int $stationId Station ID
     * @param int $page Pagination page
     * @param int $perPage Items per page
     * @return array Result with 'success', 'items', 'total'
     */
    public function listPlaylists(int $stationId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        try {
            // Count total
            $countStmt = $this->write->prepare("
                SELECT COUNT(*) as total
                FROM `ngn_2025`.`playlists`
                WHERE station_id = :stationId AND playlist_type LIKE 'station_%'
            ");
            $countStmt->execute([':stationId' => $stationId]);
            $total = (int)$countStmt->fetchColumn();

            // Fetch paginated results
            $offset = ($page - 1) * $perPage;
            $listStmt = $this->write->prepare("
                SELECT
                    p.id, p.slug, p.title,
                    (SELECT COUNT(*) FROM `ngn_2025`.`playlist_items` WHERE playlist_id = p.id) as item_count,
                    p.playlist_type, p.visibility, p.created_at, p.updated_at
                FROM `ngn_2025`.`playlists` p
                WHERE p.station_id = :stationId AND p.playlist_type LIKE 'station_%'
                ORDER BY p.created_at DESC
                LIMIT :offset, :perPage
            ");
            $listStmt->bindValue(':stationId', $stationId);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            $listStmt->execute();
            $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return [
                'success' => true,
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            ];

        } catch (\Throwable $e) {
            $this->logger->error('list_playlists_failed', ['station_id' => $stationId, 'error' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'total' => 0, 'message' => 'Failed to list playlists'];
        }
    }

    /**
     * Update playlist metadata
     *
     * @param int $playlistId Playlist ID
     * @param int $stationId Station ID (for verification)
     * @param array $updates Fields to update: title, geo_restrictions, schedule
     * @return bool Success
     */
    public function updatePlaylist(int $playlistId, int $stationId, array $updates): bool
    {
        try {
            $allowed = ['title', 'geo_restrictions', 'schedule', 'playlist_type'];
            $set = [];
            $params = [':id' => $playlistId, ':stationId' => $stationId];

            foreach ($updates as $key => $value) {
                if (!in_array($key, $allowed)) {
                    continue;
                }

                if ($key === 'title') {
                    $set[] = 'title = :title';
                    $params[':title'] = substr($value, 0, 255);
                } elseif ($key === 'geo_restrictions') {
                    $set[] = 'geo_restrictions = :geoRestrictions';
                    $params[':geoRestrictions'] = is_array($value) ? json_encode($value) : null;
                } elseif ($key === 'schedule') {
                    $set[] = 'schedule = :schedule';
                    $params[':schedule'] = is_array($value) ? json_encode($value) : null;
                }
            }

            if (empty($set)) {
                return true; // Nothing to update
            }

            $sql = "UPDATE `ngn_2025`.`playlists` SET " . implode(', ', $set) . " WHERE id = :id AND station_id = :stationId";
            $stmt = $this->write->prepare($sql);
            return $stmt->execute($params);

        } catch (\Throwable $e) {
            $this->logger->error('update_playlist_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Add items to playlist
     *
     * @param int $playlistId Playlist ID
     * @param array $items Array of items with position, and either track_id or station_content_id
     * @return bool Success
     */
    public function addPlaylistItems(int $playlistId, array $items): bool
    {
        try {
            // Sort by position
            usort($items, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

            $stmt = $this->write->prepare("
                INSERT INTO `ngn_2025`.`playlist_items`
                (playlist_id, position, content_type, track_id, station_content_id)
                VALUES (:playlistId, :position, :contentType, :trackId, :contentId)
            ");

            foreach ($items as $position => $item) {
                $contentType = 'track';
                $trackId = null;
                $contentId = null;

                if (isset($item['track_id'])) {
                    $contentType = 'track';
                    $trackId = $item['track_id'];
                } elseif (isset($item['station_content_id'])) {
                    $contentType = 'station_content';
                    $contentId = $item['station_content_id'];
                } else {
                    continue; // Skip invalid items
                }

                $stmt->execute([
                    ':playlistId' => $playlistId,
                    ':position' => $item['position'] ?? $position,
                    ':contentType' => $contentType,
                    ':trackId' => $trackId,
                    ':contentId' => $contentId
                ]);
            }

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('add_playlist_items_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Update playlist item order
     *
     * @param int $playlistId Playlist ID
     * @param array $items Array with position => {track_id or station_content_id}
     * @return bool Success
     */
    public function reorderItems(int $playlistId, array $items): bool
    {
        try {
            $this->write->beginTransaction();

            $updateTrackStmt = $this->write->prepare(
                "UPDATE `ngn_2025`.`playlist_items` SET position = :position WHERE playlist_id = :playlist_id AND track_id = :id"
            );
            
            $updateContentStmt = $this->write->prepare(
                "UPDATE `ngn_2025`.`playlist_items` SET position = :position WHERE playlist_id = :playlist_id AND station_content_id = :id"
            );

            foreach ($items as $item) {
                if (!isset($item['id'], $item['type'], $item['position'])) continue;

                $params = [
                    ':position' => $item['position'],
                    ':playlist_id' => $playlistId,
                    ':id' => $item['id'],
                ];

                if ($item['type'] === 'track') {
                    $updateTrackStmt->execute($params);
                } elseif ($item['type'] === 'station_content') {
                    $updateContentStmt->execute($params);
                }
            }

            $this->write->commit();
            return true;

        } catch (\Throwable $e) {
            $this->write->rollBack();
            $this->logger->error('reorder_items_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Remove item from playlist
     *
     * @param int $playlistId Playlist ID
     * @param int $position Item position
     * @return bool Success
     */
    public function removeItem(int $playlistId, int $position): bool
    {
        try {
            $stmt = $this->write->prepare("
                DELETE FROM `ngn_2025`.`playlist_items`
                WHERE playlist_id = :playlistId AND position = :position
            ");
            return $stmt->execute([':playlistId' => $playlistId, ':position' => $position]);

        } catch (\Throwable $e) {
            $this->logger->error('remove_item_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete playlist
     *
     * @param int $playlistId Playlist ID
     * @param int $stationId Station ID (for verification)
     * @return bool Success
     */
    public function deletePlaylist(int $playlistId, int $stationId): bool
    {
        try {
            $stmt = $this->write->prepare("
                DELETE FROM `ngn_2025`.`playlists`
                WHERE id = :id AND station_id = :stationId AND playlist_type LIKE 'station_%'
            ");
            return $stmt->execute([':id' => $playlistId, ':stationId' => $stationId]);

        } catch (\Throwable $e) {
            $this->logger->error('delete_playlist_failed', ['playlist_id' => $playlistId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Generate unique slug for playlist
     *
     * @param string $title Playlist title
     * @param int $stationId Station ID (for uniqueness)
     * @return string Unique slug
     */
    private function generateSlug(string $title, int $stationId): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', substr($title, 0, 50)));
        $base = preg_replace('/-+/', '-', $base);
        $base = trim($base, '-');

        // Ensure uniqueness
        $slug = $base;
        $counter = 1;
        while ($this->slugExists($slug, $stationId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug already exists
     *
     * @param string $slug Slug to check
     * @param int $stationId Station ID
     * @return bool True if exists
     */
    private function slugExists(string $slug, int $stationId): bool
    {
        try {
            $stmt = $this->write->prepare("
                SELECT COUNT(*) FROM `ngn_2025`.`playlists`
                WHERE slug = :slug AND station_id = :stationId
            ");
            $stmt->execute([':slug' => $slug, ':stationId' => $stationId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
