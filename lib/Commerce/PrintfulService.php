<?php
namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * PrintfulService - Integration with Printful API for print-on-demand merchandise
 * 
 * Features:
 * - Sync products from Printful catalog
 * - Create orders in Printful
 * - Handle webhooks for order status updates
 * - Manage product variants (sizes, colors)
 * - Calculate shipping rates
 * 
 * @see https://developers.printful.com/docs/
 */
class PrintfulService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    
    private const API_BASE = 'https://api.printful.com';
    private const API_VERSION = '';  // Printful uses unversioned API
    
    // Printful order statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_INPROCESS = 'inprocess';
    public const STATUS_ONHOLD = 'onhold';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FULFILLED = 'fulfilled';
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }
    
    /**
     * Get Printful API key from config/env
     */
    private function getApiKey(): ?string
    {
        return getenv('PRINTFUL_API_KEY') ?: null;
    }
    
    /**
     * Make authenticated request to Printful API
     * @param string $method HTTP method
     * @param string $endpoint API endpoint (e.g., /products)
     * @param array<string,mixed>|null $data Request body for POST/PUT
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'error' => 'Printful API key not configured'];
        }
        
        $url = self::API_BASE . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'cURL error: ' . $error, 'status' => 0];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded['result'] ?? $decoded, 'status' => $httpCode];
        }
        
        $errorMsg = $decoded['error']['message'] ?? $decoded['message'] ?? 'Unknown error';
        return ['success' => false, 'error' => $errorMsg, 'status' => $httpCode, 'data' => $decoded];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // CATALOG / PRODUCTS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Get available product catalog from Printful
     * @return array{success: bool, items?: array, error?: string}
     */
    public function getCatalog(): array
    {
        $result = $this->request('GET', '/products');
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'items' => $result['data'] ?? []];
    }
    
    /**
     * Get product details including variants
     * @return array{success: bool, product?: array, error?: string}
     */
    public function getCatalogProduct(int $productId): array
    {
        $result = $this->request('GET', '/products/' . $productId);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'product' => $result['data'] ?? null];
    }
    
    /**
     * Get variant details
     * @return array{success: bool, variant?: array, error?: string}
     */
    public function getVariant(int $variantId): array
    {
        $result = $this->request('GET', '/products/variant/' . $variantId);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'variant' => $result['data'] ?? null];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // STORE SYNC PRODUCTS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Get all synced products in the store
     * @return array{success: bool, items?: array, error?: string}
     */
    public function getSyncProducts(int $offset = 0, int $limit = 100): array
    {
        $result = $this->request('GET', '/sync/products?offset=' . $offset . '&limit=' . $limit);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'items' => $result['data'] ?? []];
    }
    
    /**
     * Get a single synced product with variants
     * @return array{success: bool, product?: array, error?: string}
     */
    public function getSyncProduct(int $syncProductId): array
    {
        $result = $this->request('GET', '/sync/products/' . $syncProductId);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'product' => $result['data'] ?? null];
    }
    
    /**
     * Create a new sync product
     * @param array<string,mixed> $productData
     * @return array{success: bool, product?: array, error?: string}
     */
    public function createSyncProduct(array $productData): array
    {
        $result = $this->request('POST', '/sync/products', $productData);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'product' => $result['data'] ?? null];
    }
    
    /**
     * Update a sync product
     * @param array<string,mixed> $productData
     * @return array{success: bool, product?: array, error?: string}
     */
    public function updateSyncProduct(int $syncProductId, array $productData): array
    {
        $result = $this->request('PUT', '/sync/products/' . $syncProductId, $productData);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'product' => $result['data'] ?? null];
    }
    
    /**
     * Delete a sync product
     * @return array{success: bool, error?: string}
     */
    public function deleteSyncProduct(int $syncProductId): array
    {
        return $this->request('DELETE', '/sync/products/' . $syncProductId);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // ORDERS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Get all orders
     * @return array{success: bool, items?: array, error?: string}
     */
    public function getOrders(int $offset = 0, int $limit = 100, ?string $status = null): array
    {
        $query = '?offset=' . $offset . '&limit=' . $limit;
        if ($status !== null) {
            $query .= '&status=' . urlencode($status);
        }
        $result = $this->request('GET', '/orders' . $query);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'items' => $result['data'] ?? []];
    }
    
    /**
     * Get a single order
     * @return array{success: bool, order?: array, error?: string}
     */
    public function getOrder(int $printfulOrderId): array
    {
        $result = $this->request('GET', '/orders/' . $printfulOrderId);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'order' => $result['data'] ?? null];
    }
    
    /**
     * Create an order in Printful
     * @param array<string,mixed> $orderData Order data including recipient and items
     * @return array{success: bool, order?: array, error?: string}
     */
    public function createOrder(array $orderData): array
    {
        // Ensure required fields
        if (empty($orderData['recipient'])) {
            return ['success' => false, 'error' => 'Recipient address is required'];
        }
        if (empty($orderData['items'])) {
            return ['success' => false, 'error' => 'At least one item is required'];
        }
        
        $result = $this->request('POST', '/orders', $orderData);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'order' => $result['data'] ?? null];
    }
    
    /**
     * Confirm a draft order (submit for fulfillment)
     * @return array{success: bool, order?: array, error?: string}
     */
    public function confirmOrder(int $printfulOrderId): array
    {
        $result = $this->request('POST', '/orders/' . $printfulOrderId . '/confirm');
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'order' => $result['data'] ?? null];
    }
    
    /**
     * Cancel an order
     * @return array{success: bool, order?: array, error?: string}
     */
    public function cancelOrder(int $printfulOrderId): array
    {
        $result = $this->request('DELETE', '/orders/' . $printfulOrderId);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'order' => $result['data'] ?? null];
    }
    
    /**
     * Estimate order costs before creating
     * @param array<string,mixed> $orderData
     * @return array{success: bool, costs?: array, error?: string}
     */
    public function estimateOrderCosts(array $orderData): array
    {
        $result = $this->request('POST', '/orders/estimate-costs', $orderData);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'costs' => $result['data'] ?? null];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // SHIPPING RATES
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Calculate shipping rates for an order
     * @param array<string,mixed> $shippingData Recipient address and items
     * @return array{success: bool, rates?: array, error?: string}
     */
    public function getShippingRates(array $shippingData): array
    {
        $result = $this->request('POST', '/shipping/rates', $shippingData);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'rates' => $result['data'] ?? []];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // WEBHOOKS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Get configured webhooks
     * @return array{success: bool, webhooks?: array, error?: string}
     */
    public function getWebhooks(): array
    {
        $result = $this->request('GET', '/webhooks');
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'webhooks' => $result['data'] ?? null];
    }
    
    /**
     * Configure webhooks for order events
     * @param string $url Webhook endpoint URL
     * @param array<string> $types Event types to subscribe to
     * @return array{success: bool, webhook?: array, error?: string}
     */
    public function setupWebhooks(string $url, array $types = []): array
    {
        if (empty($types)) {
            // Subscribe to all order-related events by default
            $types = [
                'package_shipped',
                'package_returned',
                'order_created',
                'order_updated',
                'order_failed',
                'order_canceled',
                'order_put_hold',
                'order_remove_hold',
            ];
        }
        
        $result = $this->request('POST', '/webhooks', [
            'url' => $url,
            'types' => $types,
        ]);
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'webhook' => $result['data'] ?? null];
    }
    
    /**
     * Disable all webhooks
     * @return array{success: bool, error?: string}
     */
    public function disableWebhooks(): array
    {
        return $this->request('DELETE', '/webhooks');
    }
    
    /**
     * Handle incoming webhook from Printful
     * @param array<string,mixed> $payload Webhook payload
     * @return array{success: bool, processed?: bool, error?: string}
     */
    public function handleWebhook(array $payload): array
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];
        
        try {
            switch ($type) {
                case 'package_shipped':
                    return $this->handlePackageShipped($data);
                case 'order_created':
                    return $this->handleOrderCreated($data);
                case 'order_updated':
                    return $this->handleOrderUpdated($data);
                case 'order_failed':
                    return $this->handleOrderFailed($data);
                case 'order_canceled':
                    return $this->handleOrderCanceled($data);
                case 'package_returned':
                    return $this->handlePackageReturned($data);
                default:
                    // Log unknown webhook type but don't fail
                    return ['success' => true, 'processed' => false, 'note' => 'Unknown webhook type: ' . $type];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle package_shipped webhook - update order with tracking info
     * @param array<string,mixed> $data
     */
    private function handlePackageShipped(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        $shipment = $data['shipment'] ?? [];
        $trackingNumber = $shipment['tracking_number'] ?? null;
        $trackingUrl = $shipment['tracking_url'] ?? null;
        $carrier = $shipment['carrier'] ?? null;
        
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID in webhook'];
        }
        
        // Update our orders table
        $sql = "UPDATE `ngn_2025`.`orders` 
                SET printful_status = :status,
                    tracking_number = COALESCE(:tracking, tracking_number),
                    tracking_url = COALESCE(:trackingUrl, tracking_url),
                    shipping_carrier = COALESCE(:carrier, shipping_carrier),
                    shipped_at = COALESCE(shipped_at, NOW()),
                    status = 'shipped',
                    updated_at = NOW()
                WHERE printful_order_id = :printfulId";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':status', 'shipped');
        $stmt->bindValue(':tracking', $trackingNumber);
        $stmt->bindValue(':trackingUrl', $trackingUrl);
        $stmt->bindValue(':carrier', $carrier);
        $stmt->bindValue(':printfulId', (string)$printfulOrderId);
        $stmt->execute();
        
        // Log event
        $this->logPrintfulEvent($printfulOrderId, 'package_shipped', $data);
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Handle order_created webhook
     * @param array<string,mixed> $data
     */
    private function handleOrderCreated(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID'];
        }
        
        $this->updatePrintfulStatus($printfulOrderId, 'pending');
        $this->logPrintfulEvent($printfulOrderId, 'order_created', $data);
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Handle order_updated webhook
     * @param array<string,mixed> $data
     */
    private function handleOrderUpdated(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        $status = $data['order']['status'] ?? null;
        
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID'];
        }
        
        $this->updatePrintfulStatus($printfulOrderId, $status);
        $this->logPrintfulEvent($printfulOrderId, 'order_updated', $data);
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Handle order_failed webhook
     * @param array<string,mixed> $data
     */
    private function handleOrderFailed(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        $reason = $data['reason'] ?? 'Unknown reason';
        
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID'];
        }
        
        $this->updatePrintfulStatus($printfulOrderId, 'failed');
        $this->logPrintfulEvent($printfulOrderId, 'order_failed', array_merge($data, ['failure_reason' => $reason]));
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Handle order_canceled webhook
     * @param array<string,mixed> $data
     */
    private function handleOrderCanceled(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID'];
        }
        
        $this->updatePrintfulStatus($printfulOrderId, 'canceled');
        
        // Also update NGN order status
        $sql = "UPDATE `ngn_2025`.`orders` 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE printful_order_id = :printfulId";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':printfulId', (string)$printfulOrderId);
        $stmt->execute();
        
        $this->logPrintfulEvent($printfulOrderId, 'order_canceled', $data);
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Handle package_returned webhook
     * @param array<string,mixed> $data
     */
    private function handlePackageReturned(array $data): array
    {
        $printfulOrderId = $data['order']['id'] ?? null;
        
        if (!$printfulOrderId) {
            return ['success' => false, 'error' => 'Missing order ID'];
        }
        
        $this->updatePrintfulStatus($printfulOrderId, 'returned');
        $this->logPrintfulEvent($printfulOrderId, 'package_returned', $data);
        
        return ['success' => true, 'processed' => true];
    }
    
    /**
     * Update Printful status in our database
     */
    private function updatePrintfulStatus(int $printfulOrderId, ?string $status): void
    {
        $sql = "UPDATE `ngn_2025`.`orders` 
                SET printful_status = :status, updated_at = NOW() 
                WHERE printful_order_id = :printfulId";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':printfulId', (string)$printfulOrderId);
        $stmt->execute();
    }
    
    /**
     * Log Printful event for debugging/audit
     * @param array<string,mixed> $data
     */
    private function logPrintfulEvent(int $printfulOrderId, string $eventType, array $data): void
    {
        // Find our order ID
        $sql = "SELECT id FROM `ngn_2025`.`orders` WHERE printful_order_id = :printfulId LIMIT 1";
        $stmt = $this->read->prepare($sql);
        $stmt->bindValue(':printfulId', (string)$printfulOrderId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $orderId = (int)$row['id'];
            $logSql = "INSERT INTO `ngn_2025`.`order_events` (order_id, event_type, description, actor_id, created_at)
                       VALUES (:orderId, :type, :desc, NULL, NOW())";
            $logStmt = $this->write->prepare($logSql);
            $logStmt->bindValue(':orderId', $orderId, PDO::PARAM_INT);
            $logStmt->bindValue(':type', 'printful_' . $eventType);
            $logStmt->bindValue(':desc', json_encode($data));
            $logStmt->execute();
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // NGN INTEGRATION HELPERS
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * Sync a local NGN product with Printful
     * @param int $ngnProductId Our product ID
     * @return array{success: bool, printful_id?: int, error?: string}
     */
    public function syncProductToPrintful(int $ngnProductId): array
    {
        // Get our product
        $sql = "SELECT * FROM `ngn_2025`.`products` WHERE id = :id";
        $stmt = $this->read->prepare($sql);
        $stmt->bindValue(':id', $ngnProductId, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }
        
        // If already synced, update; otherwise create
        if (!empty($product['printful_product_id'])) {
            $result = $this->updateSyncProduct((int)$product['printful_product_id'], [
                'sync_product' => [
                    'name' => $product['name'],
                    'thumbnail' => $product['image_url'],
                ],
            ]);
        } else {
            // Need to create - this requires variant mapping which is complex
            return ['success' => false, 'error' => 'Creating new Printful products requires manual catalog selection'];
        }
        
        if ($result['success']) {
            // Update sync status
            $updateSql = "UPDATE `ngn_2025`.`products` SET printful_sync_status = 'synced', updated_at = NOW() WHERE id = :id";
            $updateStmt = $this->write->prepare($updateSql);
            $updateStmt->bindValue(':id', $ngnProductId, PDO::PARAM_INT);
            $updateStmt->execute();
        }
        
        return $result;
    }
    
    /**
     * Import products from Printful store to NGN
     * @param string $entityType Owner type (artist/label/venue)
     * @param int $entityId Owner ID
     * @return array{success: bool, imported?: int, error?: string}
     */
    public function importProductsFromPrintful(string $entityType, int $entityId): array
    {
        $syncProducts = $this->getSyncProducts();
        if (!$syncProducts['success']) {
            return $syncProducts;
        }
        
        $imported = 0;
        foreach ($syncProducts['items'] as $syncProduct) {
            $printfulId = $syncProduct['id'] ?? null;
            if (!$printfulId) continue;
            
            // Check if already imported
            $checkSql = "SELECT id FROM `ngn_2025`.`products` WHERE printful_product_id = :pfId LIMIT 1";
            $checkStmt = $this->read->prepare($checkSql);
            $checkStmt->bindValue(':pfId', (string)$printfulId);
            $checkStmt->execute();
            if ($checkStmt->fetch()) {
                continue; // Already exists
            }
            
            // Get full product details
            $details = $this->getSyncProduct($printfulId);
            if (!$details['success']) continue;
            
            $productData = $details['product']['sync_product'] ?? [];
            $variants = $details['product']['sync_variants'] ?? [];
            
            // Create in NGN
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $productData['name'] ?? 'product')) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            
            $insertSql = "INSERT INTO `ngn_2025`.`products` (
                entity_type, entity_id, name, slug, description, category,
                base_price, currency, image_url, is_active, 
                printful_product_id, printful_sync_status, created_at, updated_at
            ) VALUES (
                :entityType, :entityId, :name, :slug, :desc, 'merch',
                :price, 'USD', :image, 1,
                :printfulId, 'synced', NOW(), NOW()
            )";
            
            // Use first variant price as base
            $basePrice = 0;
            if (!empty($variants[0]['retail_price'])) {
                $basePrice = (float)$variants[0]['retail_price'];
            }
            
            $insertStmt = $this->write->prepare($insertSql);
            $insertStmt->bindValue(':entityType', $entityType);
            $insertStmt->bindValue(':entityId', $entityId, PDO::PARAM_INT);
            $insertStmt->bindValue(':name', $productData['name'] ?? 'Imported Product');
            $insertStmt->bindValue(':slug', $slug);
            $insertStmt->bindValue(':desc', null);
            $insertStmt->bindValue(':price', $basePrice);
            $insertStmt->bindValue(':image', $productData['thumbnail_url'] ?? null);
            $insertStmt->bindValue(':printfulId', (string)$printfulId);
            $insertStmt->execute();
            
            $ngnProductId = (int)$this->write->lastInsertId();
            
            // Import variants
            foreach ($variants as $variant) {
                $variantSql = "INSERT INTO `ngn_2025`.`product_variants` (
                    product_id, sku, name, option_values, price_modifier,
                    printful_variant_id, is_active, created_at, updated_at
                ) VALUES (
                    :productId, :sku, :name, :options, :priceMod,
                    :printfulVarId, 1, NOW(), NOW()
                )";
                $variantStmt = $this->write->prepare($variantSql);
                $variantStmt->bindValue(':productId', $ngnProductId, PDO::PARAM_INT);
                $variantStmt->bindValue(':sku', $variant['sku'] ?? null);
                $variantStmt->bindValue(':name', $variant['name'] ?? 'Variant');
                $variantStmt->bindValue(':options', json_encode([
                    'size' => $variant['size'] ?? null,
                    'color' => $variant['color'] ?? null,
                ]));
                $priceMod = ((float)($variant['retail_price'] ?? $basePrice)) - $basePrice;
                $variantStmt->bindValue(':priceMod', $priceMod);
                $variantStmt->bindValue(':printfulVarId', (string)($variant['id'] ?? ''));
                $variantStmt->execute();
            }
            
            $imported++;
        }
        
        return ['success' => true, 'imported' => $imported];
    }
    
    /**
     * Create Printful order from NGN order
     * @param int $ngnOrderId Our order ID
     * @return array{success: bool, printful_order_id?: int, error?: string}
     */
    public function createPrintfulOrderFromNgn(int $ngnOrderId): array
    {
        // Get our order with items
        $orderSql = "SELECT o.*, 
                            oi.product_id, oi.variant_id, oi.quantity,
                            p.printful_product_id,
                            pv.printful_variant_id
                     FROM `ngn_2025`.`orders` o
                     JOIN `ngn_2025`.`order_items` oi ON oi.order_id = o.id
                     LEFT JOIN `ngn_2025`.`products` p ON p.id = oi.product_id
                     LEFT JOIN `ngn_2025`.`product_variants` pv ON pv.id = oi.variant_id
                     WHERE o.id = :orderId";
        $stmt = $this->read->prepare($orderSql);
        $stmt->bindValue(':orderId', $ngnOrderId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return ['success' => false, 'error' => 'Order not found'];
        }
        
        $order = $rows[0];
        
        // Build recipient
        $recipient = [
            'name' => $order['shipping_name'] ?? '',
            'address1' => $order['shipping_address1'] ?? '',
            'address2' => $order['shipping_address2'] ?? '',
            'city' => $order['shipping_city'] ?? '',
            'state_code' => $order['shipping_state'] ?? '',
            'country_code' => $order['shipping_country'] ?? 'US',
            'zip' => $order['shipping_zip'] ?? '',
            'phone' => $order['shipping_phone'] ?? '',
            'email' => $order['email'] ?? '',
        ];
        
        // Build items
        $items = [];
        foreach ($rows as $row) {
            $syncVariantId = $row['printful_variant_id'] ?? null;
            if (!$syncVariantId) {
                continue; // Skip non-Printful items
            }
            $items[] = [
                'sync_variant_id' => (int)$syncVariantId,
                'quantity' => (int)$row['quantity'],
                'external_id' => (string)$row['product_id'] . '-' . ($row['variant_id'] ?? '0'),
            ];
        }
        
        if (empty($items)) {
            return ['success' => false, 'error' => 'No Printful items in order'];
        }
        
        // Create order in Printful
        $result = $this->createOrder([
            'external_id' => $order['order_number'],
            'recipient' => $recipient,
            'items' => $items,
        ]);
        
        if ($result['success'] && !empty($result['order']['id'])) {
            // Update our order with Printful ID
            $updateSql = "UPDATE `ngn_2025`.`orders` 
                          SET printful_order_id = :pfOrderId, printful_status = 'pending', updated_at = NOW()
                          WHERE id = :orderId";
            $updateStmt = $this->write->prepare($updateSql);
            $updateStmt->bindValue(':pfOrderId', (string)$result['order']['id']);
            $updateStmt->bindValue(':orderId', $ngnOrderId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            return ['success' => true, 'printful_order_id' => $result['order']['id']];
        }
        
        return $result;
    }
    
    /**
     * Get store info / validate API key
     * @return array{success: bool, store?: array, error?: string}
     */
    public function getStoreInfo(): array
    {
        $result = $this->request('GET', '/store');
        if (!$result['success']) {
            return $result;
        }
        return ['success' => true, 'store' => $result['data'] ?? null];
    }
}
