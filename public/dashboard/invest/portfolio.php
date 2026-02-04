<?php

// Ensure necessary NGN bootstrap and configurations are loaded.
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json};
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Commerce\InvestmentService; // Import InvestmentService
use PDO;

// Basic page setup
$config = new Config(); // Load configuration
$pageTitle = 'My Investment Portfolio';

// --- Fetch User Data and Investments ---
$userId = null;
$userName = 'Valued Investor'; // Default placeholder
$activeInvestments = [];
$totalActiveInvestment = 0.00; // In dollars
$totalAccruedInterest = 0.00; // In dollars
$totalPayout = 0.00; // In dollars

try {
    $request = new Request(); // Ensure Request is available
    $authHeader = $request->header('Authorization');

    if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
        $tokenSvc = new TokenService($config);
        $claims = $tokenSvc->decode(trim(substr($authHeader, 7)));
        $sub = (string)($claims['sub'] ?? '');

        if ($sub) {
            // Resolve numeric user id
            if (ctype_digit($sub)) {
                $userId = (int)$sub;
            } else {
                // If email is used, fetch user ID from DB
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
                $stmt = $pdo->prepare("SELECT Id, FirstName, LastName FROM `Users` WHERE Email = ? LIMIT 1");
                $stmt->execute([$sub]);
                $user = $stmt->fetch();
                if ($user) {
                    $userId = (int)$user['Id'];
                    $userName = trim($user['FirstName'] . ' ' . $user['LastName']);
                    if (empty($userName)) $userName = 'Valued Investor';
                }
            }

            if ($userId) {
                // Fetch active investments for the user
                $pdoRankings = ConnectionFactory::named($config, 'rankings2025'); // Use the same DB for investments
                $stmt = $pdoRankings->prepare(
                    "SELECT Id, amount, apy, term_years, status, created_at FROM `investments` 
                     WHERE `user_id` = :userId AND `status` = 'active' ORDER BY created_at ASC"
                );
                $stmt->execute(['':userId' => $userId]);
                $activeInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate totals
                foreach ($activeInvestments as $investment) {
                    $principal = (float)$investment['amount']; // Amount is in cents
                    $apy = (float)$investment['apy'];
                    $termYears = (int)$investment['term_years'];
                    $purchaseDate = new DateTime($investment['created_at']);
                    $today = new DateTime();
                    $daysSincePurchase = $today->diff($purchaseDate)->days;
                    
                    // Calculate accrued interest dynamically
                    // We use $principal / 100 to get dollar value for calculation, then multiply by interest rate and time fraction.
                    // The result will be in dollars. Store everything in dollars for display.
                    $principalInDollars = $principal / 100;
                    $accruedInterest = $principalInDollars * $apy * ($daysSincePurchase / 365.25); // Approximate days in year

                    $totalActiveInvestment += $principalInDollars;
                    $totalAccruedInterest += $accruedInterest;
                }
                $totalPayout = $totalActiveInvestment + $totalAccruedInterest;
            }
        }
    } 
} catch (\Throwable $e) {
    // Ignore auth errors for page load, API will handle them.
    // Log error if needed: error_log("Could not fetch user or investment data on page load: " . $e->getMessage());
    // Set default values or show an error message on the page if critical data is missing
    if (!$userId) {
        $userName = 'Guest'; // Indicate user is not logged in or ID not found
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NGN 2.0</title>
    <!-- Cleaner Spotify Killer Theme CSS -->
    <style>
        body { 
            background-color: #121212; 
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .sk-portfolio-card {
            background-color: #1e1e1e;
            border-radius: 12px;
            padding: 40px;
            margin: 30px auto;
            max-width: 700px;
            box-shadow: 0 4px 15px rgba(0, 255, 102, 0.4); /* Subtle neon green glow */
            transition: box-shadow 0.3s ease;
        }
        .sk-portfolio-card:hover { box-shadow: 0 4px 25px rgba(0, 255, 102, 0.6); }
        .sk-portfolio-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        .sk-portfolio-header h1 {
            color: #00fff2; /* Neon cyan */
            margin: 0;
            font-size: 2.2em;
        }
        .sk-portfolio-header p {
            color: #bbb;
            font-size: 1.1em;
            margin-top: 5px;
        }
        .sk-summary-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        .sk-summary-section:last-child { border-bottom: none; padding-bottom: 0; }
        .sk-summary-section h2 {
            color: #00fff2; /* Neon cyan */
            font-size: 1.6em;
            margin-bottom: 15px;
            flex-basis: 100%; /* Ensure it takes full width */
        }
        .sk-summary-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 180px; /* Minimum width for items */
        }
        .sk-summary-item strong {
            color: #fff;
            font-size: 1.1em;
        }
        .sk-summary-item span {
            color: #ddd;
            font-size: 1.3em;
            font-weight: bold;
        }
        .sk-perk-badge-display {
            margin-top: 15px;
            text-align: center;
            flex-basis: 100%; /* Ensure it takes full width */
        }
        .sk-perk-badge {
            display: inline-block;
            background-color: #ff00aa; /* Hot magenta */
            color: #000;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 1em;
            font-weight: bold;
            transition: transform 0.2s ease, opacity 0.2s ease, visibility 0.2s ease;
        }
        .sk-perk-badge.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1.05);
        }
        .sk-investment-list {
            margin-top: 20px;
            width: 100%;
        }
        .sk-investment-list th, .sk-investment-list td {
            border: 1px solid #444;
            padding: 12px;
            text-align: right;
        }
        .sk-investment-list th {
            background-color: #2a2a2a;
            color: #00fff2; /* Neon cyan */
            text-align: center;
            font-weight: bold;
        }
        .sk-investment-list td {
            background-color: #1e1e1e;
        }
        .sk-investment-list td:first-child, .sk-investment-list th:first-child {
            text-align: center;
            font-weight: bold;
            color: #fff;
        }
        .sk-footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.85em;
            color: #888;
            border-top: 1px solid #333;
            padding-top: 20px;
        }
        .sk-print-button {
            display: block;
            width: fit-content;
            margin: 30px auto 0;
            background: linear-gradient(90deg, #00f0ff, #00ff6e);
            color: #000;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 0 8px rgba(0, 255, 102, 0.7), 0 0 20px rgba(0, 255, 102, 0.4);
            font-size: 1.1em;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
        }
        .sk-print-button:hover { box-shadow: 0 0 12px rgba(0, 255, 102, 0.9), 0 0 30px rgba(0, 255, 102, 0.6); transform: translateY(-3px); }
        .sk-legal-disclaimer {
            font-style: italic;
            color: #aaa;
        }
    </style>
</head>
<body>

    <div class="sk-portfolio-card">
        <div class="sk-portfolio-header">
            <h1>NGN Community Funding</h1>
            <p>Your Investment Portfolio</p>
        </div>

        <div class="sk-summary-section">
            <h2>Portfolio Summary</h2>
            <div class="sk-summary-item">
                <strong>Total Active Investment</strong>
                <span id="total-active-investment"></span>
            </div>
            <div class="sk-summary-item">
                <strong>Total Accrued Interest</strong>
                <span id="total-accrued-interest"></span>
            </div>
            <div class="sk-summary-item">
                <strong>Total Payout Estimate</strong>
                <span id="total-payout-estimate"></span>
            </div>
            <div class="sk-perk-badge-display">
                <span id="elite-investor-badge" class="sk-perk-badge">🔥 Elite Investor Status: ACTIVE</span>
            </div>
        </div>

        <div class="sk-section">
            <h2>Your Notes</h2>
            <table class="sk-investment-list">
                <thead>
                    <tr>
                        <th>Date Invested</th>
                        <th>Amount</th>
                        <th>Rate (APY)</th>
                        <th>Term</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="investment-list-body">
                    <?php if (!empty($activeInvestments)): ?>
                        <?php foreach ($activeInvestments as $investment): ?>
                        <tr>
                            <td><?php echo (new DateTime($investment['created_at']))->format('Y-m-d'); ?></td>
                            <td><?php echo '$' . number_format($investment['amount'] / 100, 2); // Convert cents to dollars ?></td>
                            <td><?php echo sprintf('%.1f%%', $investment['apy']); ?></td>
                            <td><?php echo $investment['term_years']; ?> Years</td>
                            <td><?php echo ucfirst($investment['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">No active investments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="sk-footer">
            <p class="sk-legal-disclaimer">
                Investment figures are estimates and subject to market conditions and official terms. Consult your financial advisor.
            </p>
        </div>
    </div>

    <button id="print-portfolio-btn" class="sk-print-button">Print / Save as PDF</button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const totalInvestmentDisplay = document.getElementById('total-active-investment');
            const totalInterestDisplay = document.getElementById('total-accrued-interest');
            const totalPayoutDisplay = document.getElementById('total-payout-estimate');
            const eliteBadge = document.getElementById('elite-investor-badge');
            const printButton = document.getElementById('print-portfolio-btn');

            // Function to format currency
            function formatCurrency(amount) {
                return '$' + Number(amount).toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
            }

            // Populate summary figures using PHP variables (amounts are in cents, convert to dollars)
            const principalTotal = <?php echo json_encode($totalActiveInvestment); ?>;
            const interestTotal = <?php echo json_encode($totalAccruedInterest); ?>;
            const payoutTotal = <?php echo json_encode($totalPayout); ?>;
            const isElite = <?php echo json_encode(!empty($activeInvestments)); // Simple check: if any investments, assume Elite status for badge ?>;

            if (totalInvestmentDisplay) {
                totalInvestmentDisplay.textContent = formatCurrency(principalTotal);
            }
            if (totalInterestDisplay) {
                totalInterestDisplay.textContent = formatCurrency(interestTotal);
            }
            if (totalPayoutDisplay) {
                totalPayoutDisplay.textContent = formatCurrency(payoutTotal);
            }
            
            // Show/hide Elite badge based on presence of active investments
            if (eliteBadge) {
                if (isElite) {
                    eliteBadge.classList.add('active');
                } else {
                    eliteBadge.classList.remove('active');
                }
            }

            // Add event listener for the print button
            if (printButton) {
                printButton.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>

</body>
</html>