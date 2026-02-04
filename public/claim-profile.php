<?php

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Title ---
$pageTitle = 'Claim Your Profile';

// --- Dependencies ---
try {
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger) || !($logger instanceof Logger)) {
        $logger = new Logger('claims');
        $logFilePath = __DIR__ . '/../storage/logs/claims.log';
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }
} catch (\Throwable $e) {
    error_log("Claim Profile Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services.</p>";
    exit;
}

// Check if user is already logged in
$currentUser = null;
if (isset($_SESSION['User'])) {
    $currentUser = $_SESSION['User'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NextGenNoise</title>

    <link rel="stylesheet" href="/frontend/src/spotify-killer-theme.css">
    <link rel="stylesheet" href="/css/claims.css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <style>
        .claim-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 4rem 2rem;
            text-align: center;
            color: white;
        }

        .claim-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .claim-hero p {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .search-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .search-box input {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            padding: 1rem 2rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-box button:hover {
            background: #5568d3;
        }

        .entity-type-filter {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .entity-type-filter label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-weight: 500;
        }

        .entity-type-filter input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
        }

        .results-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .results-message {
            text-align: center;
            color: #6b7280;
            font-size: 1rem;
            padding: 2rem;
        }

        .profile-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.5rem;
            transition: box-shadow 0.3s;
            cursor: pointer;
        }

        .profile-card:hover {
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .profile-card.claimed {
            opacity: 0.6;
            background: #f9fafb;
        }

        .profile-card img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
            flex-shrink: 0;
        }

        .profile-card-content {
            flex: 1;
        }

        .profile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .profile-card h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #1f2937;
        }

        .profile-card-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: #6b7280;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e5e7eb;
            color: #374151;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.artist { background: #dbeafe; color: #1e40af; }
        .badge.label { background: #fed7aa; color: #92400e; }
        .badge.venue { background: #d1fae5; color: #065f46; }
        .badge.station { background: #fce7f3; color: #831843; }

        .badge.claimed {
            background: #fecaca;
            color: #7f1d1d;
        }

        .profile-card-actions {
            display: flex;
            gap: 0.75rem;
        }

        .profile-card-actions button {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .btn-claim {
            background: #10b981;
            color: white;
        }

        .btn-claim:hover {
            background: #059669;
        }

        .btn-claim:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .pagination button {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button:hover:not(.active) {
            border-color: #667eea;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .no-results {
            text-align: center;
            padding: 3rem 2rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            color: #6b7280;
        }

        .info-section {
            background: #f0f9ff;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 0.375rem;
        }

        .info-section h3 {
            margin-top: 0;
            color: #667eea;
        }

        @media (max-width: 768px) {
            .claim-hero h1 {
                font-size: 1.875rem;
            }

            .profile-card {
                flex-direction: column;
            }

            .profile-card img {
                width: 100%;
                height: auto;
            }

            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="flex min-h-screen flex-col bg-gray-100 dark:bg-gray-900">

    <?php // Header/Navigation - could include existing nav component ?>
    <nav class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <a href="/" class="text-2xl font-bold text-blue-600">NextGenNoise</a>
                <div>
                    <?php if ($currentUser): ?>
                        <a href="/dashboard/artist" class="mr-4 text-gray-600 hover:text-gray-900">Dashboard</a>
                        <a href="/logout" class="text-gray-600 hover:text-gray-900">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="mr-4 text-gray-600 hover:text-gray-900">Login</a>
                        <a href="/register" class="text-blue-600 font-semibold hover:text-blue-700">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="claim-hero">
        <h1>Is Your Profile Already on NextGenNoise?</h1>
        <p>Search for your artist, label, venue, or station profile and claim it today!</p>
    </section>

    <!-- Search Container -->
    <div class="search-container">
        <!-- Search Box -->
        <div class="search-box">
            <input
                type="text"
                id="searchInput"
                placeholder="Search by name, social media URL, city..."
                autocomplete="off"
            >
            <button onclick="performSearch()">Search</button>
        </div>

        <!-- Entity Type Filter -->
        <div class="entity-type-filter">
            <label>
                <input type="checkbox" name="entity_type" value="artist" checked> Artist
            </label>
            <label>
                <input type="checkbox" name="entity_type" value="label" checked> Label
            </label>
            <label>
                <input type="checkbox" name="entity_type" value="venue" checked> Venue
            </label>
            <label>
                <input type="checkbox" name="entity_type" value="station" checked> Station
            </label>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <h3>Why Claim Your Profile?</h3>
            <ul style="margin: 0; padding-left: 1.5rem;">
                <li>Complete control over your profile information</li>
                <li>Access advanced analytics and insights</li>
                <li>Build your audience on NextGenNoise</li>
                <li>Get priority support from our team</li>
            </ul>
        </div>
    </div>

    <!-- Results Container -->
    <div class="results-container">
        <div id="resultsDiv"></div>
        <div id="paginationDiv"></div>
    </div>

</div>

<script>
let currentPage = 1;
const itemsPerPage = 20;
let allResults = [];
let filteredResults = [];

async function performSearch() {
    const query = document.getElementById('searchInput').value.trim();
    const entityTypes = Array.from(document.querySelectorAll('input[name="entity_type"]:checked'))
        .map(el => el.value);

    if (!query) {
        document.getElementById('resultsDiv').innerHTML = '<div class="results-message">Enter a search term to find profiles</div>';
        return;
    }

    if (entityTypes.length === 0) {
        document.getElementById('resultsDiv').innerHTML = '<div class="results-message">Please select at least one entity type</div>';
        return;
    }

    document.getElementById('resultsDiv').innerHTML = '<div class="loading">Searching...</div>';

    try {
        const response = await fetch('/api/v1/search/profiles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                query: query,
                entity_types: entityTypes
            })
        });

        const data = await response.json();

        if (data.success) {
            allResults = data.data || [];
            currentPage = 1;
            displayResults();
        } else {
            document.getElementById('resultsDiv').innerHTML = `<div class="no-results">${data.message || 'No profiles found'}</div>`;
        }
    } catch (error) {
        console.error('Search error:', error);
        document.getElementById('resultsDiv').innerHTML = '<div class="no-results">Error searching profiles. Please try again.</div>';
    }
}

function displayResults() {
    if (allResults.length === 0) {
        document.getElementById('resultsDiv').innerHTML = '<div class="no-results">No profiles found. Try a different search.</div>';
        document.getElementById('paginationDiv').innerHTML = '';
        return;
    }

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageResults = allResults.slice(start, end);

    let html = '';
    pageResults.forEach(profile => {
        const isClaimed = profile.claimed === 1 || profile.user_id !== null;
        const entityBadge = `<span class="badge ${profile.entity_type}">${profile.entity_type}</span>`;
        const claimedBadge = isClaimed ? '<span class="badge claimed">Already Claimed</span>' : '';

        const imageUrl = profile.image_url || '/assets/placeholder-profile.png';
        const locationInfo = profile.city ? `${profile.city}, ${profile.region || ''}`.trim() : profile.region || '';

        html += `
            <div class="profile-card ${isClaimed ? 'claimed' : ''}">
                <img src="${imageUrl}" alt="${profile.name}" onerror="this.src='/assets/placeholder-profile.png'">
                <div class="profile-card-content">
                    <div class="profile-card-header">
                        <h3>${profile.name}</h3>
                        <div>
                            ${entityBadge}
                            ${claimedBadge}
                        </div>
                    </div>
                    <div class="profile-card-meta">
                        ${locationInfo ? `<span>${locationInfo}</span>` : ''}
                        ${profile.email ? `<span>${profile.email}</span>` : ''}
                    </div>
                    <div class="profile-card-actions">
                        ${!isClaimed ? `
                            <button class="btn-claim" onclick="claimProfile('${profile.entity_type}', ${profile.id})">
                                Claim Profile
                            </button>
                        ` : `
                            <button class="btn-claim" disabled>Already Claimed</button>
                        `}
                    </div>
                </div>
            </div>
        `;
    });

    document.getElementById('resultsDiv').innerHTML = html;

    // Pagination
    const totalPages = Math.ceil(allResults.length / itemsPerPage);
    if (totalPages > 1) {
        let paginationHtml = '<div class="pagination">';

        if (currentPage > 1) {
            paginationHtml += `<button onclick="goToPage(${currentPage - 1})">← Previous</button>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            paginationHtml += `<button class="${activeClass}" onclick="goToPage(${i})">${i}</button>`;
        }

        if (currentPage < totalPages) {
            paginationHtml += `<button onclick="goToPage(${currentPage + 1})">Next →</button>`;
        }

        paginationHtml += '</div>';
        document.getElementById('paginationDiv').innerHTML = paginationHtml;
    } else {
        document.getElementById('paginationDiv').innerHTML = '';
    }
}

function goToPage(page) {
    currentPage = page;
    displayResults();
    document.querySelector('.search-container').scrollIntoView({ behavior: 'smooth' });
}

function claimProfile(entityType, entityId) {
    // Redirect to claim request form
    window.location.href = `/claim/request?entity_type=${entityType}&entity_id=${entityId}`;
}

// Allow Enter key to search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }

    // If already logged in, show welcome message
    <?php if ($currentUser): ?>
        console.log('User logged in as: <?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>');
    <?php endif; ?>
});
</script>

</body>
</html>
