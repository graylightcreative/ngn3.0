<?php
namespace NGN\Lib\Services\Marketplace;

/**
 * Professional Marketplace Service
 * Handles B2B service listings (Mixing, Mastering, Session Work)
 * Bible Ref: Chapter 53 (MyIndiPro Node)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class MarketplaceService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Get all active service listings
     */
    public function getListings(array $filters = []): array
    {
        $query = "SELECT * FROM service_listings WHERE status = 'active'";
        $params = [];

        if (!empty($filters['category'])) {
            $query .= " AND category = ?";
            $params[] = $filters['category'];
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new professional service listing
     */
    public function createListing(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO service_listings (user_id, name, description, category, base_price, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['name'],
            $data['description'],
            $data['category'],
            $data['base_price']
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
