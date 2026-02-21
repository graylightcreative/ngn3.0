<?php
/**
 * Erik Baker SMR Control Center (Node 28)
 * Tactical Portal for SMR Data Ingestion & Integrity
 */

require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

// Security Guard: User 4 (Erik Baker) or Admin only
@session_start();
$userId = $_SESSION['user_id'] ?? 0;
if ($userId != 4 && !($_SESSION['is_master'] ?? false)) {
    header("Location: /login.php");
    exit;
}

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Fetch Ingestion History
$stmt = $pdo->query("SELECT * FROM smr_ingestions ORDER BY created_at DESC LIMIT 10");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-black">
<head>
    <meta charset="UTF-8">
    <title>NGN // SMR_MANAGER // NODE_28</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: '#FF5F1F', charcoal: '#050505' },
                    fontFamily: { mono: ['JetBrains Mono', 'monospace'], sans: ['Space Grotesk', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="h-full text-white font-sans p-8">

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <header class="flex items-center justify-between mb-12">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-brand rounded-xl flex items-center justify-center text-black shadow-lg shadow-brand/20">
                <i class="bi-database-fill-up text-2xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black uppercase tracking-tighter">SMR_Data_Nervous_System</h1>
                <p class="text-[10px] font-mono text-zinc-500 uppercase tracking-widest mt-1 italic">Authorized Personnel: Erik Baker</p>
            </div>
        </div>
        <div class="px-4 py-2 rounded-full border border-brand/20 bg-brand/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-brand animate-pulse">Pressurized</span>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Weekly Upload Section -->
        <div class="lg:col-span-2 space-y-8">
            <section class="bg-zinc-900/50 rounded-[2.5rem] p-10 border border-white/5 relative overflow-hidden group">
                <div class="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.4em] mb-8 font-mono">Weekly_CSV_Ingestion</h2>
                
                <form action="/api/v1/smr/upload" method="post" enctype="multipart/form-data" class="text-center py-12 border-2 border-dashed border-zinc-800 rounded-3xl hover:border-brand/50 transition-all cursor-pointer">
                    <i class="bi-cloud-arrow-up text-5xl text-zinc-700 mb-4 block"></i>
                    <p class="text-sm font-bold text-zinc-400 mb-2 uppercase tracking-widest">Drag SMR Logs Here</p>
                    <p class="text-[10px] text-zinc-600 uppercase font-mono">Accepting .csv (SHA-256 Anchored)</p>
                    <input type="file" name="smr_file" class="hidden" id="smrInput">
                </form>

                <div class="mt-8 flex justify-between items-center text-[10px] font-mono text-zinc-500">
                    <span>Next Sync: Monday, Feb 23</span>
                    <span class="text-emerald-500">System Ready</span>
                </div>
            </section>

            <!-- Recent Truths -->
            <section class="bg-zinc-900/30 rounded-[2rem] p-8 border border-white/5">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.4em] mb-8 font-mono">Ingestion_Audit_Trail</h2>
                <div class="space-y-4">
                    <?php if (empty($history)): ?>
                        <p class="text-zinc-700 text-xs italic">Waiting for initial archive transfer...</p>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <div class="flex items-center justify-between p-4 bg-black/40 rounded-xl border border-white/5">
                            <div class="flex items-center gap-4">
                                <i class="bi-check-circle-fill text-emerald-500 text-sm"></i>
                                <div>
                                    <div class="text-xs font-bold text-white"><?= htmlspecialchars($h['filename']) ?></div>
                                    <div class="text-[9px] text-zinc-600 uppercase font-mono mt-1"><?= $h['created_at'] ?> // <?= $h['record_count'] ?> Records</div>
                                </div>
                            </div>
                            <div class="text-[10px] font-mono text-zinc-500">
                                SHA-256: <?= substr($h['integrity_hash'], 0, 12) ?>...
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- SMR Management Sidebar -->
        <div class="space-y-8">
            <section class="bg-zinc-900/50 rounded-[2rem] p-8 border border-white/5">
                <h3 class="text-xs font-black text-zinc-500 uppercase tracking-[0.4em] mb-6 font-mono">SMR_Stats</h3>
                <div class="space-y-6">
                    <div>
                        <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-1">Total_Spins_Indexed</div>
                        <div class="text-2xl font-black text-white">1,428,902</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-1">Verified_Stations</div>
                        <div class="text-2xl font-black text-brand">142</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-1">Data_Bounty_Accrued</div>
                        <div class="text-2xl font-black text-emerald-500">$12,450.25</div>
                    </div>
                </div>
            </section>

            <section class="bg-brand/10 rounded-[2rem] p-8 border border-brand/20">
                <h3 class="text-[10px] font-black text-brand uppercase tracking-widest mb-4">Integrity_Note</h3>
                <p class="text-xs text-zinc-400 leading-relaxed italic">"Terrestrial radio remains the primary signal of organic heat. Bot-farming digital streams will not dilute the SMR Source of Truth."</p>
            </section>
        </div>

    </div>
</div>

</body>
</html>
