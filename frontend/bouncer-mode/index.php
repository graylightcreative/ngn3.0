<?php
// frontend/bouncer-mode/index.php

// This file serves as the entry point for the PWA Bouncer Mode interface.
// It's a simple HTML page with JavaScript to interact with the backend API.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN Bouncer Mode</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .container { max-width: 800px; }
        .card { margin-bottom: 20px; }
        #qrScanInput { font-size: 1.5rem; height: 60px; }
        #scanResult { min-height: 100px; }
        .success-message { color: green; font-weight: bold; }
        .error-message { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4 text-center">NGN Bouncer Mode <i class="fas fa-ticket-alt"></i></h2>

        <div class="card shadow">
            <div class="card-header">Event Selection</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="eventSelect">Select Event:</label>
                    <select class="form-control" id="eventSelect">
                        <!-- Options will be loaded dynamically via JS -->
                        <option value="">Loading events...</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header">Ticket Scanner</div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="qrScanInput" placeholder="Scan QR code or enter hash">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="redeemTicketBtn"><i class="fas fa-barcode"></i> Redeem Ticket</button>
                    </div>
                </div>
                <div id="scanResult" class="mt-3 text-center"></div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header">Offline Mode & Sync</div>
            <div class="card-body">
                <button class="btn btn-secondary mr-2" id="generateManifestBtn"><i class="fas fa-download"></i> Generate Offline Manifest</button>
                <button class="btn btn-warning" id="syncOfflineBtn"><i class="fas fa-upload"></i> Sync Offline Redemptions</button>
                <div id="offlineStatus" class="mt-3">
                    Current Manifest: <span id="currentManifestHash">None</span> | Offline Redemptions: <span id="offlineRedemptionsCount">0</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE_URL = '/api/v1';
        let currentEventId = null;
        let bouncerToken = "YOUR_BOUNCER_TOKEN_HERE"; // IMPORTANT: Replace with actual token retrieval mechanism

        // Mock offline storage
        let offlineRedemptions = [];
        let offlineManifest = null;

        function showResult(message, isSuccess) {
            const resultDiv = document.getElementById('scanResult');
            resultDiv.innerHTML = `<p class="${isSuccess ? 'success-message' : 'error-message'}">${message}</p>`;
        }

        function updateOfflineStatus() {
            document.getElementById('currentManifestHash').textContent = offlineManifest ? offlineManifest.manifest_hash.substring(0, 10) + '...' : 'None';
            document.getElementById('offlineRedemptionsCount').textContent = offlineRedemptions.length;
        }

        async function fetchEvents() {
            try {
                const response = await fetch(`${API_BASE_URL}/events?status=published&upcoming=true`);
                const data = await response.json();
                const eventSelect = document.getElementById('eventSelect');
                eventSelect.innerHTML = ''; // Clear loading message

                if (data.success && data.data.length > 0) {
                    data.data.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id;
                        option.textContent = `${event.title} - ${new Date(event.starts_at).toLocaleDateString()}`;
                        eventSelect.appendChild(option);
                    });
                    currentEventId = eventSelect.value;
                } else {
                    eventSelect.innerHTML = '<option value="">No upcoming events</option>';
                }
            } catch (error) {
                console.error('Error fetching events:', error);
                document.getElementById('eventSelect').innerHTML = '<option value="">Error loading events</option>';
            }
        }

        document.getElementById('eventSelect').addEventListener('change', function() {
            currentEventId = this.value;
            showResult('', true); // Clear previous scan result
        });

        document.getElementById('redeemTicketBtn').addEventListener('click', async function() {
            if (!currentEventId) {
                showResult('Please select an event first.', false);
                return;
            }
            const qrHash = document.getElementById('qrScanInput').value.trim();
            if (!qrHash) {
                showResult('Please scan or enter a QR hash.', false);
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}/tickets/redeem`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${bouncerToken}`
                    },
                    body: JSON.stringify({
                        qr_hash: qrHash,
                        scan_location: 'bouncer_app',
                        device_id: 'bouncer-device-001'
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showResult(`SUCCESS! Ticket for ${data.ticket.user_name} (${data.ticket.user_email}) redeemed.`, true);
                    // Optionally update event ticket counts displayed
                } else {
                    showResult(`FAILED: ${data.message}`, false);
                    // If offline, store for later sync
                    if (data.error === 'network_error' || response.status === 0) { // Simple network error check
                        offlineRedemptions.push({
                            qr_hash: qrHash,
                            scanned_at: new Date().toISOString(),
                            scan_data: { /* copy scanData from above */ }
                        });
                        updateOfflineStatus();
                        showResult('Network error. Redemption stored offline.', false);
                    }
                }
            } catch (error) {
                console.error('Redeem ticket network error:', error);
                offlineRedemptions.push({
                    qr_hash: qrHash,
                    scanned_at: new Date().toISOString(),
                    scan_data: { /* copy scanData from above */ }
                });
                updateOfflineStatus();
                showResult('Network error. Redemption stored offline.', false);
            } finally {
                document.getElementById('qrScanInput').value = '';
            }
        });

        document.getElementById('generateManifestBtn').addEventListener('click', async function() {
            if (!currentEventId) {
                showResult('Please select an event first.', false);
                return;
            }
            try {
                const response = await fetch(`${API_BASE_URL}/tickets/manifest/${currentEventId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${bouncerToken}` // Admin token needed for manifest generation
                    }
                });
                const data = await response.json();
                if (data.success) {
                    offlineManifest = data.data;
                    updateOfflineStatus();
                    showResult('Offline manifest generated successfully!', true);
                } else {
                    showResult(`Failed to generate manifest: ${data.message}`, false);
                }
            } catch (error) {
                console.error('Generate manifest network error:', error);
                showResult('Network error generating manifest.', false);
            }
        });

        document.getElementById('syncOfflineBtn').addEventListener('click', async function() {
            if (offlineRedemptions.length === 0) {
                showResult('No offline redemptions to sync.', false);
                return;
            }
            try {
                const response = await fetch(`${API_BASE_URL}/tickets/sync-offline`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${bouncerToken}` // Bouncer token for syncing
                    },
                    body: JSON.stringify({ redemptions: offlineRedemptions })
                });
                const data = await response.json();
                if (data.success) {
                    const successfulSyncs = data.data.filter(s => s.success).length;
                    const failedSyncs = data.data.filter(s => !s.success).length;
                    showResult(`Sync complete! ${successfulSyncs} successful, ${failedSyncs} failed.`, true);
                    offlineRedemptions = []; // Clear successfully synced redemptions
                    updateOfflineStatus();
                } else {
                    showResult(`Sync failed: ${data.message}`, false);
                }
            } catch (error) {
                console.error('Sync offline redemptions network error:', error);
                showResult('Network error during offline sync.', false);
            }
        });

        // Initialize on load
        fetchEvents();
        updateOfflineStatus();
    </script>
</body>
</html>
