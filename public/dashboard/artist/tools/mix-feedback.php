<?php

/**
 * Artist Dashboard - Mix Feedback Tool
 * (Bible Ch. 10 & 13 - Writer Engine & Royalty System: AI feedback on mixes powered by Writer Engine)
 * Investor perks: Free analysis; Non-investors: 15 Sparks
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
use NGN\Lib\AI\BioWriter;
use NGN\Lib\AI\EventDescriptionWriter;
use NGN\Lib\AI\ForbiddenException; // Import custom ForbiddenException
use NGN\Lib\AI\MixFeedbackAssistant; // Import MixFeedbackAssistant

// Basic page setup (assuming a minimal PHP environment)
$config = new Config(); // Load configuration
$pageTitle = 'AI Mix Feedback';

// Placeholder paths for assets. In a real application, these would be managed by an asset pipeline.
$jsApp = '/assets/js/mix-feedback.js'; // Assuming JS will be served from an /assets/js/ directory

// Fetch user investor status for UI conditionally
$isInvestor = false; // Default to false
$userId = null;

// Try to get user info if authenticated to determine investor status and enable/disable UI elements
try {
    // Re-instantiate Request object here as it might be null if not already set.
    // Ensure Request is properly initialized in the bootstrap if it's a shared instance.
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
                // If email is used, fetch user ID from DB
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
                $stmt = $pdo->prepare("SELECT Id FROM `Users` WHERE Email = ? LIMIT 1");
                $stmt->execute([$sub]);
                $user = $stmt->fetch();
                $userId = $user ? (int)$user['Id'] : null;
            }

            if ($userId) {
                // Fetch investor status
                $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
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

// Treat test accounts as investors for perk demonstration
if (dashboard_is_test_account()) {
    $isInvestor = true;
}

// Dynamically set button text and disable state based on investor status and disclaimer
$buttonText = $isInvestor ? 'Analyze (Free - Test Account/Investor Perk)' : 'Analyze (15 Sparks)';
$submitButtonDisabled = !$isInvestor; // Initially disable if not investor, JS will enable if disclaimer is checked

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
            <h1>AI Mix Feedback</h1>
            <p>Upload your mix for AI-powered technical analysis and suggestions.</p>

            <form id="mix-feedback-form">
                <div class="sk-form-group">
                    <label for="artist_name">Artist Name (Optional)</label>
                    <input type="text" id="artist_name" name="artist_name" class="sk-input-text" placeholder="Your Artist Name">
                </div>

                <div class="sk-form-group">
                    <label for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" class="sk-input-text" placeholder="e.g., Rock, Hip Hop, Electronic" required>
                </div>

                <div class="sk-form-group">
                    <label for="file_url">Mix File URL</label>
                    <input type="url" id="file_url" name="file_url" class="sk-input-text" placeholder="https://your-mix-url.com/track.mp3" required>
                </div>

                <div class="sk-disclaimer-group">
                    <input type="checkbox" id="legal_disclaimer" name="legal_disclaimer_accepted" class="sk-disclaimer-checkbox" required <?php echo $isInvestor ? 'checked disabled' : ''; ?> >
                    <label for="legal_disclaimer" class="sk-disclaimer-label">
                        I have read and accept the <a href="/terms#ai-disclaimer" target="_blank">AI Mix Analysis Disclaimer</a>.
                    </label>
                </div>

                <button type="submit" id="analyze-btn" class="sk-btn-neon" <?php echo $submitButtonDisabled ? 'disabled' : ''; ?>><?php echo htmlspecialchars($buttonText); ?></button>
            </form>

            <div id="loading-spinner" class="sk-loading-spinner"></div>
            <div id="error-message" class="sk-error-message" style="display: none;"></div>
            <div id="results-container" style="display: none;">
                <h2>Technical Report:</h2>
                <div id="mix-analysis-report" class="sk-result-box">
                    <!-- Mix analysis results will be displayed here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('mix-feedback-form');
            const analyzeBtn = document.getElementById('analyze-btn');
            const loadingSpinner = document.getElementById('loading-spinner');
            const errorMessageDiv = document.getElementById('error-message');
            const resultsContainer = document.getElementById('results-container');
            const mixAnalysisReportDiv = document.getElementById('mix-analysis-report');
            const legalDisclaimerCheckbox = document.getElementById('legal_disclaimer');

            // Initialize button state based on PHP variable (investor status)
            // Investor perks disable the disclaimer and enable the button initially.
            const isInvestorUser = <?php echo json_encode($isInvestor); ?>;
            if (isInvestorUser) {
                // If investor, disclaimer is checked and button is enabled by default (handled by PHP disabled attribute)
                // No need to re-enable or change button text here, PHP already did it.
            } else {
                // If not investor, button starts disabled and only enabled by disclaimer.
                analyzeBtn.disabled = !legalDisclaimerCheckbox.checked;
            }

            // Enable/disable analyze button based on checkbox state and investor status
            legalDisclaimerCheckbox.addEventListener('change', function() {
                // Button is enabled only if disclaimer is checked OR if the user is an investor
                analyzeBtn.disabled = !this.checked && !isInvestorUser;
            });

            form.addEventListener('submit', async function(event) {
                event.preventDefault(); // Prevent default form submission

                // Clear previous messages and hide results
                errorMessageDiv.textContent = '';
                errorMessageDiv.style.display = 'none';
                resultsContainer.style.display = 'none';
                mixAnalysisReportDiv.innerHTML = '';

                // Show loading spinner and disable button
                loadingSpinner.style.display = 'block';
                analyzeBtn.disabled = true;

                const formData = new FormData(form);
                const data = {
                    artist_name: formData.get('artist_name'),
                    genre: formData.get('genre'),
                    file_url: formData.get('file_url'),
                    legal_disclaimer_accepted: formData.get('legal_disclaimer') === 'on'
                };

                try {
                    const response = await fetch('/api/v1/ai/mix-feedback', {
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
                        // The API returns analysis in result.data.analysis
                        if (result.data && result.data.analysis && typeof result.data.analysis === 'object') {
                            displayMixAnalysis(result.data.analysis);
                            resultsContainer.style.display = 'block';
                            
                            // Update UI if the perk was used (is_free_perk)
                            if (result.data.analysis.is_free_perk) {
                                // Example: Add a visual indicator or confirmation
                                const perkIndicator = document.createElement('span');
                                perkIndicator.innerHTML = " <span style='color: #00f0ff; font-size: 0.8em;'>(Perk Applied)</span>";
                                analyzeBtn.parentNode.insertBefore(perkIndicator, analyzeBtn.nextSibling);
                            }
                        } else {
                            displayError('Could not retrieve analysis. Please try again.');
                        }
                    } else if (response.status === 402) {
                        // Handle Insufficient Sparks error
                        const message = '⚡ Insufficient Sparks - Please top up your account to continue.';
                        displayError(message, true); // Pass true to apply sparks-specific styling
                    } else if (response.status === 400) {
                        // Handle Bad Request error (e.g., disclaimer not accepted)
                        const errorData = await response.json();
                        let errorMessage = 'Invalid input. Please check all fields and accept the disclaimer.';
                        if (errorData.errors && errorData.errors[0] && errorData.errors[0].message) {
                            errorMessage = errorData.errors[0].message;
                        }
                        displayError(errorMessage, false); // Not a sparks error, so false
                    } else {
                        // Handle other API errors (e.g., 401 Unauthorized, 500 Server Error)
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
                    // Re-enable button only if disclaimer is checked OR if user is investor
                    analyzeBtn.disabled = !legalDisclaimerCheckbox.checked && !isInvestorUser;
                }
            });

            function displayMixAnalysis(analysis) {
                mixAnalysisReportDiv.innerHTML = ''; // Clear previous results
                if (!analysis || typeof analysis !== 'object') {
                    mixAnalysisReportDiv.innerHTML = '<p>No analysis data received.</p>';
                    return;
                }

                // Display overall message if present
                if (analysis.overall_message) {
                    const messageElement = document.createElement('p');
                    messageElement.style.marginBottom = '15px';
                    messageElement.textContent = analysis.overall_message;
                    mixAnalysisReportDiv.appendChild(messageElement);
                }

                // Display technical report details
                if (analysis.technical_report && typeof analysis.technical_report === 'object') {
                    const reportList = document.createElement('ul');
                    reportList.style.listStyle = 'disc';
                    reportList.style.paddingLeft = '25px';
                    reportList.style.margin = '0';
                    reportList.style.gap = '10px';

                    for (const [key, value] of Object.entries(analysis.technical_report)) {
                        const listItem = document.createElement('li');
                        // Format the key for readability (e.g., low_end_mud -> Low End Mud)
                        const formattedKey = key.replace(/_/g, ' ').replace(/\b(\w)/g, char => char.toUpperCase());
                        listItem.innerHTML = `<strong>${formattedKey}:</strong> ${value}`;
                        reportList.appendChild(listItem);
                    }
                    mixAnalysisReportDiv.appendChild(reportList);
                } else {
                    mixAnalysisReportDiv.innerHTML = '<p>No detailed technical report available.</p>';
                }
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

            // Function to show a 'Locked' overlay on the card if not Elite (only applicable if tool requires Elite)
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