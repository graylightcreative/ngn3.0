/**
 * NGN Commerce & Interaction Handlers
 */

async function tipArtist(artistId, sparkCount) {
    if (!artistId || !sparkCount) return;

    // Visual feedback instantly
    window.NGN_Toast.info(`Sending ${sparkCount} Sparks...`);

    try {
        const response = await fetch('/api/v1/royalties/spark', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                entity_type: 'artist',
                entity_id: artistId,
                spark_count: sparkCount
            })
        });

        const data = await response.json();

        if (data.success) {
            window.NGN_Toast.success(`Successfully tipped ${sparkCount} Sparks!`);
            
            // Trigger haptic if mobile
            if (window.navigator.vibrate) {
                window.navigator.vibrate([50, 30, 50]);
            }
        } else {
            window.NGN_Toast.error(data.message || 'Tipping failed.');
        }
    } catch (e) {
        // Interceptor handles generic network errors, but we can add specific context
        console.error('[Commerce] Tip error:', e);
    }
}

// Global Play Track Handler (Integrated with Toast)
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-play-track]');
    if (btn) {
        const title = btn.dataset.trackTitle || 'Track';
        window.NGN_Toast.info(`Streaming: ${title}`);
    }
});
