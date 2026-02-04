<?php

// Ensure necessary NGN bootstrap and configurations are loaded.
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json};
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;

// Basic page setup
$config = new Config(); // Load configuration
$pageTitle = 'Investment Proposal';

// --- Get Investment Amount ---
// Accept amount as GET parameter, default to $2500.00 if missing.
// Assume amount is in dollars for user input and display.
$investmentAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 2500.00;

// --- Server-side ROI Calculation ---
$principal = $investmentAmount;
$annualRate = 0.08; // 8% APY
$termYears = 5;
$roiData = [];
$currentBalance = $principal;
$totalInterestEarned = 0;

for ($year = 1; $year <= $termYears; $year++) {
    // Calculate interest for the current year on the current balance
    $interestThisYear = $currentBalance * $annualRate;
    // Add the earned interest to the current balance
    $currentBalance += $interestThisYear;
    // Accumulate total interest earned
    $totalInterestEarned += $interestThisYear;

    $roiData[] = [
        'year' => $year,
        'interest_earned' => $interestThisYear,
        'end_balance' => $currentBalance,
    ];
}

$totalPayout = $principal + $totalInterestEarned;

// --- User Info (Mocked for now) ---
// In a real application, this would be fetched based on user authentication.
$userName = 'Valued Investor'; // Placeholder for user's name
$preparationDate = date('F j, Y');

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
        .sk-proposal-card {
            background-color: #1e1e1e;
            border-radius: 12px;
            padding: 40px;
            margin: 30px auto;
            max-width: 700px;
            box-shadow: 0 4px 15px rgba(0, 255, 102, 0.4); /* Subtle neon green glow */
            transition: box-shadow 0.3s ease;
        }
        .sk-proposal-card:hover { box-shadow: 0 4px 25px rgba(0, 255, 102, 0.6); }
        .sk-proposal-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        .sk-proposal-header h1 {
            color: #00fff2; /* Neon cyan */
            margin: 0;
            font-size: 2.2em;
        }
        .sk-proposal-header p {
            color: #bbb;
            font-size: 1.1em;
            margin-top: 5px;
        }
        .sk-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        .sk-section:last-child { border-bottom: none; padding-bottom: 0; }
        .sk-section h2 {
            color: #00fff2; /* Neon cyan */
            font-size: 1.6em;
            margin-bottom: 15px;
        }
        .sk-section p, .sk-section li {
            color: #ddd;
            line-height: 1.7;
            font-size: 1.05em;
        }
        .sk-terms-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sk-terms-list li {
            margin-bottom: 10px;
        }
        .sk-terms-list strong {
            color: #fff;
            display: inline-block;
            width: 180px;
        }
        .sk-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sk-table th, .sk-table td {
            border: 1px solid #444;
            padding: 12px;
            text-align: right;
        }
        .sk-table th {
            background-color: #2a2a2a;
            color: #00fff2; /* Neon cyan */
            text-align: center;
            font-weight: bold;
        }
        .sk-table td {
            background-color: #1e1e1e;
        }
        .sk-table td:first-child {
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

    <div class="sk-proposal-card">
        <div class="sk-proposal-header">
            <h1>NGN Community Funding</h1>
            <p>Strategic Investment Proposal</p>
        </div>

        <div class="sk-section">
            <h2>Prepared For:</h2>
            <p><?php echo htmlspecialchars($userName); ?></p>
            <p>Date: <?php echo htmlspecialchars($preparationDate); ?></p>
        </div>

        <div class="sk-section">
            <h2>Investment Terms</h2>
            <ul class="sk-terms-list">
                <li><strong>Principal:</strong> <span id="proposal-principal"></span></li>
                <li><strong>Annual Rate (APY):</strong> 8.0%</li>
                <li><strong>Term:</strong> 5 Years</li>
            </ul>
        </div>

        <div class="sk-section">
            <h2>5-Year Projection</h2>
            <table class="sk-table">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Interest Earned</th>
                        <th>End Balance</th>
                    </tr>
                </thead>
                <tbody id="roi-projection-body">
                    <?php foreach ($roiData as $yearData): ?>
                        <tr>
                            <td><?php echo $yearData['year']; ?></td>
                            <td><?php echo '$' . number_format($yearData['interest_earned'], 2); ?></td>
                            <td><?php echo '$' . number_format($yearData['end_balance'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sk-footer">
            <p class="sk-legal-disclaimer">
                This proposal is a non-binding estimate based on projected returns. All investments carry inherent risks, and past performance is not indicative of future results. Please consult with a financial advisor and review the official offering documents before making any investment decisions.
            </p>
        </div>
    </div>

    <button id="print-proposal-btn" class="sk-print-button">Print / Save as PDF</button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const principalDisplay = document.getElementById('principal-display');
            const investNowBtn = document.getElementById('invest-now-btn'); // Not used directly, but for context.
            const printButton = document.getElementById('print-proposal-btn');

            // Function to format currency
            function formatCurrency(amount) {
                return '$' + Number(amount).toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
            }

            // Populate principal display using PHP variable
            // Ensure principalValue is correctly passed from PHP. Since it's in cents, convert to dollars.
            const principalValueInDollars = <?php echo json_encode($principal); ?>;
            if (principalDisplay) {
                principalDisplay.textContent = formatCurrency(principalValueInDollars);
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