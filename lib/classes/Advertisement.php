<?php

use App\Services\Subscription;

class Advertisement
{
    private $pdo;
    private Subscription $subscriptionService;

    public function __construct($pdo, Subscription $subscriptionService)
    {
        $this->pdo = $pdo;
        $this->subscriptionService = $subscriptionService;
    }

    public function pricesByLocationPerDay()
    {
        $oneTime = [
            'header' => '250',
            'footer' => '175',
            'item_square' => '150',
            'side' => '175',
            'item_horizontal' => '150',
            'callout' => '150',
            'all' => '700',
        ];

        $monthly = [
            'header' => '200',
            'footer' => '125',
            'item_square' => '100',
            'side' => '125',
            'item_horizontal' => '100',
            'callout' => '100',
            'all' => '500',
        ];

        return [
            'one_time' => $oneTime,
            'monthly' => $monthly,
        ];
    }

    public function getActiveAdsByLocation($location, $currentDateTime = null)
    {
        if ($currentDateTime === null) {
            $currentDateTime = new \DateTime();
        }

        $query = "SELECT id, title, slug, location, desktop_image, mobile_image, url, keywords FROM `ngn_2025`.`ads` WHERE location = :location AND active = 1 AND start_date <= :currentDate AND end_date >= :currentDate";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':location' => $location,
            ':currentDate' => $currentDateTime->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementHit($adId)
    {
        $query = "UPDATE `ngn_2025`.`ads` SET hits = IFNULL(hits, 0) + 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':id' => $adId]);
    }

    public function displayFullPageAd($ad)
    {
        return "
            <div class='ad full' data-ad-id='{$ad['Id']}'>
                <a href='{$ad['Url']}' target='_blank'>
                    <img src='{$GLOBALS['Default']['Baseurl']}lib/images/ads/{$ad['Slug']}/{$ad['DesktopImage']}' alt='{$ad['Title']}'>
                </a>
                <a href='{$GLOBALS['Default']['Baseurl']}advertising' class='text-muted small'>[ ADVERTISEMENT ]</a>
            </div>
        ";
    }

    public function displaySideAd($ad)
    {
        return "
            <div class='ad side' data-ad-id='{$ad['Id']}'>
                <a href='{$ad['Url']}' target='_blank'>
                    <img src='{$GLOBALS['Default']['Baseurl']}lib/images/ads/{$ad['Slug']}/{$ad['MobileImage']}' alt='{$ad['Title']}'>
                </a>
                <a href='{$GLOBALS['Default']['Baseurl']}advertising' class='text-muted small'>[ ADVERTISEMENT ]</a>
            </div>
        ";
    }

    public function displayInlineItemAd($ad)
    {
        return "
            <div class='ad content' data-ad-id='{$ad['Id']}'>
                <a href='{$ad['Url']}' target='_blank'>
                    <img src='{$GLOBALS['Default']['Baseurl']}lib/images/ads/{$ad['Slug']}/{$ad['DesktopImage']}' alt='{$ad['Title']}'>
                </a>
                <a href='{$GLOBALS['Default']['Baseurl']}advertising' class='text-muted small'>[ ADVERTISEMENT ]</a>
            </div>
        ";
    }

    public function processSubscriptionPurchase($userId, $subscriptionPlan, $customerId)
    {
        try {
            // Use Subscription service to create the subscription
            $subscription = $this->subscriptionService->createSubscription($customerId, $subscriptionPlan);

            // Save subscription details to the database
            $this->saveSubscriptionToDatabase($userId, $subscription);

        } catch (\Exception $e) {
            throw new \Exception('Error processing subscription: ' . $e->getMessage());
        }
    }

    public function processSingleAdPurchase($adDetails, $stripeToken)
    {
        try {
            // Use the Subscription service to process the one-time payment
            $paymentIntent = $this->subscriptionService->getStripeService()->createPaymentIntent([
                'amount' => $adDetails['amount'],
                'currency' => 'usd',
                'metadata' => [
                    'user_id' => $adDetails['userId'],
                    'ad_title' => $adDetails['title'],
                ],
            ]);

            // Save ad purchase details to the database
            $this->saveAdPurchaseToDatabase($adDetails, $paymentIntent);

        } catch (\Exception $e) {
            throw new \Exception('Error processing payment: ' . $e->getMessage());
        }
    }

    public function showAdBasedOnUserPreferences($ads, $userCookies)
    {
        $tailoredAds = $this->showAdByUserPreferences($ads, $userCookies);

        shuffle($tailoredAds);

        return $tailoredAds;
    }

    private function saveSubscriptionToDatabase($userId, $subscription)
    {
        $query = "INSERT INTO `ngn_2025`.`subscriptions` (user_id, subscription_id, status, start_date, end_date) VALUES (:userId, :subId, :status, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':userId' => $userId,
            ':subId' => $subscription['id'],
            ':status' => $subscription['status'],
        ]);
    }

    private function saveAdPurchaseToDatabase($adDetails, $paymentIntent)
    {
        $query = "INSERT INTO `ngn_2025`.`single_ad_purchases` (user_id, ad_title, amount_paid, payment_intent_id, created_at) VALUES (:userId, :title, :amount, :paymentId, NOW())";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':userId' => $adDetails['userId'],
            ':title' => $adDetails['title'],
            ':amount' => $adDetails['amount'],
            ':paymentId' => $paymentIntent['id'],
        ]);
    }

    private function getUserEmail($userId)
    {
        $query = "SELECT email FROM `ngn_2025`.`users` WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchColumn();
    }

    private function showAdByUserPreferences($ads, $userCookies)
    {
        $tailoredAds = [];

        foreach ($ads as $ad) {
            if ($this->matchesUserPreferences($ad, $userCookies)) {
                $tailoredAds[] = $ad;
            }
        }

        return $tailoredAds;
    }

    private function matchesUserPreferences($ad, $userCookies)
    {
        if (isset($userCookies['interests'])) {
            $interests = explode(',', $userCookies['interests']);
            foreach ($interests as $interest) {
                if (stripos($ad['keywords'], $interest) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}