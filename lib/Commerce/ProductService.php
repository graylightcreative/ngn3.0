<?php
namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * ProductService - Manages products and variants for entity storefronts
 * Supports artist, label, and venue shops with Printful integration
 */
class ProductService
{
    private PDO $read;
    private PDO $write;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }

    /**
     * List products with filters, search, pagination
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(
        ?string $ownerType = null,
        ?int $ownerId = null,
        ?string $type = null,
        string $search = '',
        bool $activeOnly = true,
        int $page = 1,
        int $perPage = 20,
        string $sort = 'created_at',
        string $dir = 'desc'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['name', 'price_cents', 'created_at'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        if ($ownerType !== null && $ownerType !== '') {
            $where[] = 'p.owner_type = :ownerType';
            $params[':ownerType'] = $ownerType;
        }
        if ($ownerId !== null) {
            $where[] = 'p.owner_id = :ownerId';
            $params[':ownerId'] = $ownerId;
        }
        if ($type !== null && $type !== '') {
            $where[] = 'p.type = :type';
            $params[':type'] = $type;
        }
        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($activeOnly) {
            $where[] = "p.status = 'active'";
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $perPage;

        $sortCol = match($sort) {
            'name' => 'p.name',
            'price_cents' => 'p.price_cents',
            default => 'p.created_at',
        };

        try {
            $sql = "SELECT p.*
                    FROM `ngn_2025`.`products` p
                    $whereSql
                    ORDER BY $sortCol $dir, p.name ASC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Count total
            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`products` p $whereSql";
            $cStmt = $this->read->prepare($countSql);
            foreach ($params as $k => $v) {
                $cStmt->bindValue($k, $v);
            }
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('ProductService::list error: ' . $e->getMessage());
            $rows = [];
            $total = 0;
        }

        $items = array_map([$this, 'normalizeProduct'], $rows);
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get single product by ID with variants
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT p.* FROM `ngn_2025`.`products` p WHERE p.id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$row) return null;

            $product = $this->normalizeProduct($row);

            // Fetch variants
            $product['variants'] = $this->getVariants($id);

            return $product;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get product by slug
     * @return array<string,mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        try {
            $sql = "SELECT id FROM `ngn_2025`.`products` WHERE slug = :slug LIMIT 1";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':slug', $slug);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            return $this->get((int)$row['id']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a new product
     * @param array<string,mixed> $data
     * @return array{success: bool, id?: int, error?: string}
     */
    public function create(array $data): array
    {
        $required = ['owner_type', 'owner_id', 'name', 'price_cents'];
        foreach ($required as $field) {
            if (empty($data[$field]) && !($field === 'price_cents' && isset($data[$field]))) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Generate slug if not provided
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);

        try {
            $sql = "INSERT INTO `ngn_2025`.`products` (
                        owner_type, owner_id, name, slug, description, type,
                        price_cents, cost_cents, currency, image_url, gallery_urls,
                        status, track_inventory, inventory_count, allow_backorder,
                        sku, external_id, metadata, tags, created_at, updated_at
                    ) VALUES (
                        :owner_type, :owner_id, :name, :slug, :description, :type,
                        :price_cents, :cost_cents, :currency, :image_url, :gallery_urls,
                        :status, :track_inventory, :inventory_count, :allow_backorder,
                        :sku, :external_id, :metadata, :tags, NOW(), NOW()
                    )";

            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':owner_type', $data['owner_type']);
            $stmt->bindValue(':owner_id', (int)$data['owner_id'], PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':slug', $slug);
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':type', $data['type'] ?? 'physical');
            $stmt->bindValue(':price_cents', (int)$data['price_cents'], PDO::PARAM_INT);
            $stmt->bindValue(':cost_cents', (int)($data['cost_cents'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':currency', $data['currency'] ?? 'USD');
            $stmt->bindValue(':image_url', $data['image_url'] ?? null);
            $stmt->bindValue(':gallery_urls', isset($data['gallery_urls']) ? json_encode($data['gallery_urls']) : null);
            $stmt->bindValue(':status', $data['status'] ?? 'draft');
            $stmt->bindValue(':track_inventory', (int)($data['track_inventory'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':inventory_count', (int)($data['inventory_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':allow_backorder', (int)($data['allow_backorder'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':sku', $data['sku'] ?? null);
            $stmt->bindValue(':external_id', $data['external_id'] ?? null);
            $stmt->bindValue(':metadata', isset($data['metadata']) ? json_encode($data['metadata']) : null);
            $stmt->bindValue(':tags', isset($data['tags']) ? json_encode($data['tags']) : null);
            $stmt->execute();

            $id = (int)$this->write->lastInsertId();
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a product
     * @param array<string,mixed> $data
     * @return array{success: bool, error?: string}
     */
    public function update(int $id, array $data): array
    {
        $sets = [];
        $params = [':id' => $id];

        $allowedFields = [
            'name', 'slug', 'description', 'type', 'price_cents', 'cost_cents',
            'currency', 'image_url', 'status', 'track_inventory',
            'inventory_count', 'allow_backorder', 'sku', 'external_id'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $val = $data[$field];
                if (in_array($field, ['track_inventory', 'allow_backorder'])) {
                    $val = (int)$val;
                } elseif ($field === 'price_cents' || $field === 'inventory_count') {
                    $val = (int)$val;
                }
                $params[":$field"] = $val;
            }
        }

        // Handle JSON fields
        if (array_key_exists('gallery_urls', $data)) {
            $sets[] = 'gallery_urls = :gallery_urls';
            $params[':gallery_urls'] = json_encode($data['gallery_urls']);
        }
        if (array_key_exists('metadata', $data)) {
            $sets[] = 'metadata = :metadata';
            $params[':metadata'] = json_encode($data['metadata']);
        }
        if (array_key_exists('tags', $data)) {
            $sets[] = 'tags = :tags';
            $params[':tags'] = json_encode($data['tags']);
        }

        if (empty($sets)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $sets[] = 'updated_at = NOW()';

        try {
            $sql = "UPDATE `ngn_2025`.`products` SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get variants for a product
     * @return array<int, array<string,mixed>>
     */
    public function getVariants(int $productId): array
    {
        try {
            $sql = "SELECT v.*
                    FROM `ngn_2025`.`product_variants` v
                    WHERE v.product_id = :productId
                    ORDER BY v.id ASC";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':productId', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'product_id' => (int)$row['product_id'],
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'price_cents' => isset($row['price_cents']) ? (int)$row['price_cents'] : 0,
                    'cost_cents' => isset($row['cost_cents']) ? (int)$row['cost_cents'] : 0,
                    'inventory_count' => (int)($row['inventory_count'] ?? 0),
                    'external_id' => $row['external_id'] ?? null,
                    'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null,
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get featured products across all storefronts
     * @return array<int, array<string,mixed>>
     */
    public function getFeatured(int $limit = 12): array
    {
        try {
            $sql = "SELECT p.*
                    FROM `ngn_2025`.`products` p
                    WHERE p.status = 'active'
                    ORDER BY p.created_at DESC
                    LIMIT :limit";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map([$this, 'normalizeProduct'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Normalize product row from database
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeProduct(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'owner_type' => $row['owner_type'] ?? null,
            'owner_id' => isset($row['owner_id']) ? (int)$row['owner_id'] : null,
            'name' => $row['name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'description' => $row['description'] ?? null,
            'type' => $row['type'] ?? 'physical',
            'price_cents' => isset($row['price_cents']) ? (int)$row['price_cents'] : 0,
            'price' => (float)((int)($row['price_cents'] ?? 0) / 100),
            'cost_cents' => isset($row['cost_cents']) ? (int)$row['cost_cents'] : 0,
            'cost' => (float)((int)($row['cost_cents'] ?? 0) / 100),
            'currency' => $row['currency'] ?? 'USD',
            'image_url' => $row['image_url'] ?? null,
            'gallery_urls' => isset($row['gallery_urls']) ? json_decode($row['gallery_urls'], true) : null,
            'status' => $row['status'] ?? 'draft',
            'track_inventory' => (bool)($row['track_inventory'] ?? false),
            'inventory_count' => (int)($row['inventory_count'] ?? 0),
            'allow_backorder' => (bool)($row['allow_backorder'] ?? false),
            'sku' => $row['sku'] ?? null,
            'external_id' => $row['external_id'] ?? null,
            'metadata' => isset($row['metadata']) ? json_decode($row['metadata'], true) : null,
            'tags' => isset($row['tags']) ? json_decode($row['tags'], true) : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * Generate URL-safe slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        // Add uniqueness suffix
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        return $slug;
    }
}