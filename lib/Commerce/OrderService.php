<?php
namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Royalties\PayoutEngine;
use PDO;

/**
 * OrderService - Manages orders, order items, and order lifecycle
 * Integrates with Stripe payments and supports multi-seller orders
 */
class OrderService
{
    private PDO $read;
    private PDO $write;
    private ProductService $productService;
    private PrintfulService $printfulService;
    private PayoutEngine $payoutEngine;

    // Order statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public function __construct(Config $config, ProductService $productService, PrintfulService $printfulService)
    {
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->productService = $productService;
        $this->printfulService = $printfulService;
        $this->payoutEngine = new PayoutEngine($config);
    }

    /**
     * List orders with filters, pagination
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(
        ?int $userId = null,
        ?string $status = null,
        ?string $entityType = null,
        ?int $entityId = null,
        int $page = 1,
        int $perPage = 20,
        string $sort = 'created_at',
        string $dir = 'desc'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['created_at', 'total', 'status'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'o.user_id = :userId';
            $params[':userId'] = $userId;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        // Filter by seller (entity) via order_items
        if ($entityType !== null && $entityId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM `ngn_2025`.`order_items` oi 
                        WHERE oi.order_id = o.id AND oi.seller_type = :sellerType AND oi.seller_id = :sellerId)';
            $params[':sellerType'] = $entityType;
            $params[':sellerId'] = $entityId;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $perPage;

        $sortCol = match($sort) {
            'total' => 'o.total',
            'status' => 'o.status',
            default => 'o.created_at',
        };

        try {
            $sql = "SELECT o.id, o.order_number, o.user_id, o.email, o.status,
                           o.subtotal, o.discount_amount, o.tax_amount, o.shipping_amount, o.total,
                           o.currency, o.coupon_id, o.coupon_code,
                           o.shipping_name, o.shipping_address1, o.shipping_city, o.shipping_state,
                           o.shipping_zip, o.shipping_country, o.shipping_phone,
                           o.stripe_payment_intent_id, o.stripe_charge_id,
                           o.notes, o.created_at, o.updated_at
                    FROM `ngn_2025`.`orders` o
                    $whereSql
                    ORDER BY $sortCol $dir
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Count
            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`orders` o $whereSql";
            $cStmt = $this->read->prepare($countSql);
            foreach ($params as $k => $v) {
                $cStmt->bindValue($k, $v);
            }
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            $rows = [];
            $total = 0;
        }

        $items = array_map([$this, 'normalizeOrder'], $rows);
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get single order by ID with items
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT o.id, o.order_number, o.user_id, o.email, o.status,
                           o.subtotal, o.discount_amount, o.tax_amount, o.shipping_amount, o.total,
                           o.currency, o.coupon_id, o.coupon_code,
                           o.shipping_name, o.shipping_address1, o.shipping_address2,
                           o.shipping_city, o.shipping_state, o.shipping_zip, o.shipping_country, o.shipping_phone,
                           o.billing_name, o.billing_address1, o.billing_address2,
                           o.billing_city, o.billing_state, o.billing_zip, o.billing_country,
                           o.stripe_payment_intent_id, o.stripe_charge_id, o.stripe_refund_id,
                           o.printful_order_id, o.printful_status,
                           o.notes, o.metadata, o.created_at, o.updated_at
                    FROM `ngn_2025`.`orders` o
                    WHERE o.id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$row) return null;

            $order = $this->normalizeOrder($row);
            $order['items'] = $this->getItems($id);
            $order['events'] = $this->getEvents($id);

            return $order;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get order by order number
     * @return array<string,mixed>|null
     */
    public function getByOrderNumber(string $orderNumber): ?array
    {
        try {
            $sql = "SELECT id FROM `ngn_2025`.`orders` WHERE order_number = :num LIMIT 1";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':num', $orderNumber);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            return $this->get((int)$row['id']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a new order
     * @param array<string,mixed> $data
     * @param array<int, array<string,mixed>> $items
     * @return array{success: bool, id?: int, order_number?: string, error?: string}
     */
    public function create(array $data, array $items): array
    {
        if (empty($items)) {
            return ['success' => false, 'error' => 'Order must have at least one item'];
        }
        if (empty($data['email'])) {
            return ['success' => false, 'error' => 'Email is required'];
        }

        $orderNumber = $this->generateOrderNumber();

        // Calculate totals and costs
        $subtotal = 0;
        $totalCost = 0;
        $enrichedItems = [];

        foreach ($items as $item) {
            $product = $this->productService->get($item['product_id']);
            if (!$product) {
                return ['success' => false, 'error' => "Product with ID {$item['product_id']} not found."];
            }

            $variant = null;
            if (!empty($item['variant_id'])) {
                foreach ($product['variants'] as $v) {
                    if ($v['id'] === $item['variant_id']) {
                        $variant = $v;
                        break;
                    }
                }
            }

            $price = $variant['price_cents'] ?? $product['price_cents'];
            $baseCost = $variant['cost_cents'] ?? $product['cost_cents'];
            
            $itemSubtotal = $price * $item['quantity'];
            $subtotal += $itemSubtotal;

            $item['price'] = $price / 100;
            $item['base_cost_cents'] = $baseCost;
            
            // For now, print and shipping costs are not integrated. This can be added later.
            $item['print_cost_cents'] = 0;
            $item['shipping_cost_cents'] = 0;

            $lineItemTotalCost = ($baseCost + $item['print_cost_cents'] + $item['shipping_cost_cents']) * $item['quantity'];
            $item['line_item_total_cost_cents'] = $lineItemTotalCost;

            $totalCost += $lineItemTotalCost;
            $enrichedItems[] = $item;
        }

        $discountAmount = (float)($data['discount_amount'] ?? 0);
        $taxAmount = (float)($data['tax_amount'] ?? 0);
        $shippingAmount = (float)($data['shipping_amount'] ?? 0);
        $total = $subtotal - $discountAmount + $taxAmount + $shippingAmount;

        try {
            $this->write->beginTransaction();

            $sql = "INSERT INTO `ngn_2025`.`orders` (
                        order_number, user_id, email, status,
                        subtotal, discount_amount, tax_amount, shipping_amount, total, total_cost_cents, currency,
                        coupon_id, coupon_code,
                        shipping_name, shipping_address1, shipping_address2,
                        shipping_city, shipping_state, shipping_zip, shipping_country, shipping_phone,
                        billing_name, billing_address1, billing_address2,
                        billing_city, billing_state, billing_zip, billing_country,
                        stripe_payment_intent_id, notes, metadata,
                        created_at, updated_at
                    ) VALUES (
                        :order_number, :user_id, :email, :status,
                        :subtotal, :discount_amount, :tax_amount, :shipping_amount, :total, :total_cost_cents, :currency,
                        :coupon_id, :coupon_code,
                        :shipping_name, :shipping_address1, :shipping_address2,
                        :shipping_city, :shipping_state, :shipping_zip, :shipping_country, :shipping_phone,
                        :billing_name, :billing_address1, :billing_address2,
                        :billing_city, :billing_state, :billing_zip, :billing_country,
                        :stripe_payment_intent_id, :notes, :metadata,
                        NOW(), NOW()
                    )";

            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':order_number', $orderNumber);
            $stmt->bindValue(':user_id', isset($data['user_id']) ? (int)$data['user_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':status', $data['status'] ?? self::STATUS_PENDING);
            $stmt->bindValue(':subtotal', $subtotal);
            $stmt->bindValue(':discount_amount', $discountAmount);
            $stmt->bindValue(':tax_amount', $taxAmount);
            $stmt->bindValue(':shipping_amount', $shippingAmount);
            $stmt->bindValue(':total', $total);
            $stmt->bindValue(':total_cost_cents', $totalCost);
            $stmt->bindValue(':currency', $data['currency'] ?? 'USD');
            $stmt->bindValue(':coupon_id', $data['coupon_id'] ?? null);
            $stmt->bindValue(':coupon_code', $data['coupon_code'] ?? null);
            $stmt->bindValue(':shipping_name', $data['shipping_name'] ?? null);
            $stmt->bindValue(':shipping_address1', $data['shipping_address1'] ?? null);
            $stmt->bindValue(':shipping_address2', $data['shipping_address2'] ?? null);
            $stmt->bindValue(':shipping_city', $data['shipping_city'] ?? null);
            $stmt->bindValue(':shipping_state', $data['shipping_state'] ?? null);
            $stmt->bindValue(':shipping_zip', $data['shipping_zip'] ?? null);
            $stmt->bindValue(':shipping_country', $data['shipping_country'] ?? 'US');
            $stmt->bindValue(':shipping_phone', $data['shipping_phone'] ?? null);
            $stmt->bindValue(':billing_name', $data['billing_name'] ?? null);
            $stmt->bindValue(':billing_address1', $data['billing_address1'] ?? null);
            $stmt->bindValue(':billing_address2', $data['billing_address2'] ?? null);
            $stmt->bindValue(':billing_city', $data['billing_city'] ?? null);
            $stmt->bindValue(':billing_state', $data['billing_state'] ?? null);
            $stmt->bindValue(':billing_zip', $data['billing_zip'] ?? null);
            $stmt->bindValue(':billing_country', $data['billing_country'] ?? 'US');
            $stmt->bindValue(':stripe_payment_intent_id', $data['stripe_payment_intent_id'] ?? null);
            $stmt->bindValue(':notes', $data['notes'] ?? null);
            $stmt->bindValue(':metadata', isset($data['metadata']) ? json_encode($data['metadata']) : null);
            $stmt->execute();

            $orderId = (int)$this->write->lastInsertId();

            // Insert items
            foreach ($enrichedItems as $item) {
                $this->createItem($orderId, $item);
            }

            // Log creation event
            $this->logEvent($orderId, 'order_created', 'Order created', $data['created_by'] ?? null);

            $this->write->commit();
            return ['success' => true, 'id' => $orderId, 'order_number' => $orderNumber];
        } catch (\Throwable $e) {
            $this->write->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update order status with event logging
     * @return array{success: bool, error?: string}
     */
    public function updateStatus(int $orderId, string $status, ?string $notes = null, ?int $actorId = null): array
    {
        $validStatuses = [
            self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_PROCESSING,
            self::STATUS_SHIPPED, self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_REFUNDED
        ];

        if (!in_array($status, $validStatuses, true)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        try {
            $sql = "UPDATE `ngn_2025`.`orders` SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            // Log event
            $this->logEvent($orderId, 'status_changed', "Status changed to $status" . ($notes ? ": $notes" : ''), $actorId);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update Stripe payment info after successful charge
     * @return array{success: bool, error?: string}
     */
    public function updatePayment(int $orderId, string $paymentIntentId, ?string $chargeId = null): array
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET stripe_payment_intent_id = :pi, stripe_charge_id = :charge, 
                        status = :status, paid_at = NOW(), updated_at = NOW() 
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':pi', $paymentIntentId);
            $stmt->bindValue(':charge', $chargeId);
            $stmt->bindValue(':status', self::STATUS_PAID);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            $this->logEvent($orderId, 'payment_received', "Payment received via Stripe ($paymentIntentId)");

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update shipping tracking info
     * @return array{success: bool, error?: string}
     */
    public function updateShipping(int $orderId, string $carrier, string $trackingNumber, ?int $actorId = null): array
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET shipping_carrier = :carrier, tracking_number = :tracking, 
                        shipped_at = NOW(), status = :status, updated_at = NOW() 
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':carrier', $carrier);
            $stmt->bindValue(':tracking', $trackingNumber);
            $stmt->bindValue(':status', self::STATUS_SHIPPED);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            $this->logEvent($orderId, 'shipped', "Shipped via $carrier (tracking: $trackingNumber)", $actorId);

            // Trigger Foundry Settlement (NGN 3.0)
            $this->payoutEngine->processFoundrySettlement($orderId);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process refund
     * @return array{success: bool, error?: string}
     */
    public function refund(int $orderId, float $amount, string $reason, ?string $stripeRefundId = null, ?int $actorId = null): array
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET refund_amount = COALESCE(refund_amount, 0) + :amount,
                        stripe_refund_id = COALESCE(:refund_id, stripe_refund_id),
                        status = :status, refunded_at = NOW(), updated_at = NOW() 
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':amount', $amount);
            $stmt->bindValue(':refund_id', $stripeRefundId);
            $stmt->bindValue(':status', self::STATUS_REFUNDED);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            $this->logEvent($orderId, 'refunded', "Refund of $" . number_format($amount, 2) . ": $reason", $actorId);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get order items
     * @return array<int, array<string,mixed>>
     */
    public function getItems(int $orderId): array
    {
        try {
            $sql = "SELECT oi.id, oi.order_id, oi.product_id, oi.variant_id,
                           oi.seller_type, oi.seller_id, oi.sku, oi.name, oi.options,
                           oi.quantity, oi.price, oi.total, oi.fulfillment_status,
                           oi.printful_item_id, oi.created_at
                    FROM `ngn_2025`.`order_items` oi
                    WHERE oi.order_id = :orderId
                    ORDER BY oi.id ASC";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':orderId', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'order_id' => (int)$row['order_id'],
                    'product_id' => isset($row['product_id']) ? (int)$row['product_id'] : null,
                    'variant_id' => isset($row['variant_id']) ? (int)$row['variant_id'] : null,
                    'seller_type' => $row['seller_type'],
                    'seller_id' => isset($row['seller_id']) ? (int)$row['seller_id'] : null,
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'options' => $row['options'] ? json_decode($row['options'], true) : null,
                    'quantity' => (int)$row['quantity'],
                    'price' => (float)$row['price'],
                    'total' => (float)$row['total'],
                    'fulfillment_status' => $row['fulfillment_status'],
                    'printful_item_id' => $row['printful_item_id'],
                    'created_at' => $row['created_at'],
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get order events/audit log
     * @return array<int, array<string,mixed>>
     */
    public function getEvents(int $orderId): array
    {
        try {
            $sql = "SELECT e.id, e.order_id, e.event_type, e.description, e.actor_id, e.created_at
                    FROM `ngn_2025`.`order_events` e
                    WHERE e.order_id = :orderId
                    ORDER BY e.created_at DESC";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':orderId', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'order_id' => (int)$row['order_id'],
                    'event_type' => $row['event_type'],
                    'description' => $row['description'],
                    'actor_id' => isset($row['actor_id']) ? (int)$row['actor_id'] : null,
                    'created_at' => $row['created_at'],
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get order statistics for a seller
     * @return array<string,mixed>
     */
    public function getSellerStats(string $entityType, int $entityId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT o.id) AS total_orders,
                        SUM(oi.total) AS total_revenue,
                        SUM(oi.quantity) AS total_items_sold,
                        COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) AS pending_orders,
                        COUNT(DISTINCT CASE WHEN o.status = 'processing' THEN o.id END) AS processing_orders
                    FROM `ngn_2025`.`order_items` oi
                    JOIN `ngn_2025`.`orders` o ON o.id = oi.order_id
                    WHERE oi.seller_type = :type AND oi.seller_id = :id
                      AND o.status NOT IN ('cancelled', 'refunded')";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':type', $entityType);
            $stmt->bindValue(':id', $entityId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_orders' => (int)($row['total_orders'] ?? 0),
                'total_revenue' => (float)($row['total_revenue'] ?? 0),
                'total_items_sold' => (int)($row['total_items_sold'] ?? 0),
                'pending_orders' => (int)($row['pending_orders'] ?? 0),
                'processing_orders' => (int)($row['processing_orders'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0.0,
                'total_items_sold' => 0,
                'pending_orders' => 0,
                'processing_orders' => 0,
            ];
        }
    }

    /**
     * Create order item
     * @param array<string,mixed> $item
     */
    private function createItem(int $orderId, array $item): void
    {
        $quantity = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $total = $quantity * $price;

        $sql = "INSERT INTO `ngn_2025`.`order_items` (
                    order_id, product_id, variant_id, seller_type, seller_id,
                    sku, name, options, quantity, price, total,
                    base_cost_cents, print_cost_cents, shipping_cost_cents, line_item_total_cost_cents,
                    fulfillment_status, created_at
                ) VALUES (
                    :order_id, :product_id, :variant_id, :seller_type, :seller_id,
                    :sku, :name, :options, :quantity, :price, :total,
                    :base_cost_cents, :print_cost_cents, :shipping_cost_cents, :line_item_total_cost_cents,
                    :fulfillment_status, NOW()
                )";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', isset($item['product_id']) ? (int)$item['product_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':variant_id', isset($item['variant_id']) ? (int)$item['variant_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':seller_type', $item['seller_type'] ?? null);
        $stmt->bindValue(':seller_id', isset($item['seller_id']) ? (int)$item['seller_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':sku', $item['sku'] ?? null);
        $stmt->bindValue(':name', $item['name'] ?? 'Product');
        $stmt->bindValue(':options', isset($item['options']) ? json_encode($item['options']) : null);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':price', $price);
        $stmt->bindValue(':total', $total);
        $stmt->bindValue(':base_cost_cents', (int)($item['base_cost_cents'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':print_cost_cents', (int)($item['print_cost_cents'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':shipping_cost_cents', (int)($item['shipping_cost_cents'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':line_item_total_cost_cents', (int)($item['line_item_total_cost_cents'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':fulfillment_status', $item['fulfillment_status'] ?? 'pending');
        $stmt->execute();
    }

    /**
     * Log order event
     */
    private function logEvent(int $orderId, string $type, string $description, ?int $actorId = null): void
    {
        try {
            $sql = "INSERT INTO `ngn_2025`.`order_events` (order_id, event_type, description, actor_id, created_at)
                    VALUES (:order_id, :type, :desc, :actor, NOW())";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':desc', $description);
            $stmt->bindValue(':actor', $actorId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail event logging - don't break main operation
        }
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'NGN';
        $date = date('ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Normalize order row
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeOrder(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'order_number' => $row['order_number'] ?? null,
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'email' => $row['email'] ?? null,
            'status' => $row['status'] ?? self::STATUS_PENDING,
            'subtotal' => isset($row['subtotal']) ? (float)$row['subtotal'] : 0.0,
            'discount_amount' => isset($row['discount_amount']) ? (float)$row['discount_amount'] : 0.0,
            'tax_amount' => isset($row['tax_amount']) ? (float)$row['tax_amount'] : 0.0,
            'shipping_amount' => isset($row['shipping_amount']) ? (float)$row['shipping_amount'] : 0.0,
            'total' => isset($row['total']) ? (float)$row['total'] : 0.0,
            'currency' => $row['currency'] ?? 'USD',
            'coupon_id' => isset($row['coupon_id']) ? (int)$row['coupon_id'] : null,
            'coupon_code' => $row['coupon_code'] ?? null,
            'shipping' => [
                'name' => $row['shipping_name'] ?? null,
                'address1' => $row['shipping_address1'] ?? null,
                'address2' => $row['shipping_address2'] ?? null,
                'city' => $row['shipping_city'] ?? null,
                'state' => $row['shipping_state'] ?? null,
                'zip' => $row['shipping_zip'] ?? null,
                'country' => $row['shipping_country'] ?? null,
                'phone' => $row['shipping_phone'] ?? null,
            ],
            'billing' => [
                'name' => $row['billing_name'] ?? null,
                'address1' => $row['billing_address1'] ?? null,
                'address2' => $row['billing_address2'] ?? null,
                'city' => $row['billing_city'] ?? null,
                'state' => $row['billing_state'] ?? null,
                'zip' => $row['billing_zip'] ?? null,
                'country' => $row['billing_country'] ?? null,
            ],
            'stripe_payment_intent_id' => $row['stripe_payment_intent_id'] ?? null,
            'stripe_charge_id' => $row['stripe_charge_id'] ?? null,
            'stripe_refund_id' => $row['stripe_refund_id'] ?? null,
            'printful_order_id' => $row['printful_order_id'] ?? null,
            'printful_status' => $row['printful_status'] ?? null,
            'notes' => $row['notes'] ?? null,
            'metadata' => isset($row['metadata']) ? json_decode($row['metadata'], true) : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
