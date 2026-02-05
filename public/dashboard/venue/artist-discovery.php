<?php
/**
 * Venue Dashboard - Artist Discovery
 * (Bible Ch. 7 Product Specs - V.3 Talent Discovery: Search local artists)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Artist Discovery';
$currentPage = 'home'; // or a new one for discovery

$searchQuery = $_GET['q'] ?? '';
$localOnly = isset($_GET['local']) && $_GET['local'] == '1';
$artists = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $params = [];
        $where = [];

        if (!empty($searchQuery)) {
            $where[] = "(name LIKE ? OR bio LIKE ?)";
            $params[] = '%' . $searchQuery . '%';
            $params[] = '%' . $searchQuery . '%';
        }

        if ($localOnly && !empty($entity['city'])) {
            $where[] = "city = ?";
            $params[] = $entity['city'];
            if (!empty($entity['region'])) {
                $where[] = "region = ?";
                $params[] = $entity['region'];
            }
        }
        
        $sql = "SELECT * FROM artists";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY name ASC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <a href="index.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">Artist Discovery</h1>
        <p class="page-subtitle">Find artists for your venue</p>
    </header>
    
    <div class="page-content">
        <div class="card">
            <form action="artist-discovery.php" method="GET">
                <div class="form-group">
                    <label class="form-label">Search for Artists</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="q" class="form-input" placeholder="Search by name, genre, etc..." value="<?= htmlspecialchars($searchQuery) ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="local" value="1" <?= $localOnly ? 'checked' : '' ?>>
                        <span>Search local artists only (based on your venue's city and region)</span>
                    </label>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Find Artists</h2>
            <div style="padding: 1rem;">
                <div class="form-group">
                    <label class="form-label">Radius (miles)</label>
                    <input type="number" id="radiusInput" class="form-input" placeholder="e.g., 50" min="1" value="50">
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">Search artists within this radius of your venue. (Simulated)</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Genre</label>
                    <select id="genreSelect" class="form-input">
                        <option value="all">Any Genre</option>
                        <option value="rock">Rock</option>
                        <option value="pop">Pop</option>
                        <option value="hip-hop">Hip Hop</option>
                        <option value="electronic">Electronic</option>
                        <option value="jazz">Jazz</option>
                        <option value="country">Country</option>
                    </select>
                </div>
                <button type="button" id="applyFiltersBtn" class="btn btn-primary">Apply Filters</button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Search Results (<span id="artistResultCount">0</span>)</h2>
            </div>
            <div id="artistResultsContainer" class="grid grid-4">
                <!-- Artist cards will be dynamically inserted here by JavaScript -->
                <div style="grid-column: span 4; text-align: center; padding: 48px 24px; color: var(--text-muted);">
                    <i class="bi bi-search" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                    <p>Use the filters above to find artists.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radiusInput = document.getElementById('radiusInput');
    const genreSelect = document.getElementById('genreSelect');
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');
    const artistResultsContainer = document.getElementById('artistResultsContainer');
    const artistResultCount = document.getElementById('artistResultCount');

    const mockArtistData = [
        { name: 'Rock Band X', genre: 'rock', distance: 10, slug: 'rock-band-x', imageUrl: 'https://via.placeholder.com/150/FF0000/FFFFFF?text=RockX' },
        { name: 'Pop Star Y', genre: 'pop', distance: 5, slug: 'pop-star-y', imageUrl: 'https://via.placeholder.com/150/0000FF/FFFFFF?text=PopY' },
        { name: 'Hip Hop Crew Z', genre: 'hip-hop', distance: 25, slug: 'hip-hop-crew-z', imageUrl: 'https://via.placeholder.com/150/00FF00/FFFFFF?text=HipHopZ' },
        { name: 'Electronic Duo A', genre: 'electronic', distance: 50, slug: 'electronic-duo-a', imageUrl: 'https://via.placeholder.com/150/FFFF00/000000?text=ElecA' },
        { name: 'Jazz Ensemble B', genre: 'jazz', distance: 15, slug: 'jazz-ensemble-b', imageUrl: 'https://via.placeholder.com/150/800080/FFFFFF?text=JazzB' },
        { name: 'Country Singer C', genre: 'country', distance: 30, slug: 'country-singer-c', imageUrl: 'https://via.placeholder.com/150/FFA500/FFFFFF?text=CtryC' },
        { name: 'Indie Folk D', genre: 'rock', distance: 8, slug: 'indie-folk-d', imageUrl: 'https://via.placeholder.com/150/808080/FFFFFF?text=IndieD' },
        { name: 'Metal Mayhem', genre: 'rock', distance: 40, slug: 'metal-mayhem', imageUrl: 'https://via.placeholder.com/150/404040/FFFFFF?text=Metal' },
    ];

    function renderArtists(artistsToRender) {
        artistResultsContainer.innerHTML = ''; // Clear previous results
        if (artistsToRender.length === 0) {
            artistResultsContainer.innerHTML = `
                <div style="grid-column: span 4; text-align: center; padding: 48px 24px; color: var(--text-muted);">
                    <i class="bi bi-search" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                    <p>No artists found matching your filters.</p>
                </div>
            `;
        } else {
            artistsToRender.forEach(artist => {
                const artistCard = document.createElement('div');
                artistCard.className = 'card';
                artistCard.innerHTML = `
                    <a href="/?view=artist&slug=${artist.slug}" target="_blank">
                        <img src="${artist.imageUrl}" alt="${artist.name}" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
                        <h3 class="card-title" style="margin-bottom: 0.5rem;">${artist.name}</h3>
                    </a>
                    <p style="font-size: 13px; color: var(--text-muted);">
                        ${artist.genre ? artist.genre.charAt(0).toUpperCase() + artist.genre.slice(1) : ''} &bull; ${artist.distance} miles away
                    </p>
                `;
                artistResultsContainer.appendChild(artistCard);
            });
        }
        artistResultCount.textContent = artistsToRender.length;
    }

    function applyFilters() {
        const selectedRadius = parseInt(radiusInput.value) || 0;
        const selectedGenre = genreSelect.value;

        const filteredArtists = mockArtistData.filter(artist => {
            const matchesRadius = selectedRadius === 0 || artist.distance <= selectedRadius;
            const matchesGenre = selectedGenre === 'all' || artist.genre === selectedGenre;
            return matchesRadius && matchesGenre;
        });
        renderArtists(filteredArtists);
    }

    applyFiltersBtn.addEventListener('click', applyFilters);
    radiusInput.addEventListener('input', applyFilters); // Live filter on radius change
    genreSelect.addEventListener('change', applyFilters); // Live filter on genre change

    // Initial render
    applyFilters();
});
</script>

</body>
</html>