<?php
/**
 * NGN Sovereign Player - Streaming Command Center
 * Foundry Standard: Deep Charcoal / Electric Orange
 * Bible Ref: Chapter 49 (Shredder Node)
 */
?>
<div id="ngn-player-container" class="player-bar">
    <!-- Player UI will be injected here by PlayerUI.js -->
</div>

<style>
/* Sovereign Player Overrides */
.player-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(5, 5, 5, 0.9);
    backdrop-filter: blur(30px);
    border-top: 1px solid rgba(255, 95, 31, 0.2);
    z-index: 1000;
    height: 90px;
    padding: 0 24px;
    display: flex;
    align-items: center;
}

/* Base tailwind for player injected content */
#ngn-player-container .ngn-player {
    display: grid;
    grid-template-columns: 300px 1fr 300px;
    width: 100%;
    align-items: center;
    gap: 32px;
}

.ngn-btn-shredder {
    color: #FF5F1F;
    border: 1px solid rgba(255, 95, 31, 0.3);
    padding: 4px 12px;
    border-radius: 8px;
    transition: all 0.2s;
}

.ngn-btn-shredder:hover {
    background: rgba(255, 95, 31, 0.1);
    box-shadow: 0 0 15px rgba(255, 95, 31, 0.2);
}
</style>

<!-- Player Engine Scripts -->
<script type="module" src="/js/player/player-init.js?v=<?= time() ?>"></script>
