/**
 * NGN Guided-Tour Testing
 * 
 * Uses Intro.js to guide beta testers through dashboard features.
 * Bible Ch. 35: Beta Tester Onboarding
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if we should auto-start or if user clicked "Take a Tour"
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('start_tour')) {
        initTour();
    }
});

function initTour() {
    // Ensure intro.js is loaded
    if (typeof introJs === 'undefined') {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js';
        script.onload = runTour;
        document.head.appendChild(script);
    } else {
        runTour();
    }
}

function runTour() {
    const path = window.location.pathname;
    let steps = [];

    // Common steps for all dashboards
    const commonSteps = [
        {
            title: 'Welcome to NGN 2.0 Beta!',
            intro: 'This tour will guide you through the core features of your dashboard. Let's get started!'
        },
        {
            element: document.querySelector('.sk-sidebar') || document.querySelector('nav'),
            intro: 'Use the sidebar to navigate between your analytics, settings, and tools.',
            position: 'right'
        }
    ];

    // Role-specific steps
    if (path.includes('/admin/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="api-health"]'),
                intro: 'Monitor the heartbeat of the NGN ecosystem here.',
                position: 'bottom'
            },
            {
                element: document.querySelector('a[href*="governance"]'),
                intro: 'Review and audit platform actions in the Governance center.',
                position: 'bottom'
            }
        ];
    } else if (path.includes('/dashboard/artist/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="score.php"]'),
                intro: 'Your NGN Score is the heartbeat of your ranking. See how it's calculated here.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="tools/bio-writer.php"]'),
                intro: 'Try our AI tools to generate bios and optimize release timing.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/station/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="spins.php"]'),
                intro: 'Upload and manage your station spins to power the NGN charts.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="content.php"]'),
                intro: 'Manage the content and announcements for your station.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/label/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="roster.php"]'),
                intro: 'Manage your entire artist roster and view aggregate analytics.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="campaigns.php"]'),
                intro: 'Create and track marketing campaigns for your artists.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/venue/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="shows.php"]'),
                intro: 'Schedule shows and manage ticket sales directly through NGN.',
                position: 'right'
            }
        ];
    }

    if (steps.length > 0) {
        introJs().setOptions({
            steps: steps,
            showProgress: true,
            showBullets: false,
            exitOnOverlayClick: false
        }).start();
    }
}

// Global helper to start tour
window.startNgnTour = initTour;
