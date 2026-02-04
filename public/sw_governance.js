/**
 * Service Worker - Governance Module
 * Chapter 31 - Push Notification Handler
 *
 * Handles SIR-related push notifications and one-tap verification
 */

// Listen for push notifications
self.addEventListener('push', function(event) {
    if (!event.data) {
        console.log('[SW] Received push with no data');
        return;
    }

    try {
        const data = event.data.json();

        // Only handle SIR-related notifications
        if (!data.type || !data.type.startsWith('sir_')) {
            return;
        }

        const options = {
            body: data.message || 'New SIR action required',
            icon: '/assets/img/ngn-icon.png',
            badge: '/assets/img/badge.png',
            tag: 'sir-notification-' + (data.sir_id || Date.now()),
            requireInteraction: data.type === 'sir_reminder', // Keep reminder visible
            vibrate: [200, 100, 200],
            data: {
                sir_id: data.sir_id,
                sir_number: data.sir_number,
                action_url: data.action_url || `/admin/governance/sir/${data.sir_id}`,
                one_tap_verify_url: data.one_tap_verify_url,
                notification_type: data.type,
            },
            actions: getActionsForNotificationType(data.type, data.sir_id),
        };

        // Add custom badge colors based on notification type
        if (data.type === 'sir_reminder') {
            options.badge = '/assets/img/badge-warning.png';
        } else if (data.type === 'sir_verified') {
            options.badge = '/assets/img/badge-success.png';
        }

        event.waitUntil(
            self.registration.showNotification(data.title || 'SIR Update', options)
        );

    } catch (error) {
        console.error('[SW] Push notification error:', error);
    }
});

/**
 * Get action buttons based on notification type
 */
function getActionsForNotificationType(type, sirId) {
    const actions = [
        {
            action: 'view',
            title: 'View SIR',
            icon: '/assets/img/icon-view.png',
        },
    ];

    // Add verification action only for certain notification types
    if (type === 'sir_assigned' || type === 'rant_phase_update' || type === 'sir_verification_ready') {
        actions.push({
            action: 'verify',
            title: 'Verify SIR',
            icon: '/assets/img/icon-verify.png',
        });
    }

    return actions;
}

// Handle notification clicks
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const data = event.notification.data;

    // Handle different action types
    if (event.action === 'verify') {
        handleOneTabVerify(data);
    } else {
        handleViewSir(data);
    }
});

/**
 * Handle one-tap verification
 */
function handleOneTabVerify(data) {
    if (!data.one_tap_verify_url) {
        // No verification URL, fall back to viewing
        handleViewSir(data);
        return;
    }

    // Get stored auth token
    navigator.serviceWorker.controller.postMessage({
        type: 'getAuthToken',
    });

    // Wait a bit for token, then make request
    setTimeout(() => {
        getAuthToken().then(token => {
            if (!token) {
                // No token, open page to authenticate
                handleViewSir(data);
                return;
            }

            // Make one-tap verification request
            fetch(data.one_tap_verify_url, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sir_id: data.sir_id,
                }),
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error('Verification failed');
            })
            .then(result => {
                // Show success notification
                self.registration.showNotification('Verified', {
                    body: `SIR-${data.sir_number} verified successfully`,
                    icon: '/assets/img/ngn-icon.png',
                    badge: '/assets/img/badge-success.png',
                    tag: 'sir-success-' + data.sir_id,
                });

                // Optionally open the SIR detail page
                clients.matchAll({ type: 'window', includeUncontrolled: true })
                    .then(clientList => {
                        for (let client of clientList) {
                            if (client.url === data.action_url && 'focus' in client) {
                                return client.focus();
                            }
                        }
                        if (clients.openWindow) {
                            return clients.openWindow(data.action_url);
                        }
                    });
            })
            .catch(error => {
                console.error('[SW] One-tap verification error:', error);
                // Fall back to viewing SIR
                handleViewSir(data);
            });
        });
    }, 100);
}

/**
 * Handle viewing SIR
 */
function handleViewSir(data) {
    const url = data.action_url || '/admin/governance';

    clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(clientList => {
            // Look for an existing window
            for (let client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Open new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        });
}

/**
 * Get stored auth token (from localStorage)
 */
function getAuthToken() {
    return new Promise((resolve) => {
        // Try to get token from IndexedDB or localStorage
        // This is a fallback mechanism
        resolve(null); // In production, implement proper token storage
    });
}

// Handle notification dismissal
self.addEventListener('notificationclose', function(event) {
    // Could track analytics here
    console.log('[SW] Notification dismissed:', event.notification.data.sir_number);
});

// Service worker activation
self.addEventListener('activate', function(event) {
    console.log('[SW] Service Worker Activated - Governance Module');
    event.waitUntil(clients.claim());
});

// Service worker installation
self.addEventListener('install', function(event) {
    console.log('[SW] Service Worker Installed - Governance Module');
    self.skipWaiting();
});
