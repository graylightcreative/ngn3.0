<?php
// NGN Maintenance Landing Page — upgraded for NGN 2.0 messaging
// Headers
$now = gmdate('D, d M Y H:i:s') . ' GMT';
header('Content-Type: text/html; charset=utf-8');
http_response_code(503);
header('Retry-After: 3600'); // advise clients to retry in 1 hour
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Load bootstrap to access env (safe here; the universal guard skips when path starts with /maintenance)
$root = dirname(__DIR__, 2);
@require_once $root . '/lib/bootstrap.php';

// Env-driven extras
$msg = getenv('MAINTENANCE_MESSAGE') ?: '';
$eta = getenv('MAINTENANCE_ETA') ?: '';
$waitlist = getenv('WAITLIST_URL') ?: '';
$pricingTeaser = getenv('PRICING_TEASER') ?: '';
// Sponsor/Advertiser
$sponsorDeck = getenv('SPONSOR_DECK_URL') ?: '';
$sponsorCal = getenv('SPONSOR_CAL_URL') ?: '';
$adsEmail = getenv('ADS_CONTACT_EMAIL') ?: 'ads@nextgennoise.com';

// --- NGN 2.0 PROGRESS & ROADMAP DATA LOADING ---
$percent = null; $epicsSummary = []; $currentWorkflow = null; $allTasks = [];
$doneMilestones = []; $inProgressTasks = []; $highPriorityPending = [];
// --- LEGAL MANDATE COMPLIANT TERMS ---
$baseApy = '8% APY'; // Changed from '8% APY (Guaranteed)'
$investorPerk = 'NGN Elite-Host Access + AI Mix Feedback Tool for 5 years.';
$investorTarget = 2500;
$targetValuation = '$5M - $10M (Series A)';


try {
    $trackerPath = $root . '/storage/plan/progress.json';
    if (is_file($trackerPath)) {
        $tracker = json_decode(@file_get_contents($trackerPath), true);
        $scoreMap = ['done'=>1.0,'in_progress'=>0.5,'pending'=>0.0];
        $sumE = 0.0; $sumEW = 0.0;

        foreach ($tracker['epics'] as $epic) {
            $eW = isset($epic['weight']) ? (float)$epic['weight'] : 1.0;
            $miles = $epic['milestones'] ?? [];
            $sumM = 0.0; $sumMW = 0.0;

            foreach ($miles as $mi) {
                $mW = isset($mi['weight']) ? (float)$mi['weight'] : 1.0;
                $tasks = $mi['tasks'] ?? [];
                $mScore = null; $status = 'pending'; $present = 0; $total = 0;

                if ($tasks) {
                    $sumT = 0.0; $sumTW = 0.0; $total = 0; $present = 0;
                    foreach ($tasks as $t) {
                        $tW = isset($t['weight'])?(float)$t['weight']:1.0;
                        $st = (string)($t['status']??'pending');
                        $sumT += $tW * ($scoreMap[$st]??0.0);
                        $sumTW += $tW;
                        $total++;
                        if ($st==='done') $present++;

                        $allTasks[] = [
                                'id' => $t['id'],
                                'title' => $t['title'],
                                'status' => $st,
                                'category' => $epic['title'] ?? 'Other',
                                'priority' => $t['priority'] ?? 'medium',
                                'completed_at' => $t['completed_at'] ?? null,
                        ];
                        if ($st === 'in_progress') {
                            $inProgressTasks[] = $allTasks[count($allTasks)-1];
                        } elseif ($st === 'pending' && ($t['priority'] ?? 'medium') === 'high') {
                            $highPriorityPending[] = $allTasks[count($allTasks)-1];
                        }
                    }
                    $mScore = $sumTW>0 ? ($sumT/$sumTW) : 0.0;
                    $status = ($mScore>=0.999?'done':($mScore<=0.001?'pending':'in_progress'));
                } else {
                    $status = (string)($mi['status']??'pending');
                    $mScore = $scoreMap[$status] ?? 0.0;
                }
                $sumM += $mW * $mScore; $sumMW += $mW;

                if ($status === 'done') {
                    $doneMilestones[] = [
                            'title' => (string)($mi['title'] ?? $mi['id']),
                            'epic' => (string)($epic['title'] ?? $epic['id']),
                    ];
                }
            }

            $eScore = $sumMW>0 ? ($sumM/$sumMW) : ($scoreMap[(string)($epic['status']??'pending')]??0.0);
            $sumE += $eW * $eScore; $sumEW += $eW;
            $epicsSummary[] = [
                    'id' => $epic['id'] ?? '',
                    'title' => $epic['title'] ?? $epic['id'] ?? 'Epic',
                    'status' => $epic['status'] ?? 'pending',
                    'score' => $eScore,
            ];
        }
        $percent = (int)round(100 * ($sumEW>0 ? ($sumE/$sumEW) : 0.0));
    }
} catch (\Throwable $e) { /* ignore */ }

// Sort in progress and high priority tasks
usort($inProgressTasks, fn($a, $b) => ($b['completed_at'] ?? 0) <=> ($a['completed_at'] ?? 0));
usort($highPriorityPending, fn($a, $b) => ($b['priority'] ?? 'medium') <=> ($a['priority'] ?? 'medium'));
$inProgressTasks = array_slice($inProgressTasks, 0, 5);
$highPriorityPending = array_slice($highPriorityPending, 0, 5);


// Helper for getting task status class
function get_task_class($status) {
    switch ($status) {
        case 'done': return 'done';
        case 'in_progress': return 'progress';
        default: return 'pending';
    }
}

// Map Epic Names to Visuals
$epicMap = [
        'ai_writers' => '🤖 AI Writers',
        'community_funding' => '💰 Community Funding',
        'royalties' => '⚖️ Legal & Royalties',
        'stations' => '📻 NGN Stations v2',
        'shops' => '🛍️ Shops & Merch',
        'ppv_venues' => '📺 PPV & Live Events',
        'professional_services' => '🔧 Pro Services',
        'tours' => '🗺️ Tours & Booking',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>NextGenNoise — NGN 2.0 Launch Prep</title>
    <meta name="robots" content="noindex, nofollow" />
    <meta name="referrer" content="no-referrer" />
    <!-- Open Graph (social preview) -->
    <meta property="og:title" content="NGN 2.0 — Indie charts, ads, commerce, PPV, royalties" />
    <meta property="og:description" content="Sponsor the next wave. Self‑serve ads (web/audio/video), PPV livestreams, creator royalties, and storefronts — built for indie scenes." />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="https://nextgennoise.com/lib/images/site/web-light-1.png" />
    <meta property="twitter:card" content="summary_large_image" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #000000;
            --bg-alt: #0a0a0a;
            --card: #111111;
            --muted: #888888;
            --text: #ffffff;
            --brand: #1DB954;
            --brand-dark: #169c46;
            --accent: #00d4ff;
            --fire: #ff6b35;
            --purple: #a855f7;
            --ring: rgba(29,185,84,.5);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        html, body { min-height: 100%; overflow-x: hidden; width: 100%; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: var(--text);
            background: var(--bg);
            line-height: 1.5;
        }
        /* === HERO SECTION === */
        .hero-wrap {
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 80px 24px 60px;
            overflow: hidden;
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background:
                    radial-gradient(ellipse 80% 50% at 50% -20%, rgba(29,185,84,.3), transparent),
                    radial-gradient(ellipse 60% 40% at 80% 80%, rgba(0,212,255,.15), transparent),
                    radial-gradient(ellipse 50% 30% at 20% 60%, rgba(168,85,247,.1), transparent);
            z-index: 0;
        }
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, black, transparent);
            z-index: 0;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 1200px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(29,185,84,.15);
            border: 1px solid rgba(29,185,84,.4);
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--brand);
            margin-bottom: 24px;
            animation: pulse-glow 2s ease-in-out infinite;
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(29,185,84,.2); }
            50% { box-shadow: 0 0 40px rgba(29,185,84,.4); }
        }
        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(48px, 10vw, 120px);
            font-weight: 700;
            line-height: 0.95;
            letter-spacing: -3px;
            margin-bottom: 24px;
        }
        .hero-title .line1 { color: #fff; display: block; }
        .hero-title .line2 {
            display: block;
            background: linear-gradient(135deg, var(--brand) 0%, var(--accent) 50%, var(--purple) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero-sub {
            font-size: clamp(18px, 2.5vw, 24px);
            color: var(--muted);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        .hero-ctas {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
            margin-bottom: 60px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: var(--brand);
            color: #000;
            box-shadow: 0 0 40px rgba(29,185,84,.4), inset 0 1px 0 rgba(255,255,255,.2);
        }
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 60px rgba(29,185,84,.5);
        }
        .btn-secondary {
            background: rgba(255,255,255,.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,.15);
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,.12);
            border-color: var(--brand);
            transform: translateY(-2px);
        }
        .btn-fire {
            background: linear-gradient(135deg, var(--fire), #ff8f6b);
            color: #000;
            animation: fire-pulse 1.5s ease-in-out infinite;
        }
        @keyframes fire-pulse {
            0%, 100% { box-shadow: 0 0 30px rgba(255,107,53,.4); }
            50% { box-shadow: 0 0 50px rgba(255,107,53,.6); }
        }

        /* === STATS BAR === */
        .stats-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
            padding: 30px 0;
            border-top: 1px solid rgba(255,255,255,.1);
            border-bottom: 1px solid rgba(255,255,255,.1);
            margin-top: 40px;
        }
        .stat {
            text-align: center;
        }
        .stat-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }
        .stat-num span { color: var(--brand); }
        .stat-label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
        }

        /* === TICKER === */
        .ticker-wrap {
            width: 100%;
            overflow: hidden;
            background: rgba(29,185,84,.08);
            border-top: 1px solid rgba(29,185,84,.2);
            border-bottom: 1px solid rgba(29,185,84,.2);
            padding: 16px 0;
            margin: 60px 0;
        }
        .ticker-track {
            display: flex;
            gap: 60px;
            animation: ticker 30s linear infinite;
            width: max-content;
        }
        .ticker-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 600;
            color: var(--brand);
            white-space: nowrap;
        }
        .ticker-item::before {
            content: '◆';
            font-size: 8px;
        }
        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* === SECTIONS === */
        .section-wrap {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        .section-tag {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--brand);
            margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(36px, 6vw, 64px);
            font-weight: 700;
            letter-spacing: -2px;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .section-sub {
            font-size: 18px;
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
        }

        /* === FEATURE CARDS === */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 100px;
        }
        .feature-card {
            background: linear-gradient(180deg, rgba(255,255,255,.05) 0%, rgba(255,255,255,.02) 100%);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 24px;
            padding: 32px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--brand), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(29,185,84,.3);
            box-shadow: 0 20px 60px rgba(0,0,0,.4), 0 0 40px rgba(29,185,84,.1);
        }
        .feature-card:hover::before { opacity: 1; }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(29,185,84,.3);
        }
        .feature-icon svg { width: 28px; height: 28px; color: #000; }
        .feature-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .feature-desc {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        /* === COMPARISON SECTION === */
        .compare-section {
            background: linear-gradient(180deg, var(--bg) 0%, var(--bg-alt) 100%);
            padding: 100px 0;
            margin: 80px 0;
        }
        .compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }
        @media (max-width: 768px) {
            .compare-grid { grid-template-columns: 1fr; }
        }
        .compare-card {
            padding: 40px;
            border-radius: 24px;
            position: relative;
        }
        .compare-card.them {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
        }
        .compare-card.us {
            background: linear-gradient(135deg, rgba(29,185,84,.15), rgba(0,212,255,.1));
            border: 2px solid var(--brand);
            box-shadow: 0 0 60px rgba(29,185,84,.2);
        }
        .compare-card.us::before {
            content: 'THE NGN WAY';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--brand);
            color: #000;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 100px;
            letter-spacing: 1px;
        }
        .compare-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .compare-list {
            list-style: none;
        }
        .compare-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,.06);
            font-size: 15px;
            color: var(--muted);
        }
        .compare-list li:last-child { border-bottom: none; }
        .compare-list .icon-x { color: #ef4444; }
        .compare-list .icon-check { color: var(--brand); }

        /* === PROGRESS SECTION === */
        .progress-section {
            background: var(--bg-alt);
            padding: 80px 0;
            border-top: 1px solid rgba(255,255,255,.05);
            border-bottom: 1px solid rgba(255,255,255,.05);
        }
        .progress-bar-wrap {
            background: rgba(255,255,255,.05);
            border-radius: 100px;
            padding: 6px;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .progress-bar {
            height: 20px;
            border-radius: 100px;
            background: linear-gradient(90deg, var(--brand), var(--accent));
            box-shadow: 0 0 20px rgba(29,185,84,.5);
            position: relative;
            transition: width 1s ease-out;
        }
        .progress-bar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255,255,255,.5);
        }
        .progress-label {
            text-align: center;
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .progress-percent {
            text-align: center;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 48px;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 40px;
        }
        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .task-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            padding: 16px 20px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 12px;
            font-size: 14px;
        }
        .task-item.done { border-color: rgba(29,185,84,.3); background: rgba(29,185,84,.08); }
        .task-item.progress { border-color: rgba(251,191,36,.3); background: rgba(251,191,36,.08); }
        .task-item.pending { border-color: rgba(168,85,247,.3); background: rgba(168,85,247,.08); }
        .task-icon { font-size: 18px; }
        .task-item.done .task-icon { color: var(--brand); }
        .task-item.progress .task-icon { color: #fbbf24; }
        .task-item.pending .task-icon { color: var(--purple); }
        .task-title { font-weight: 600; color: #fff; }
        .task-category { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }

        /* Funding specific styles */
        .funding-callout {
            max-width: 1000px;
            margin: 60px auto 0;
            padding: 40px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255,107,53,.15), rgba(168,85,247,.1));
            border: 2px solid var(--fire);
            text-align: left;
        }
        .funding-callout h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            color: var(--fire);
            margin-bottom: 12px;
        }
        .funding-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .funding-stat-box {
            background: rgba(0,0,0,.3);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.1);
            flex: 1;
        }
        .funding-stat-box .val {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }
        .funding-stat-box .label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* New section styles */
        .investor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }
        .investor-card {
            padding: 24px;
            background: var(--card);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }
        .investor-card h4 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .investor-card .highlight {
            color: var(--fire);
            font-weight: 700;
        }


        /* === CTA SECTION === */
        .cta-section {
            padding: 120px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% 100%, rgba(29,185,84,.2), transparent);
        }
        .cta-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(40px, 8vw, 80px);
            font-weight: 700;
            letter-spacing: -2px;
            margin-bottom: 24px;
            position: relative;
        }
        .cta-sub {
            font-size: 20px;
            color: var(--muted);
            margin-bottom: 40px;
            position: relative;
        }
        .btn-fire-invest {
            background: linear-gradient(135deg, var(--fire), #ff4444);
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            padding: 20px 48px;
            border-radius: 60px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 60px rgba(255,107,53,.5), inset 0 1px 0 rgba(255,255,255,.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: pulse-fire 2s ease-in-out infinite;
        }
        .btn-fire-invest:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 20px 80px rgba(255,107,53,.6);
        }
        @keyframes pulse-fire {
            0%, 100% { box-shadow: 0 0 60px rgba(255,107,53,.5), inset 0 1px 0 rgba(255,255,255,.3); }
            50% { box-shadow: 0 0 80px rgba(255,107,53,.7), inset 0 1px 0 rgba(255,255,255,.3); }
        }


        /* === FOOTER === */
        .site-footer {
            padding: 40px 24px;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,.08);
            font-size: 13px;
            color: var(--muted);
        }
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 20px;
        }
        .footer-links a {
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-links a:hover { color: var(--brand); }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .hero-title { letter-spacing: -1px; }
            .stats-bar { gap: 24px; }
            .stat { flex: 1 1 40%; }
            .tasks-grid { grid-template-columns: 1fr; }
            .funding-stats { flex-direction: column; gap: 15px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
<!-- ========== HERO SECTION ========== -->
<section class="hero-wrap">
    <div class="hero-bg"></div>
    <div class="hero-grid"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <span>🔥</span> NGN 2.0 — NOW IN DEVELOPMENT
        </div>
        <h1 class="hero-title">
            <span class="line1">MUSIC DESERVES</span>
            <span class="line2">BETTER THAN SPOTIFY</span>
        </h1>
        <p class="hero-sub">
            The indie music industry's first <strong style="color:#fff">transparent</strong> platform.
            Real charts. Real royalties. Real ownership. Built by artists, for artists.
        </p>
        <div class="hero-ctas">
            <a href="#signup" class="btn btn-primary">
                🚀 Join the Revolution
            </a>
            <a href="/admin/login.php" class="btn btn-secondary">
                Admin Login →
            </a>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat">
                <div class="stat-num" data-count="1000"><span>1,000</span>+</div>
                <div class="stat-label">Artists at Launch</div>
            </div>
            <div class="stat">
                <div class="stat-num"><span>150</span>+</div>
                <div class="stat-label">Venue Partners</div>
            </div>
            <div class="stat">
                <div class="stat-num"><span><?= htmlspecialchars($baseApy) ?></span></div>
                <div class="stat-label">Fixed Note Interest Rate</div>
            </div>
            <div class="stat">
                <div class="stat-num"><span><?= htmlspecialchars($targetValuation) ?></span></div>
                <div class="stat-label">Target Series A Valuation</div>
            </div>
        </div>
    </div>
</section>

<!-- ========== MAILING LIST SIGNUP (PRESERVED) ========== -->
<section id="signup" style="padding: 60px 24px; background: linear-gradient(180deg, rgba(29,185,84,.08) 0%, transparent 100%); border-bottom: 1px solid rgba(255,255,255,.08);">
    <div style="max-width: 600px; margin: 0 auto; text-align: center;">
        <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 32px; font-weight: 700; margin-bottom: 12px;">
            🚀 Join the Waitlist
        </h2>
        <p style="color: var(--muted); margin-bottom: 32px; font-size: 16px;">
            Be the first to know when NGN 2.0 launches. Get early access, exclusive updates, and founding member perks.
        </p>

        <form id="waitlist-form" style="display: flex; flex-direction: column; gap: 16px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" name="firstName" placeholder="First Name" required
                       style="padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 15px; outline: none; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='rgba(255,255,255,.15)'">
                <input type="text" name="lastName" placeholder="Last Name" required
                       style="padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 15px; outline: none; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='rgba(255,255,255,.15)'">
            </div>
            <input type="email" name="email" placeholder="Email Address" required
                   style="padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 15px; outline: none; transition: border-color 0.2s; width: 100%;"
                   onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='rgba(255,255,255,.15)'">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="tel" name="phone" placeholder="Phone (optional)"
                       style="padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 15px; outline: none; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='rgba(255,255,255,.15)'">
                <input type="text" name="band" placeholder="Band/Artist Name (optional)"
                       style="padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 15px; outline: none; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='rgba(255,255,255,.15)'">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 17px; padding: 18px 32px;">
                <span id="submit-text">🔥 Get Early Access</span>
                <span id="submit-loading" style="display: none;">
                        <svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>
                        Joining...
                    </span>
            </button>
        </form>

        <div id="form-message" style="margin-top: 20px; padding: 16px; border-radius: 12px; display: none;"></div>

        <p style="margin-top: 20px; font-size: 12px; color: var(--muted);">
            🔒 We respect your privacy. Unsubscribe anytime.
        </p>
    </div>
</section>
<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    #waitlist-form input::placeholder { color: rgba(255,255,255,.4); }
</style>

<!-- ========== TICKER ========== -->
<div class="ticker-wrap">
    <div class="ticker-track">
        <span class="ticker-item">Transparent Charts</span>
        <span class="ticker-item">Self-Serve Ads</span>
        <span class="ticker-item">Creator Royalties</span>
        <span class="ticker-item">PPV Livestreams</span>
        <span class="ticker-item">Artist Shops</span>
        <span class="ticker-item">Stripe Payouts</span>
        <span class="ticker-item">Real-Time Analytics</span>
        <span class="ticker-item">No Black Box</span>
        <span class="ticker-item">Transparent Charts</span>
        <span class="ticker-item">Self-Serve Ads</span>
        <span class="ticker-item">Creator Royalties</span>
        <span class="ticker-item">PPV Livestreams</span>
        <span class="ticker-item">Artist Shops</span>
        <span class="ticker-item">Stripe Payouts</span>
        <span class="ticker-item">Real-Time Analytics</span>
        <span class="ticker-item">No Black Box</span>
    </div>
</div>

<!-- ========== VERTICAL VALUE PROPOSITION (New Section) ========== -->
<section style="padding: 100px 0;">
    <div class="section-wrap">
        <div class="section-header">
            <div class="section-tag">INVESTMENT STRATEGY</div>
            <h2 class="section-title">The Vertical Value Proposition</h2>
            <p class="section-sub">We don't target consumers; we target the industry's highest value players. Our platform solves their existential problems.</p>
        </div>

        <div class="investor-grid">
            <!-- Labels -->
            <div class="investor-card">
                <h4>💿 Labels & Managers</h4>
                <p class="text-sm text-gray-400">**Problem:** No unified, accurate data for indie charts or verifiable audience growth.</p>
                <p class="text-sm mt-2"><span class="highlight">NGN Solution:</span> Real-time SMR/Spins, Unified Roster Analytics, and AI Campaign Automation.</p>
            </div>

            <!-- Stations -->
            <div class="investor-card">
                <h4>📻 Stations & Podcasters</h4>
                <p class="text-sm text-gray-400">**Problem:** High liability from user-uploaded content (BYOS) and complex licensing.</p>
                <p class="text-sm mt-2"><span class="highlight">NGN Solution:</span> Legal Indemnity Infrastructure (BYOS), Automated Compliance Logging (PLN), and Self-Serve Ad Revenue Sharing.</p>
            </div>

            <!-- Venues -->
            <div class="investor-card">
                <h4>🏟️ Venues & Promoters</h4>
                <p class="text-sm text-gray-400">**Problem:** Fragmented booking, ticketing, and no high-margin digital revenue stream.</p>
                <p class="text-sm mt-2"><span class="highlight">NGN Solution:</span> Integrated PPV Livestreaming, Booking Offer Optimization AI, and the NGN Safety Badge program.</p>
            </div>

            <!-- Producers/Mixers -->
            <div class="investor-card">
                <h4>🔧 Mixers & Producers</h4>
                <p class="text-sm text-gray-400">**Problem:** No standardized crediting system or reliable marketplace for services.</p>
                <p class="text-sm mt-2"><span class="highlight">NGN Solution:</span> Claimable Track Credits, AI Mix Feedback Tool (15 Spark Upsell), and Pro Marketplace Listing.</p>
            </div>
        </div>
    </div>
</section>

<!-- ========== FEATURES SECTION (PRESERVED) ========== -->
<section style="padding: 100px 0;">
    <div class="section-wrap">
        <div class="section-header">
            <div class="section-tag">Why NGN?</div>
            <h2 class="section-title">Everything Spotify<br>Should Have Been</h2>
            <p class="section-sub">We're not just another streaming platform. We're the infrastructure indie music has been waiting for.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.75 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v14.25a.75.75 0 0 1-.75.75H4.5a.75.75 0 0 1-.75-.75V4.5Zm6 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v9.75a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V9Zm6-3a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v12.75a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V6Z"/></svg>
                </div>
                <h3 class="feature-title">Transparent Charts</h3>
                <p class="feature-desc">No more mystery algorithms. See exactly how rankings are calculated—spins, plays, adds, engagement. Every data point explained.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7.5a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z"/><path fill-rule="evenodd" d="M1.5 4.875C1.5 3.839 2.34 3 3.375 3h17.25c1.035 0 1.875.84 1.875 1.875v9.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 0 1 1.5 14.625v-9.75ZM8.25 9.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM18.75 9a.75.75 0 0 0-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75V9.75a.75.75 0 0 0-.75-.75h-.008ZM4.5 9.75A.75.75 0 0 1 5.25 9h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75V9.75Z" clip-rule="evenodd"/><path d="M2.25 18a.75.75 0 0 0 0 1.5c5.4 0 10.63.722 15.6 2.075 1.19.324 2.4-.558 2.4-1.82V18.75a.75.75 0 0 0-.75-.75H2.25Z"/></svg>
                </div>
                <h3 class="feature-title">Real Royalties</h3>
                <p class="feature-desc">Transparent revenue splits with Stripe Connect payouts. Know exactly what you earn, when you earn it. No 6-month delays.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z"/></svg>
                </div>
                <h3 class="feature-title">Artist-First Network</h3>
                <p class="feature-desc">Artists, labels, venues, and stations—all connected. Build your fanbase, not someone else's platform.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 0 1 .359.852L12.982 9.75h7.268a.75.75 0 0 1 .548 1.262l-10.5 11.25a.75.75 0 0 1-1.272-.71l1.992-7.302H3.75a.75.75 0 0 1-.548-1.262l10.5-11.25a.75.75 0 0 1 .913-.143Z" clip-rule="evenodd"/></svg>
                </div>
                <h3 class="feature-title">Self-Serve Ads</h3>
                <p class="feature-desc">Launch display, audio, and video campaigns directly. Target by genre, geo, and device. IAB VAST 4.x compatible.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 8.25A2.25 2.25 0 0 1 17.25 6h.75A3.75 3.75 0 0 1 21.75 9.75v4.5A3.75 3.75 0 0 1 18 18h-.75A2.25 2.25 0 0 1 15 15.75v-7.5ZM3 7.5A1.5 1.5 0 0 1 4.5 6h6A1.5 1.5 0 0 1 12 7.5v9A1.5 1.5 0 0 1 10.5 18h-6A1.5 1.5 0 0 1 3 16.5v-9Z"/></svg>
                </div>
                <h3 class="feature-title">PPV Livestreams</h3>
                <p class="feature-desc">Venues and promoters can host pay-per-view events with built-in ticketing. Keep more of every dollar.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6v.75H5.513c-.96 0-1.764.724-1.865 1.679l-1.263 12A1.875 1.875 0 0 0 4.25 22.5h15.5a1.875 1.875 0 0 0 1.865-2.071l-1.263-12a1.875 1.875 0 0 0-1.865-1.679H16.5V6a4.5 4.5 0 1 0-9 0ZM12 3a3 3 0 0 0-3 3v.75h6V6a3 3 0 0 0-3-3Zm-3 8.25a3 3 0 1 0 6 0v-.75a.75.75 0 0 1 1.5 0v.75a4.5 4.5 0 1 1-9 0v-.75a.75.75 0 0 1 1.5 0v.75Z" clip-rule="evenodd"/></svg>
                </div>
                <h3 class="feature-title">Artist Shops</h3>
                <p class="feature-desc">Integrated merch stores powered by Printful + Stripe. Sell directly to fans without leaving the platform.</p>
            </div>
        </div>
    </div>
</section>

<!-- ========== COMPARISON SECTION (PRESERVED) ========== -->
<section class="compare-section">
    <div class="section-wrap">
        <div class="section-header">
            <div class="section-tag">The Difference</div>
            <h2 class="section-title">Legacy Platforms vs NGN</h2>
        </div>

        <div class="compare-grid">
            <div class="compare-card them">
                <h3 class="compare-title">
                    <span style="font-size:24px">😐</span> Traditional Platforms
                </h3>
                <ul class="compare-list">
                    <li><span class="icon-x">✕</span> Black-box algorithms nobody understands</li>
                    <li><span class="icon-x">✕</span> Royalty payments delayed 6+ months</li>
                    <li><span class="icon-x">✕</span> Platform takes 30%+ of everything</li>
                    <li><span class="icon-x">✕</span> No direct fan relationships</li>
                    <li><span class="icon-x">✕</span> Generic, one-size-fits-all approach</li>
                    <li><span class="icon-x">✕</span> Artists are just content</li>
                </ul>
            </div>

            <div class="compare-card us">
                <h3 class="compare-title">
                    <span style="font-size:24px">🔥</span> NextGenNoise
                </h3>
                <ul class="compare-list">
                    <li><span class="icon-check">✓</span> 100% transparent ranking formulas</li>
                    <li><span class="icon-check">✓</span> Real-time Stripe Connect payouts</li>
                    <li><span class="icon-check">✓</span> Fair, transparent revenue splits</li>
                    <li><span class="icon-check">✓</span> Direct fan engagement tools</li>
                    <li><span class="icon-check">✓</span> Built specifically for metal/rock/indie</li>
                    <li><span class="icon-check">✓</span> Artists are partners, not products</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ========== PROGRESS SECTION (Updated with Progress.json Data) ========== -->
<section class="progress-section">
    <div class="section-wrap">
        <div class="section-header">
            <div class="section-tag">Development Status</div>
            <h2 class="section-title">Building in Public</h2>
            <p class="section-sub">We believe in transparency. Here's exactly where we are on our journey to launch NGN 2.0.</p>
        </div>

        <?php if ($percent !== null): ?>
            <div class="progress-label">NGN 2.0 Full Feature Parity Progress</div>
            <div class="progress-percent"><?= $percent ?>%</div>
            <div class="progress-bar-wrap">
                <div class="progress-bar" style="width: <?= $percent ?>%;"></div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 60px;">
            <h3 style="text-align:center; font-size:28px; margin-bottom:40px; color:#fff;">
                🚀 Critical Path: What We're Launching Next
            </h3>

            <!-- Funding Callout - New Section -->
            <div class="funding-callout">
                <h3 style="color: var(--fire);">NGN Community Funding Drive</h3>
	                <p style="color: #ccc; font-size: 16px; margin-bottom: 15px;">
	                    We are eliminating securities risk with our **Fixed-Term Note Proposal**, securing capital while offering attractive returns and platform perks.
	                    Investors can now use our live **Stripe-secured checkout** to lock in their fixed-term note and perks.
	                </p>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <span style="font-size: 14px; font-weight: 600; color: var(--accent);">
                            Target: Min Investment $<?= number_format($investorTarget) ?>
                        </span>
                    <span style="font-size: 14px; font-weight: 600; color: var(--brand);">
                            Fixed Interest Rate: <?= htmlspecialchars($baseApy) ?>
                        </span>
                </div>

                <div class="funding-stats">
                    <div class="funding-stat-box">
                        <div class="val">40%</div>
                        <div class="label">Allocated to PLN Licensing</div>
                    </div>
                    <div class="funding-stat-box">
                        <div class="val">30%</div>
                        <div class="label">Allocated to AI Writers</div>
                    </div>
                    <div class="funding-stat-box">
                        <div class="val">5 Years</div>
                        <div class="label">Fixed-Term Note Payout</div>
                    </div>
                </div>
                <p style="margin-top: 15px; font-size: 14px; color: var(--purple);">
                    Investor Perk: <?= htmlspecialchars($investorPerk) ?>
                </p>
	                <a href="/invest/invest.php" class="btn btn-fire" style="margin-top: 20px; padding: 12px 24px; font-size: 15px;">
	                    View Investment Calculator & Secure Your Note →
	                </a>
            </div>
            <!-- End Funding Callout -->


            <!-- Currently Working On / High Priority Tasks -->
            <?php if (count($inProgressTasks) > 0 || count($highPriorityPending) > 0): ?>
                <h3 style="text-align:center; font-size:24px; margin-top: 80px; margin-bottom: 30px; color:var(--accent);">
                    Current Development Focus (P1 & P2)
                </h3>
                <div class="tasks-grid">
                    <?php foreach (array_merge($inProgressTasks, $highPriorityPending) as $task): ?>
                        <?php
                        $status = $task['status'] ?? 'pending';
                        $icon = $status === 'done' ? '✓' : ($status === 'in_progress' ? '◐' : '○');
                        $styleClass = get_task_class($status);
                        $category = $task['category'] ?? 'Other';
                        $epicName = $epicMap[$task['id']] ?? $epicMap[$category] ?? $category;
                        ?>
                        <div class="task-item <?= $styleClass ?>">
                            <span class="task-icon" style="font-size:24px; line-height: 1;"><?= $icon ?></span>
                            <div style="text-align: left;">
                                <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                                <div class="task-category"><?= htmlspecialchars($epicName) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Recently Completed Milestones -->
            <?php if (count($doneMilestones) > 0): ?>
                <div style="margin-top: 80px;">
                    <h4 style="text-align:center; font-size:24px; color:var(--brand); margin-bottom:30px;">✅ Recently Completed Milestones</h4>
                    <div class="tasks-grid">
                        <?php
                        // Sort Milestones by Epic Title for readability
                        usort($doneMilestones, fn($a, $b) => $a['epic'] <=> $b['epic']);
                        foreach (array_slice($doneMilestones, 0, 8) as $milestone):
                            $epicName = $epicMap[$milestone['epic']] ?? $milestone['epic'];
                            ?>
                            <div class="task-item done">
                                <span class="task-icon" style="font-size:24px; line-height: 1;">✓</span>
                                <div style="text-align: left;">
                                    <div class="task-title"><?= htmlspecialchars($milestone['title']) ?></div>
                                    <div class="task-category"><?= htmlspecialchars($epicName) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<!-- ========== FINAL CTA ========== -->
<section class="cta-section">
    <h2 class="cta-title">Ready to Join<br>the Revolution?</h2>
    <p class="cta-sub">Be part of the platform that's changing how indie music works.</p>
    <div class="hero-ctas" style="position:relative;">
        <a href="/invest/invest.php" class="btn btn-fire-invest">
            💰 Invest in NGN →
        </a>
        <?php if ($sponsorDeck): ?>
            <a href="<?= htmlspecialchars($sponsorDeck, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" target="_blank">
                📊 Sponsor Deck
            </a>
        <?php endif; ?>
        <a href="mailto:<?= htmlspecialchars($adsEmail, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">
            💼 Advertise With Us
        </a>
    </div>
</section>

<!-- ========== FOOTER ========== -->
<footer class="site-footer">
    <div class="footer-links">
        <a href="/admin/login.php">Admin</a>
        <a href="mailto:support@nextgennoise.com">Support</a>
        <a href="mailto:<?= htmlspecialchars($adsEmail, ENT_QUOTES, 'UTF-8') ?>">Advertising</a>
    </div>
    <p>© <?= date('Y') ?> NextGenNoise. The future of indie music.</p>
    <p style="margin-top:8px; font-size:11px; opacity:0.6;">HTTP 503 • Site under construction • Last updated: <?= htmlspecialchars($now ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</footer>

<script>
    // Intersection Observer for animations
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.feature-card, .compare-card, .task-item, .funding-callout, .investor-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Waitlist form handler (kept outside try/catch for safety, but removed php loading logic)
    const waitlistForm = document.getElementById('waitlist-form');
    const formMessage = document.getElementById('form-message');
    const submitText = document.getElementById('submit-text');
    const submitLoading = document.getElementById('submit-loading');

    if (waitlistForm) {
        waitlistForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Show loading state
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline-flex';
            formMessage.style.display = 'none';

            const formData = new FormData(waitlistForm);
            const data = {
                firstName: formData.get('firstName'),
                lastName: formData.get('lastName'),
                email: formData.get('email'),
                phone: formData.get('phone') || '',
                band: formData.get('band') || '',
                birthday: ''
            };

            try {
                const response = await fetch('/lib/handlers/newsletter-form-signup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.code !== 400) {
                    formMessage.style.display = 'block';
                    formMessage.style.background = 'rgba(29,185,84,.15)';
                    formMessage.style.border = '1px solid rgba(29,185,84,.4)';
                    formMessage.style.color = 'var(--brand)';
                    formMessage.innerHTML = '✅ <strong>You\'re on the list!</strong> Check your email for confirmation.';
                    waitlistForm.reset();
                } else {
                    throw new Error(result.message || 'Something went wrong');
                }
            } catch (error) {
                formMessage.style.display = 'block';
                formMessage.style.background = 'rgba(239,68,68,.15)';
                formMessage.style.border = '1px solid rgba(239,68,68,.4)';
                formMessage.style.color = '#ef4444';
                formMessage.innerHTML = '❌ ' + (error.message || 'Failed to join. Please try again.');
            } finally {
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>