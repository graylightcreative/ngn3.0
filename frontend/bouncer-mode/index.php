<?php
/**
 * NGN Bouncer Mode - Foundry Edition
 * High-velocity venue interface for live data capture.
 * Bible Ref: Chapter 48 (Venues & Tours)
 */
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN // BOUNCER MODE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: '#FF5F1F', charcoal: '#050505', surface: '#0A0A0A' },
                    fontFamily: { mono: ['JetBrains Mono', 'monospace'], sans: ['Space Grotesk', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { background-color: #050505; color: #ffffff; font-family: 'Space Grotesk', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .stat-value { font-family: 'JetBrains Mono', monospace; font-weight: 800; }
        .tactical-grid {
            background-image: linear-gradient(rgba(255, 95, 31, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 95, 31, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="h-full tactical-grid">

<div class="max-w-md mx-auto h-full flex flex-col p-6">
    
    <!-- HUD -->
    <header class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-black shadow-[0_0_20px_rgba(255,95,31,0.3)]">
                <i class="bi-shield-shaded text-2xl"></i>
            </div>
            <div>
                <h1 class="text-sm font-black uppercase tracking-widest leading-none">Bouncer_Mode</h1>
                <p class="text-[9px] font-mono text-zinc-500 uppercase tracking-tighter mt-1">Node_48_Active</p>
            </div>
        </div>
        <div class="text-right">
            <div id="beacon-status" class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass border-brand/20">
                <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                <span class="text-[9px] font-black uppercase tracking-widest text-brand">Beacon_Locked</span>
            </div>
        </div>
    </header>

    <!-- EVENT SELECTOR -->
    <section class="mb-6">
        <div class="glass p-6 rounded-3xl border-white/5">
            <label class="block text-[10px] font-black text-zinc-500 uppercase tracking-[0.3em] mb-4">Target_Event</label>
            <select id="eventSelect" class="w-full bg-black border border-white/10 rounded-2xl py-4 px-4 text-sm font-bold appearance-none outline-none focus:border-brand transition-all">
                <option value="">Locating local beacons...</option>
            </select>
        </div>
    </section>

    <!-- SCANNER TERMINAL -->
    <main class="flex-grow flex flex-col gap-6">
        <div class="glass flex-grow rounded-[2.5rem] border-brand/10 p-8 flex flex-col items-center justify-center relative overflow-hidden group">
            <div class="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            
            <i class="bi-qr-code-scan text-6xl text-brand/20 mb-8"></i>
            
            <input type="text" id="qrScanInput" placeholder="SCAN_TOKEN_INPUT" 
                   class="w-full bg-black/50 border border-white/10 rounded-2xl py-6 px-6 text-center text-xl stat-value text-brand focus:border-brand outline-none transition-all placeholder:text-zinc-800">
            
            <button id="redeemTicketBtn" class="w-full mt-6 py-6 bg-brand text-black font-black uppercase tracking-[0.2em] rounded-2xl text-sm shadow-2xl shadow-brand/20 hover:scale-[1.02] active:scale-95 transition-all">
                Verify_Redemption
            </button>

            <div id="scanResult" class="mt-8 text-center text-xs font-bold uppercase tracking-widest min-h-[1em]"></div>
        </div>

        <!-- TIPPING & FACILITY FEES -->
        <div class="glass p-6 rounded-3xl border-white/5 flex items-center justify-between">
            <div>
                <h3 class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-1">Facility_Fee_Split</h3>
                <p class="text-xs font-bold text-white">5.00% Auto-Accrual Active</p>
            </div>
            <div class="text-right">
                <div class="text-lg stat-value text-emerald-500">$142.50</div>
                <div class="text-[9px] font-mono text-zinc-600 uppercase">Current_Shift</div>
            </div>
        </div>
    </main>

    <!-- OFFLINE SYNC HUB -->
    <footer class="mt-8 grid grid-cols-2 gap-4">
        <button id="generateManifestBtn" class="glass py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-white transition-colors">
            <i class="bi-cloud-download mr-2"></i> Manifest
        </button>
        <button id="syncOfflineBtn" class="glass py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-white transition-colors">
            <i class="bi-cloud-upload mr-2"></i> Sync (<span id="offlineRedemptionsCount">0</span>)
        </button>
    </footer>

</div>

<script>
    // Logic port from legacy implementation with Foundry enhancements
    const API_BASE_URL = '/api/v1';
    let currentEventId = null;
    let bouncerToken = localStorage.getItem('bouncer_token') || 'SYSTEM_CORE'; 

    let offlineRedemptions = JSON.parse(localStorage.getItem('offline_redemptions') || '[]');
    
    function updateOfflineCount() {
        document.getElementById('offlineRedemptionsCount').textContent = offlineRedemptions.length;
        localStorage.setItem('offline_redemptions', JSON.stringify(offlineRedemptions));
    }

    async function fetchEvents() {
        try {
            const response = await fetch(`${API_BASE_URL}/events?status=published&upcoming=true`);
            const data = await response.json();
            const eventSelect = document.getElementById('eventSelect');
            eventSelect.innerHTML = ''; 

            if (data.success && data.data.length > 0) {
                data.data.forEach(event => {
                    const option = document.createElement('option');
                    option.value = event.id;
                    option.textContent = event.title.toUpperCase();
                    eventSelect.appendChild(option);
                });
                currentEventId = eventSelect.value;
            } else {
                eventSelect.innerHTML = '<option value="">NO_ACTIVE_BEACONS</option>';
            }
        } catch (error) {
            document.getElementById('eventSelect').innerHTML = '<option value="">CONNECTION_ERROR</option>';
        }
    }

    document.getElementById('redeemTicketBtn').addEventListener('click', async function() {
        const resultDiv = document.getElementById('scanResult');
        const input = document.getElementById('qrScanInput');
        const qrHash = input.value.trim();

        if (!qrHash) return;

        resultDiv.textContent = 'VERIFYING...';
        resultDiv.className = 'mt-8 text-center text-xs font-bold uppercase tracking-widest text-zinc-500';

        try {
            const response = await fetch(`${API_BASE_URL}/tickets/redeem`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${bouncerToken}` },
                body: JSON.stringify({ qr_hash: qrHash, scan_location: 'bouncer_node_48' })
            });
            const data = await response.json();
            
            if (data.success) {
                resultDiv.textContent = 'ACCESS_GRANTED';
                resultDiv.className = 'mt-8 text-center text-xs font-bold uppercase tracking-widest text-emerald-500';
                input.value = '';
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            resultDiv.textContent = 'ACCESS_DENIED // OFFLINE_STORED';
            resultDiv.className = 'mt-8 text-center text-xs font-bold uppercase tracking-widest text-brand';
            offlineRedemptions.push({ qr_hash: qrHash, scanned_at: new Date().toISOString() });
            updateOfflineCount();
            input.value = '';
        }
    });

    fetchEvents();
    updateOfflineCount();
</script>

</body>
</html>
