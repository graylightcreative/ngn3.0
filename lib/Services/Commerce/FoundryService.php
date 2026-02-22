<?php
namespace NGN\Lib\Services\Commerce;

/**
 * Foundry Service - Handles order handshake with the DTF production layer.
 * NGN 3.0 Foundry Integration (Vendor Model).
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Royalty\RoyaltyLedgerService;
use NGN\Lib\Fans\SubscriptionService;
use PDO;

class FoundryService
{
    private $config;
    private $pdo;
    private $logger;
    private $ledger;
    private $subs;
    private $vendorEmail = "kieran@starrship1.com"; 

    // Business Rules
    private const SPARK_LISTING_FEE = 500; // $5.00 USD
    private const MENS_GARMENT = "bella-+-canvas-3001-heather-black-mens-blank-tshirt.jpg";
    private const WOMENS_GARMENT = "bella-+-canvas-3001-heather-black-womens-blank-tshirt.jpeg";

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->logger = LoggerFactory::getLogger('foundry_integration');
        $this->ledger = new RoyaltyLedgerService($config);
        $this->subs = new SubscriptionService($config);
    }

    /**
     * Handshake: Submit Order to Production Layer via Email
     */
    public function submitOrder(int $orderId): array
    {
        try {
            $order = $this->getOrderData($orderId);
            if (!$order) throw new \Exception("Order not found: {$orderId}");

            // Generate Production Ticket Content
            $ticket = $this->generateProductionTicket($order);

            // Send Production Ticket Email to Kieran's Business
            $emailResult = $this->sendProductionTicketEmail($order['order_number'], $ticket);

            return [
                'success' => true,
                'email_sent' => $emailResult
            ];

        } catch (\Throwable $e) {
            $this->logger->error("Foundry Handshake Failed for Order #{$orderId}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listing Gate: Validate if user can list a new design
     */
    public function validateListing(int $userId): array
    {
        // 1. Check Subscription Slots
        $stmt = $this->pdo->prepare("
            SELECT st.merch_design_slots 
            FROM `ngn_2025`.`user_fan_subscriptions` ufs
            JOIN `ngn_2025`.`subscription_tiers` st ON ufs.tier_id = st.id
            WHERE ufs.user_id = ? AND ufs.status = 'active'
            ORDER BY st.merch_design_slots DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $slotsAvailable = (int)$stmt->fetchColumn() ?: 0;

        // 2. Count current slots used
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`products` WHERE design_owner_id = ? AND fulfillment_source = 'foundry'");
        $stmtCount->execute([$userId]);
        $slotsUsed = (int)$stmtCount->fetchColumn();

        if ($slotsAvailable > $slotsUsed) {
            return [
                'can_list' => true,
                'method' => 'subscription',
                'remaining_slots' => $slotsAvailable - $slotsUsed,
                'cost_sparks' => 0
            ];
        }

        // 3. Fallback: Check Spark Balance
        $sparkBalance = $this->ledger->getSparkBalance($userId);
        
        return [
            'can_list' => ($sparkBalance >= self::SPARK_LISTING_FEE),
            'method' => 'sparks',
            'spark_balance' => $sparkBalance,
            'cost_sparks' => self::SPARK_LISTING_FEE
        ];
    }

    /**
     * Execute Listing: Consume a slot or burn sparks
     */
    public function processListing(int $userId, string $method): bool
    {
        if ($method === 'sparks') {
            $this->ledger->recordSpark(
                $userId, 
                -self::SPARK_LISTING_FEE, 
                'Foundry Design Listing Fee'
            );
            $this->logger->info("User {$userId} burned " . self::SPARK_LISTING_FEE . " sparks for a Foundry listing.");
        }
        return true;
    }

    public function getGarmentMocks(): array
    {
        return [
            'mens' => '/lib/images/shirt-blanks/' . self::MENS_GARMENT,
            'womens' => '/lib/images/shirt-blanks/' . self::WOMENS_GARMENT
        ];
    }

    private function getOrderData(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ngn_2025`.`orders` WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $stmtItems = $this->pdo->prepare("
                SELECT oi.*, p.fulfillment_source, p.image_url as artwork_url
                FROM `ngn_2025`.`order_items` oi
                JOIN `ngn_2025`.`products` p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.fulfillment_source = 'foundry'
            ");
            $stmtItems->execute([$orderId]);
            $order['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }

        return $order;
    }

    private function generateProductionTicket(array $order): string
    {
        $ticket = "NGN PRODUCTION TICKET // ORDER #" . ($order['order_number'] ?? $order['Number']) . "\n";
        $ticket .= "--------------------------------------------------\n";
        $ticket .= "SHIPPING:\n";
        $ticket .= ($order['shipping_name'] ?? '') . "\n";
        $ticket .= ($order['shipping_address1'] ?? '') . "\n";
        $ticket .= ($order['shipping_city'] ?? '') . ", " . ($order['shipping_state'] ?? '') . " " . ($order['shipping_zip'] ?? '') . "\n\n";
        
        $ticket .= "ITEMS:\n";
        foreach ($order['items'] as $item) {
            $ticket .= "- {$item['quantity']}x {$item['name']} (SKU: {$item['sku']})\n";
            $ticket .= "  Garment: BELLA + CANVAS 3001\n";
            $ticket .= "  Artwork: {$item['artwork_url']}\n\n";
        }
        
        return $ticket;
    }

    private function sendProductionTicketEmail(string $orderNumber, string $ticket): bool
    {
        // Placeholder for Email Service integration
        return true;
    }
}
