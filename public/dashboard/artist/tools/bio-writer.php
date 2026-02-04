<?php

/**
 * Artist Dashboard - Bio Writer Tool
 * (Bible Ch. 10 & 22 - Writer Engine & Social Feed: AI-powered bio and description generation)
 */
require_once __DIR__ . '/../../../lib/bootstrap.php'; 
use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Http\{Request, Response, Router, Json, Cors, RateLimiter};
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;
use NGN\Lib\AI\BioWriter;

// Basic page setup (assuming a minimal PHP environment)
$config = new Config(); // Load configuration
$pageTitle = 'AI Bio Writer';

// Placeholder paths for assets. In a real application, these would be managed by an asset pipeline.
$jsApp = '/assets/js/bio-writer.js'; // Assuming JS will be served from an /assets/js/ directory

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
    </style>
</head>
<body>

    <div class="sk-card">
        <div class="sk-card-body">
            <h1>AI Bio Writer</h1>
            <p>Generate a professional bio for your artist profile.</p>

            <form id="bio-writer-form">
                <div class="sk-form-group">
                    <label for="artist_name">Artist Name</label>
                    <input type="text" id="artist_name" name="artist_name" class="sk-input-text" placeholder="Enter artist name" required>
                </div>

                <div class="sk-form-group">
                    <label for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" class="sk-input-text" placeholder="e.g., Synthwave, Lo-fi Hip Hop" required>
                </div>

                <div class="sk-form-group">
                    <label for="tone">Bio Tone</label>
                    <select id="tone" name="tone" class="sk-select" required>
                        <option value="" disabled selected>Select a tone...</option>
                        <option value="Professional">Professional</option>
                        <option value="Edgy">Edgy</option>
                        <option value="Fun">Fun</option>
                    </select>
                </div>

                <button type="submit" id="generate-bio-btn" class="sk-btn-neon">Generate Bio</button>
            </form>

            <div id="loading-spinner" class="sk-loading-spinner"></div>
            <div id="error-message" class="sk-error-message" style="display: none;"></div>
            <div id="results-container" style="display: none;">
                <h2>Generated Bio Variants:</h2>
                <div id="bio-variants">
                    <!-- Bios will be displayed here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('bio-writer-form');
            const generateBtn = document.getElementById('generate-bio-btn');
            const loadingSpinner = document.getElementById('loading-spinner');
            const errorMessageDiv = document.getElementById('error-message');
            const resultsContainer = document.getElementById('results-container');
            const bioVariantsDiv = document.getElementById('bio-variants');

            form.addEventListener('submit', async function(event) {
                event.preventDefault(); // Prevent default form submission

                // Clear previous messages and hide results
                errorMessageDiv.textContent = '';
                errorMessageDiv.style.display = 'none';
                resultsContainer.style.display = 'none';
                bioVariantsDiv.innerHTML = '';

                // Show loading spinner and disable button
                loadingSpinner.style.display = 'block';
                generateBtn.disabled = true;

                const formData = new FormData(form);
                const data = {
                    artist_name: formData.get('artist_name'),
                    genre: formData.get('genre'),
                    tone: formData.get('tone'),
                };

                try {
                    const response = await fetch('/api/v1/ai/bio-writer', {
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
                        // The API returns variants in result.data.variants
                        if (result.data && result.data.variants && Array.isArray(result.data.variants)) {
                            displayBios(result.data.variants);
                            resultsContainer.style.display = 'block';
                        } else {
                            // Handle cases where variants are missing or malformed
                            displayError('Could not retrieve bio variants. Please try again.');
                        }
                    } else if (response.status === 402) {
                        // Handle Insufficient Sparks error
                        const message = '⚡ Insufficient Sparks - Please top up your account to continue.';
                        displayError(message, true); // Pass true to apply sparks-specific styling
                    } else {
                        // Handle other API errors (e.g., 400 Bad Request, 401 Unauthorized, 500 Server Error)
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
                    generateBtn.disabled = false;
                }
            });

            function displayBios(bios) {
                bioVariantsDiv.innerHTML = ''; // Clear previous results
                if (bios.length === 0) {
                    bioVariantsDiv.innerHTML = '<p>No bio variants were generated. Please check your input and try again.</p>';
                    return;
                }

                bios.forEach((bio, index) => {
                    const bioDiv = document.createElement('div');
                    bioDiv.className = 'sk-result-box';
                    bioDiv.innerHTML = `
                        <p>${bio}</p>
                        <button class="copy-button" data-index="${index}">Copy</button>
                    `;
                    bioVariantsDiv.appendChild(bioDiv);
                });

                // Add copy functionality to buttons
                document.querySelectorAll('.sk-result-box .copy-button').forEach(button => {
                    button.addEventListener('click', function() {
                        const bioIndex = this.getAttribute('data-index');
                        // Ensure the bio exists before trying to copy
                        if (bios[bioIndex] !== undefined) {
                            const textToCopy = bios[bioIndex];
                            navigator.clipboard.writeText(textToCopy).then(() => {
                                // Provide visual feedback that copy was successful
                                this.textContent = 'Copied!';
                                setTimeout(() => { this.textContent = 'Copy'; }, 1500);
                            }).catch(err => {
                                console.error('Failed to copy text:', err);
                                this.textContent = 'Copy Failed!';
                            });
                        }
                    });
                });
            }

            function displayError(message, isSparksError = false) {
                errorMessageDiv.textContent = message;
                errorMessageDiv.style.display = 'block';
                if (isSparksError) {
                    // Apply theme class for sparks error
                    errorMessageDiv.classList.add('sparks'); 
                } else {
                    // Ensure sparks class is removed if not a sparks error
                    errorMessageDiv.classList.remove('sparks');
                }
            }
        });
    </script>

</body>
</html>