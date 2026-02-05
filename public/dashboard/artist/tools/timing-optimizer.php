<?php

/**
 * Artist Dashboard - Timing Optimizer Tool
 * (Bible Ch. 10 & 13 - Writer Engine & Royalty System: AI-powered release timing optimization)
 * Investor perks: Free optimization; Non-investors: 20 Sparks
 */
require_once __DIR__ . '/../../../lib/bootstrap.php';

if (!(new \NGN\Lib\Config())->featureAiEnabled()) {
    header('Location: /dashboard/artist/index.php');
    exit;
}

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json, Cors, RateLimiter};
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;
use NGN\Lib\AI\BioWriter; // Import the new BioWriter service
use NGN\Lib\AI\EventDescriptionWriter; // Import the new EventDescriptionWriter service
use NGN\Lib\AI\ForbiddenException; // Import custom ForbiddenException
use NGN\Lib\AI\MixFeedbackAssistant; // Import MixFeedbackAssistant

// Basic page setup (assuming a minimal PHP environment)
$config = new Config(); // Load configuration
$pageTitle = 'AI Release Timing Optimizer';

// Placeholder paths for assets. In a real application, these would be managed by an asset pipeline.
$jsApp = '/assets/js/timing-optimizer.js'; 

// Fetch user investor status for UI conditionally
$isInvestor = false; // Default to false
$userId = null;

// Try to get user info if authenticated
try {
    $request = new Request();
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
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
                $stmt = $pdo->prepare("SELECT Id FROM `Users` WHERE Email = ? LIMIT 1");
                $stmt->execute([$sub]);
                $user = $stmt->fetch();
                $userId = $user ? (int)$user['Id'] : null;
            }

            if ($userId) {
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
                // Fetch user's investor status directly
                $stmt = $pdo->prepare("SELECT IsInvestor FROM `Users` WHERE Id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();
                if ($userData && isset($userData['IsInvestor'])) {
                    $isInvestor = (bool)$userData['IsInvestor'];
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Ignore auth errors for page load, API will handle them.
}

// Treat test accounts as investors
if (dashboard_is_test_account()) {
    $isInvestor = true;
}

// Button configuration
$buttonText = $isInvestor ? 'Optimize (Free - Test Account/Investor Perk)' : 'Optimize (20 Sparks)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NGN 2.0</title>
    <!-- Spotify Killer Theme CSS (Using inline styles from previous step as a fallback) -->
    <style>
        /* Basic styling for layout and elements if theme CSS is not fully available */
        body { background-color: #121212; color: #ffffff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; }
        .sk-card { background-color: #1e1e1e; border-radius: 12px; padding: 40px; margin: 30px auto; max-width: 600px; box-shadow: 0 0 20px rgba(0, 255, 102, 0.7); transition: box-shadow 0.3s ease; }
        .sk-card:hover { box-shadow: 0 0 25px rgba(0, 255, 102, 0.9); }
        .sk-card-body { display: flex; flex-direction: column; gap: 25px; }
        .sk-card-body h1 { color: #00fff2; /* Neon cyan */ }
        .sk-card-body p { color: #bbb; }
        .sk-form-group { display: flex; flex-direction: column; }
        .sk-form-group label { margin-bottom: 10px; font-weight: bold; color: #999; font-size: 0.9em; }
        .sk-input-text, .sk-input-date, .sk-select {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            color: #ffffff;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .sk-input-text:focus, .sk-input-date:focus, .sk-select:focus {
            border-color: #00fff2; /* Neon cyan */
            outline: none;
            box-shadow: 0 0 8px rgba(0, 255, 102, 0.5); /* Neon green */
        }
        .sk-input-text::placeholder, .sk-input-date::placeholder, .sk-select::placeholder { color: #666; }
        .sk-select {
            appearance: none; /* Remove default dropdown arrow */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20fill%3D%22none%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%222%22%20class%3D%22bi%20bi-chevron-down%22%20viewBox%3D%220%200%2016%2016%22%3E%3Cpath%20d%3D%22M8%2012.75L3.757%208.507c-.753-.754-1.129-.997-1.129-1.507 0-.51.376-.997 1.129-1.507.753-.754%201.129-.997%201.129-1.507S4.507%204.003%203.757%204.51c-.753.754-1.129.997-1.129%201.507.001.51.376.997%201.129%201.507L7.243%208.507c.753.754%201.129.997%201.129%201.507s-.376.997-1.129%201.507L5.886%2011.493c-.753.754-1.129.997-1.129%201.507S5.133%2014.997%205.886%2014.493L8%2012.75z%22%2F%3E%3C%2Fsvg%3E');
            background-position: right 15px center;
            background-size: 20px;
        }
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
        .sk-result-box .copy-button {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #00f0ff;
            color: #000;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.8em;
            transition: background-color 0.2s ease;
        }
        .sk-result-box .copy-button:hover { background-color: #00e0ee; }
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
        .sk-error-message.sparks { color: #00ffff; /* Neon cyan */ border-color: rgba(0, 255, 255, 0.3); background-color: rgba(0, 255, 255, 0.05); }
        #results-container h2 { margin-top: 30px; margin-bottom: 15px; color: #00fff2; }
        /* Style for the 'Locked' overlay */
        .sk-locked-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border-radius: inherit;
            color: #ff00aa; /* Hot Magenta */
            font-weight: bold;
            z-index: 10;
            cursor: pointer;
        }
        .sk-locked-overlay .sk-icon { color: #ff00aa; margin-bottom: 10px; }
        .sk-locked-overlay p { margin: 0; }
        
        /* Styles for the disclaimer checkbox */
        .sk-disclaimer-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        .sk-disclaimer-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #00f0ff;
        }
        .sk-disclaimer-label {
            font-size: 0.9em;
            color: #bbb;
            cursor: pointer;
            margin-bottom: 0;
        }
        .sk-disclaimer-label a {
            color: #00f0ff;
            text-decoration: none;
        }
        .sk-disclaimer-label a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="sk-card">
        <div class="sk-card-body">
            <h1>AI Release Timing Optimizer</h1>
            <p>Get AI-powered suggestions for the best release date for your next track.</p>

            <form id="timing-optimizer-form">
                <div class="sk-form-group">
                    <label for="artist_name">Artist Name (Optional)</label>
                    <input type="text" id="artist_name" name="artist_name" class="sk-input-text" placeholder="Your Artist Name">
                </div>

                <div class="sk-form-group">
                    <label for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" class="sk-input-text" placeholder="e.g., Electronic, Rock, Ambient" required>
                </div>

                <button type="submit" id="optimize-btn" class="sk-btn-neon" <?php echo $isInvestor ? '' : 'disabled'; ?>><?php echo htmlspecialchars($buttonText); ?></button>
            </form>

            <div id="loading-spinner" class="sk-loading-spinner"></div>
            <div id="error-message" class="sk-error-message" style="display: none;"></div>
            <div id="results-container" style="display: none;">
                <h2>Optimal Release Suggestion:</h2>
                <div id="timing-suggestion" class="sk-result-box">
                    <!-- Timing suggestion will be displayed here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('timing-optimizer-form');
            const generateBtn = document.getElementById('optimize-btn');
            const loadingSpinner = document.getElementById('loading-spinner');
            const errorMessageDiv = document.getElementById('error-message');
            const resultsContainer = document.getElementById('results-container');
            const timingSuggestionDiv = document.getElementById('timing-suggestion');

            // Mocking the check for Elite subscription for UI purposes.
            // The actual check and 403 will be enforced by the backend API.
            const isEliteUser = <?php echo json_encode($isInvestor); ?>; 

            form.addEventListener('submit', async function(event) {
                event.preventDefault(); // Prevent default form submission

                // Clear previous messages and hide results
                errorMessageDiv.textContent = '';
                errorMessageDiv.style.display = 'none';
                resultsContainer.style.display = 'none';
                timingSuggestionDiv.innerHTML = '';

                // Show loading spinner and disable button
                loadingSpinner.style.display = 'block';
                generateBtn.disabled = true;

                const formData = new FormData(form);
                const data = {
                    artist_name: formData.get('artist_name'),
                    genre: formData.get('genre'),
                };

                try {
                    const response = await fetch('/api/v1/ai/timing-optimizer', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            // IMPORTANT: Replace 'YOUR_AUTH_TOKEN' with a real token.
                            // This is a placeholder and won't work without actual authentication.
                            'Authorization': 'Bearer YOUR_AUTH_TOKEN'
                        },
                        body: JSON.stringify(data)
                    });

                    if (response.ok) {
                        const result = await response.json();
                        // The API returns suggestion in result.data.suggestion
                        if (result.data && result.data.suggestion && typeof result.data.suggestion === 'object') {
                            displayTimingSuggestion(result.data.suggestion);
                            resultsContainer.style.display = 'block';
                        } else {
                            displayError('Could not retrieve timing suggestion. Please try again.');
                        }
                    } else if (response.status === 402) {
                        // Handle Insufficient Sparks error
                        const message = '⚡ Insufficient Sparks - Please top up your account to continue.';
                        displayError(message, true); // Pass true to apply sparks-specific styling
                    } else if (response.status === 403) {
                        // Handle Forbidden error (not Elite subscriber)
                        const message = '🔒 Locked: Upgrade to Elite to unlock AI timing optimization.';
                        displayError(message, false); // Not a sparks error, so false
                        showLockedOverlay(); // Show the upgrade call-to-action
                    } else {
                        // Handle other API errors (e.g., 400 Bad Request, 500 Server Error)
                        const errorData = await response.json();
                        let errorMessage = `API Error: ${response.status} ${response.statusText}`;
                        if (errorData.errors && errorData.errors[0] && errorData.errors[0].message) {
                            errorMessage = errorData.errors[0].message;
                        }
                        displayError(errorMessage);
                    }

                } catch (error) {
                    console.error('Fetch error:', error);
                    // Display network or unexpected errors
                    displayError(`Network or unexpected error: ${error.message}`);
                } finally {
                    // Hide loading spinner and re-enable button
                    loadingSpinner.style.display = 'none';
                    // Re-enable button only if disclaimer is checked, otherwise keep it disabled
                    analyzeBtn.disabled = !legalDisclaimerCheckbox.checked;
                }
            });

            function displayTimingSuggestion(suggestion) {
                timingSuggestionDiv.innerHTML = ''; // Clear previous results
                if (!suggestion || !suggestion.suggested_date || !suggestion.reasoning) {
                    timingSuggestionDiv.innerHTML = '<p>No valid suggestion received.</p>';
                    return;
                }

                const suggestionElement = document.createElement('div');
                suggestionElement.innerHTML = `
                    <p><strong>Suggested Release Date:</strong> ${suggestion.suggested_date}</p>
                    <p><strong>Reasoning:</strong> ${suggestion.reasoning}</p>
                `;
                timingSuggestionDiv.appendChild(suggestionElement);
            }

            function displayError(message, isSparksError = false) {
                errorMessageDiv.textContent = message;
                errorMessageDiv.style.display = 'block';
                if (isSparksError) {
                    errorMessageDiv.classList.add('sparks');
                } else {
                    errorMessageDiv.classList.remove('sparks');
                }
            }

            // Function to show a 'Locked' overlay on the card if not Elite
            function showLockedOverlay() { if (<?php echo json_encode(dashboard_is_test_account()); ?>) return;
                const cardBody = form.closest('.sk-card-body');
                if (cardBody) {
                    const overlay = document.createElement('div');
                    overlay.className = 'sk-locked-overlay';
                    overlay.innerHTML = '<i class="bi bi-lock-fill sk-icon"></i><p>Upgrade to Elite</p>';
                    // Link the overlay to the pricing page for upgrade action
                    overlay.onclick = () => { window.location.href = '/pricing'; }; // Assuming /pricing is the upgrade page
                    cardBody.style.position = 'relative'; // Ensure overlay is positioned correctly
                    cardBody.appendChild(overlay);
                }
            }
        });
    </script>

</body>
</html>