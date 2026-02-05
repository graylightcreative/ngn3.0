<?php

namespace NGN\Lib\Fans;

use PDO;
use Exception;

/**
 * Library Service
 *
 * Manages user-centric library features:
 * - Favorites (tracks, albums, posts, videos)
 * - Follows (artists, labels, stations, venues)
 * - History (listening and viewing history)
 *
 * Bible Ch. 23: Retention Loop and Daily Utility
 */
class LibraryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // --- Favorites ---

    public function addFavorite(int $userId, string $entityType, int $entityId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO `ngn_2025`.`favorites` (user_id, entity_type, entity_id, created_at)
            VALUES (:user_id, :type, :id, NOW())
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':type' => $entityType,
            ':id' => $entityId
        ]);
    }

    public function removeFavorite(int $userId, string $entityType, int $entityId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM `ngn_2025`.`favorites`
            WHERE user_id = :user_id AND entity_type = :type AND entity_id = :id
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':type' => $entityType,
            ':id' => $entityId
        ]);
    }

    public function getFavorites(int $userId, ?string $entityType = null, int $limit = 50, int $offset = 0): array
    {
        $where = "WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if ($entityType) {
            $where .= " AND entity_type = :type";
            $params[':type'] = $entityType;
        }

        $sql = "SELECT * FROM `ngn_2025`.`favorites` $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // --- Follows ---

    public function follow(int $userId, int $artistId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO `ngn_2025`.`follows` (user_id, artist_id, followed_at)
            VALUES (:user_id, :artist_id, NOW())
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':artist_id' => $artistId
        ]);
    }

    public function unfollow(int $userId, int $artistId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM `ngn_2025`.`follows`
            WHERE user_id = :user_id AND artist_id = :artist_id
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':artist_id' => $artistId
        ]);
    }

    public function getFollowedArtists(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, f.followed_at 
            FROM `ngn_2025`.`follows` f
            JOIN `ngn_2025`.`artists` a ON f.artist_id = a.id
            WHERE f.user_id = :user_id
            ORDER BY f.followed_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id' , $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // --- History ---

    public function addToHistory(int $userId, string $entityType, int $entityId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`history` (user_id, entity_type, entity_id, occurred_at)
            VALUES (:user_id, :type, :id, NOW())
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':type' => $entityType,
            ':id' => $entityId
        ]);
    }

    public function getHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`history`
            WHERE user_id = :user_id
            ORDER BY occurred_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id' , $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
