<?php
$pColor = \NGN\Lib\Env::get('THEME_COLOR_PRIMARY', '#FF5F1F');
?>
<style>
:root { 
    --primary: <?= $pColor ?>; 
    --charcoal: #050505; 
    --surface: #121212;
    --highlight: #1a1a1a;
    --text-main: #ffffff;
    --text-sub: #a7a7a7;
}

html, body {
    max-width: 100vw;
    overflow-x: hidden;
}

body { 
    background-color: var(--charcoal) !important; 
    color: var(--text-main); 
    font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
    overflow-x: hidden;
}

/* Spotify-style Card */
.sp-card { 
    background: var(--surface); 
    padding: 16px; 
    border-radius: 12px; 
    transition: background 0.3s, transform 0.3s;
    height: 100%;
    border: 1px solid rgba(255,255,255,0.03);
}
.sp-card:hover { 
    background: var(--highlight);
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--primary) 20%, transparent);
}

/* Scrollbar Styling */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: var(--primary); }

/* Layout Constraints */
.content-container {
    padding-bottom: 100px;
}

/* Sovereign App Experience (Full Width Responsive) */
.app-frame {
    width: 100%;
    margin: 0 auto;
    background: var(--charcoal);
    min-height: 100vh;
    position: relative;
    display: flex;
    flex-direction: column;
    transition: margin-top 0.5s ease;
    overflow-x: hidden;
}

/* Player Rig Fixes (Mobile Style, Locked Bottom) */
#ngn-player-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 80px;
    padding: 0 24px;
    background: rgba(5, 5, 5, 0.9);
    backdrop-filter: blur(30px);
    border-top: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
    z-index: 500;
    display: flex;
    align-items: center;
}
#ngn-player-container .ngn-player {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    height: 100%;
}
.ngn-player-artwork {
    width: 48px !important;
    height: 48px !important;
    border-radius: 8px;
    object-cover: cover;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
}
.ngn-player-track-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.ngn-player-meta {
    flex: 1;
    min-width: 0;
}
.ngn-player-title {
    font-size: 13px !important;
    line-height: 1.2;
}
.ngn-player-controls {
    display: flex;
    align-items: center;
    gap: 12px !important;
}
.ngn-btn-play {
    width: 42px !important;
    height: 42px !important;
    background: var(--primary) !important;
    color: #000 !important;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ngn-btn-prev, .ngn-btn-next {
    color: #fff !important;
    opacity: 0.7;
}
.ngn-btn-queue {
    position: relative;
    color: #fff !important;
}

/* Progress Bar (Show on larger mobile/tablet+) */
.ngn-player-progress {
    display: none !important;
}
@media (min-width: 640px) {
    .ngn-player-progress {
        display: flex !important;
        flex: 1;
        max-width: 600px;
    }
}

/* Hide non-essential player parts on pure mobile feel */
.ngn-player-volume, .ngn-btn-shuffle, .ngn-btn-repeat {
    display: none !important;
}
@media (min-width: 1024px) {
    .ngn-player-volume { display: flex !important; }
}

@media (min-width: 1024px) {
    .content-container { 
        padding-bottom: 100px;
    }
}

/* Loading Spinner */
.loading-spinner {
    width: 3rem;
    height: 3rem;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Global Glow Utility */
.glow-primary {
    filter: drop-shadow(0 0 15px color-mix(in srgb, var(--primary) 40%, transparent));
}

.glass {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.brand-gradient-text {
    background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* 2026 Pioneer UI Additions */
.glass-panel {
    background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0.4) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
}

.glass-panel:hover {
    border-color: color-mix(in srgb, var(--primary) 40%, rgba(255,255,255,0.1));
    box-shadow: 0 8px 32px 0 color-mix(in srgb, var(--primary) 20%, rgba(0,0,0,0.5));
}

.neon-border {
    position: relative;
}
.neon-border::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: inherit;
    background: linear-gradient(45deg, var(--primary), transparent, var(--primary));
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.neon-border:hover::before {
    opacity: 1;
    animation: borderSpin 3s linear infinite;
}

@keyframes borderSpin {
    100% { filter: hue-rotate(360deg); }
}

.separator-slash {
    position: relative;
    padding-bottom: 4rem;
}
.separator-slash::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 10%;
    right: 10%;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--primary), transparent);
    box-shadow: 0 0 20px var(--primary);
    opacity: 0.5;
}

.bg-mesh {
    background-color: var(--charcoal);
    background-image: 
        radial-gradient(at 0% 0%, color-mix(in srgb, var(--primary) 15%, transparent) 0px, transparent 50%),
        radial-gradient(at 100% 100%, color-mix(in srgb, var(--primary) 10%, transparent) 0px, transparent 50%);
}

.vibrant-icon-wrapper {
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 20%, transparent), transparent);
    border: 1px solid color-mix(in srgb, var(--primary) 30%, transparent);
    box-shadow: inset 0 0 20px color-mix(in srgb, var(--primary) 10%, transparent);
}
</style>
