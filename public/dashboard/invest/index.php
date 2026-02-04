<?php

// Ensure necessary NGN bootstrap and configurations are loaded.
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json, Cors, RateLimiter};
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;
use NGN\Lib\AI\MixFeedbackAssistant;
use NGN\Lib\Commerce\InvestmentService;
use NGN\Lib\Commerce\StripeCheckoutService;
use NGN\Lib\Commerce\Exception\InvestmentException;

// Basic page setup
$config = new Config(); // Load configuration
$pageTitle = 'Investment Calculator';

// Placeholder paths for assets
$jsApp = '/assets/js/invest-calculator.js'; // Assuming JS will be served from an /assets/js/ directory

// Fetch user investor status for UI conditionally
$isInvestor = false; // Default to false
$userId = null;

// Try to get user info if authenticated
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
                $stmt = $pdo->prepare("SELECT Id FROM `Users` WHERE Email = ? LIMIT 1");
                $stmt->execute([$sub]);
                $user = $stmt->fetch();
                $userId = $user ? (int)$user['Id'] : null;
            }
        }
    }
} catch (\Throwable $e) {
    // Ignore auth errors for page load, API will handle them.
    // Log error if needed: error_log("Could not fetch user ID on page load: " . $e->getMessage());
}

// Dynamically set button text and disable state based on investor status and disclaimer
$buttonText = 'Invest Now';
$submitButtonDisabled = true; // Initially disabled

if ($userId) {
    // If user is logged in, we can potentially enable the button based on disclaimer and potentially investor status (though investor status is not directly used to enable the button here, only to change text)
    // The actual 'disabled' state will be managed by JS based on disclaimer checkbox.
    // Investor status is handled in JS for button text change.
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NGN 2.0</title>
    <!-- Spotify Killer Theme CSS (Using inline styles from existing components) -->
    <style>
        /* Basic styling for layout and elements */
        body { background-color: #121212; color: #ffffff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; }
        .sk-card {
            background-color: #1e1e1e;
            border-radius: 12px;
            padding: 40px;
            margin: 30px auto;
            max-width: 600px;
            box-shadow: 0 0 20px rgba(0, 255, 102, 0.7); /* Neon green glow */
            transition: box-shadow 0.3s ease;
        }
        .sk-card:hover { box-shadow: 0 0 25px rgba(0, 255, 102, 0.9); }
        .sk-card-body { display: flex; flex-direction: column; gap: 25px; }
        .sk-card-body h1 { color: #00fff2; /* Neon cyan */ }
        .sk-card-body p { color: #bbb; }
        .sk-form-group { display: flex; flex-direction: column; }
        .sk-form-group label { margin-bottom: 10px; font-weight: bold; color: #999; font-size: 0.9em; }
        .sk-input-text {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            color: #ffffff;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .sk-input-text:focus {
            border-color: #00fff2; /* Neon cyan */
            outline: none;
            box-shadow: 0 0 8px rgba(0, 255, 102, 0.5); /* Neon green */
        }
        .sk-input-text::placeholder { color: #666; }
        .sk-btn-neon {
            background: linear-gradient(90deg, #00f0ff, #00ff6e);
            color: #000;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 0 8px rgba(0, 255, 102, 0.7), 0 0 20px rgba(0, 255, 102, 0.4);
            font-size: 1.1em;
        }
        .sk-btn-neon:hover { box-shadow: 0 0 12px rgba(0, 255, 102, 0.9), 0 0 30px rgba(0, 255, 102, 0.6); transform: translateY(-3px); }
        .sk-btn-neon:disabled { background: #444; box-shadow: none; cursor: not-allowed; opacity: 0.7; }
        .sk-loading-spinner { display: none; margin: 30px auto; width: 50px; height: 50px; border: 5px solid #1e1e1e; border-top-color: #00f0ff; border-radius: 50%; animation: sk-spin 1s linear infinite; }
        @keyframes sk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .sk-result-box {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 20px;
            margin-top: 25px;
            white-space: pre-wrap; /* Preserve whitespace and line breaks */
            word-break: break-word; /* Break long words */
            font-family: 'Courier New', Courier, monospace;
            position: relative;
            font-size: 0.95em;
            color: #eee;
        }
        .sk-error-message {
            color: #ff4d4d; /* Neon red */
            font-weight: bold;
            margin-top: 20px;
            text-align: center;
            padding: 15px;
            background-color: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            border-radius: 6px;
        }
        .sk-perk-badge {
            display: inline-block;
            background-color: #ff00aa; /* Hot magenta */
            color: #000;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 15px;
            transition: transform 0.2s ease, opacity 0.2s ease, visibility 0.2s ease;
            opacity: 0;
            visibility: hidden;
        }
        .sk-perk-badge.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1.1);
        }
        /* Styles for the calculator results */
        #roi-results {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 20px;
            margin-top: 25px;
            font-size: 0.95em;
            color: #eee;
        }
        #roi-results p {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #roi-results p strong {
            color: #bbb;
        }
        #roi-results .sk-perk-badge-display {
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="sk-card">
        <div class="sk-card-body">
            <h1>NGN Community Funding</h1>
            <p>Invest in NGN and earn 8% APY. See your potential returns.</p>

            <form id="investment-form">
                <div class="sk-form-group">
                    <label for="investment-amount">Investment Amount</label>
                    <input type="number" id="investment-amount" name="amount" class="sk-input-text" placeholder="Min $500" min="500" step="100" required>
                    <span id="amount-error" class="sk-error-message" style="display: none;"></span>
                </div>

                <div id="roi-results" style="display: none;">
                    <p><strong>Principal:</strong> <span id="principal-display"></span></p>
                    <p><strong>Total Interest Earned:</strong> <span id="interest-display"></span></p>
                    <p><strong>Total Payout:</strong> <span id="payout-display"></span></p>
                    <div class="sk-perk-badge-display">
                        <span id="elite-perk-badge" class="sk-perk-badge">🔥 Elite Status + AI Unlocked</span>
                    </div>
                </div>

                <button type="submit" id="invest-now-btn" class="sk-btn-neon" disabled>Invest Now</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('investment-form');
            const amountInput = document.getElementById('investment-amount');
            const amountError = document.getElementById('amount-error');
            const roiResults = document.getElementById('roi-results');
            const principalDisplay = document.getElementById('principal-display');
            const interestDisplay = document.getElementById('interest-display');
            const payoutDisplay = document.getElementById('payout-display');
            const elitePerkBadge = document.getElementById('elite-perk-badge');
            const investNowBtn = document.getElementById('invest-now-btn');

            const MIN_INVESTMENT = 500;
            const INVESTMENT_STEP = 100;
            const ANNUAL_RATE = 0.08; // 8% APY
            const INVESTMENT_YEARS = 5;
            const ELITE_THRESHOLD = 2500;

            // Function to format currency
            function formatCurrency(amount) {
                return '$' + Number(amount).toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
            }

            // Function to calculate ROI
            function calculateROI(principal) {
                const compoundFactor = Math.pow(1 + ANNUAL_RATE, INVESTMENT_YEARS);
                const totalInterest = principal * (compoundFactor - 1);
                const totalPayout = principal * compoundFactor;
                return { totalInterest: totalInterest, totalPayout: totalPayout };
            }

            // Function to update the UI
            function updateUI(principal) {
                if (principal === null || isNaN(principal) || principal < MIN_INVESTMENT) {
                    roiResults.style.display = 'none';
                    investNowBtn.disabled = true;
                    amountError.style.display = 'block';
                    amountError.textContent = `Minimum investment is $${formatCurrency(MIN_INVESTMENT)}`;
                    return;
                }

                // Check for step increments visually if needed.
                // The input type="number" with step attribute provides some browser-level guidance.
                // For strict enforcement, we might need to round or validate against the step.
                // For now, we'll calculate based on the entered value, assuming it's reasonably close to a step.

                amountError.style.display = 'none'; // Hide error if input is valid
                amountError.textContent = '';

                const { totalInterest, totalPayout } = calculateROI(principal);

                principalDisplay.textContent = formatCurrency(principal);
                interestDisplay.textContent = formatCurrency(totalInterest);
                payoutDisplay.textContent = formatCurrency(totalPayout);

                roiResults.style.display = 'block';
                investNowBtn.disabled = false;

                // Dynamic Perk Badge
                if (principal >= ELITE_THRESHOLD) {
                    elitePerkBadge.classList.add('active');
                } else {
                    elitePerkBadge.classList.remove('active');
                }
            }

            // Event listener for input changes
            amountInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                updateUI(value);
            });

            // Add event listener for form submission
            form.addEventListener('submit', async function(event) {
                event.preventDefault(); // Prevent default form submission

                const formData = new FormData(form);
                const investmentAmount = parseFloat(formData.get('amount')); // Amount in cents

                // Ensure button is not disabled before proceeding
                if (investNowBtn.disabled) {
                    return;
                }

                try {
                    const response = await fetch('/api/v1/invest/checkout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            // IMPORTANT: Replace 'YOUR_AUTH_TOKEN' with a real token.
                            // This is a placeholder and won't work without actual authentication.
                            'Authorization': 'Bearer YOUR_AUTH_TOKEN'
                        },
                        body: JSON.stringify({ amount: investmentAmount * 100 }) // Send amount in cents
                    });

                    if (response.ok) {
                        const result = await response.json();
                        if (result.data && result.data.checkout_url) {
                            window.location.href = result.data.checkout_url; // Redirect to Stripe Checkout
                        } else {
                            throw new Error('Checkout URL not received.');
                        }
                    } else {
                        const errorData = await response.json();
                        let errorMessage = `Error: ${response.status} - ${response.statusText}`;
                        if (errorData.errors && errorData.errors[0] && errorData.errors[0].message) {
                            errorMessage = errorData.errors[0].message;
                        }
                        throw new Error(errorMessage);
                    }

                } catch (error) {
                    console.error('Investment submission error:', error);
                    amountError.textContent = error.message;
                    amountError.style.display = 'block';
                    investNowBtn.disabled = false; // Re-enable button on error
                }
            });

            // Initial UI update on page load, in case there's a pre-filled value
            updateUI(parseFloat(amountInput.value));
        });
    </script>

</body>
</html>
