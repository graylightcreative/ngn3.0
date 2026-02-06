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
    // Inject Dark Mode Overrides if not already present
    if (!document.getElementById('ngn-tour-dark-mode')) {
        const style = document.createElement('style');
        style.id = 'ngn-tour-dark-mode';
        style.innerHTML = `
            .introjs-tooltip {
                background-color: #09090b;
                color: #ffffff;
                border: 1px solid #27272a;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
                border-radius: 12px;
                padding: 15px;
            }
            .introjs-arrow.left { border-right-color: #09090b; }
            .introjs-arrow.right { border-left-color: #09090b; }
            .introjs-arrow.top { border-bottom-color: #09090b; }
            .introjs-arrow.bottom { border-top-color: #09090b; }
            .introjs-tooltiptext { color: #a1a1aa; font-size: 14px; line-height: 1.6; }
            .introjs-tooltip-title { color: #ffffff; font-family: "Space Grotesk", sans-serif; font-weight: 700; font-size: 18px; margin-bottom: 8px; }
            .introjs-button {
                background-color: #27272a !important;
                color: #fff !important;
                text-shadow: none !important;
                border: 1px solid #3f3f46 !important;
                border-radius: 8px !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                font-family: 'Inter', sans-serif !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                padding: 8px 16px !important;
            }
            .introjs-button:hover {
                background-color: #3f3f46 !important;
                border-color: #52525b !important;
                color: #1DB954 !important;
            }
            .introjs-skipbutton { color: #71717a !important; }
            .introjs-prevbutton { color: #a1a1aa !important; }
            .introjs-nextbutton { 
                background-color: #1DB954 !important; 
                color: #000 !important; 
                font-weight: 700 !important; 
                border-color: #1DB954 !important; 
            }
            .introjs-nextbutton:hover {
                background-color: #1ed760 !important;
                color: #000 !important;
            }
            .introjs-disabled { color: #3f3f46 !important; background-color: #18181b !important; border-color: #27272a !important; }
            .introjs-progress { background-color: #27272a; height: 4px; }
            .introjs-progressbar { background-color: #1DB954; }
            .introjs-floating { color: #fff; }
            .introjs-tooltip-header { border-bottom: 1px solid #27272a; margin-bottom: 12px; padding-bottom: 8px; }
            .introjs-bullets ul li a { background: #3f3f46; }
            .introjs-bullets ul li a.active { background: #1DB954; }
        `;
        document.head.appendChild(style);
    }

    // Ensure intro.js CSS is loaded
    if (!document.querySelector('link[href*="introjs.min.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css';
        document.head.appendChild(link);
    }

    // Ensure intro.js script is loaded
    if (typeof introJs === 'undefined') {
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
            intro: 'This tour will guide you through the core features of your dashboard. Let\'s get started!'
        },
        {
            element: document.querySelector('.sidebar') || document.querySelector('.sk-sidebar') || document.querySelector('nav'),
            intro: 'Use the sidebar to navigate between your analytics, settings, and tools.',
            position: 'right'
        }
    ];

    // Role-specific steps
    if (path.includes('/admin/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('.metric-card'),
                intro: '<strong>Capital Raised:</strong> Monitor investments from NGN stakeholders and track platform growth.',
                position: 'bottom'
            },
            {
                element: document.querySelector('a[href*="api-health"]'),
                intro: '<strong>Service Health:</strong> Real-time monitoring of all microservices, including P95 latency and external API status.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="governance"]'),
                intro: '<strong>Directorate Board:</strong> Audit platform actions and manage the SIR (Status Inquiry Report) system.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="users.php"]'),
                intro: '<strong>User Management:</strong> Full control over roles, profile claims, and system-wide bans.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/artist/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('.stat-card'),
                intro: '<strong>Real-time Stats:</strong> View your current NGN Rank and Score. Rankings update weekly based on platform-wide engagement.',
                position: 'bottom'
            },
            {
                element: document.querySelector('a[href*="score.php"]'),
                intro: '<strong>Score Breakdown:</strong> If you are an investor, you\'ll see your 1.05x Influence Weighting applied here to boost your impact.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="releases.php"]'),
                intro: '<strong>Discography:</strong> Manage your albums, EPs, and singles. Link merch to your releases through the admin portal.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="fans.php"]'),
                intro: '<strong>Fan Engagement:</strong> Track your Spark balance here. Fans can "Tip" you Sparks which translate to real revenue.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="tiers.php"]'),
                intro: '<strong>Fan Tiers:</strong> Create Gold and Silver subscription levels to offer exclusive content and early access to your fans.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/station/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="spins.php"]'),
                intro: '<strong>Radio Spins:</strong> Log individual plays manually or use the CSV Bulk Upload to report hundreds of spins at once.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="content.php"]'),
                intro: '<strong>BYOS Library:</strong> Upload your own station identifiers, ads, and local content to mix into your playlists.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="playlists.php"]'),
                intro: '<strong>Playlist Manager:</strong> Build custom streams mixing NGN catalog tracks with your BYOS content using drag-and-drop.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="live.php"]'),
                intro: '<strong>Listener Requests:</strong> A real-time queue of fan requests and dedications. Approve them to instantly update your rotation.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="tier.php"]'),
                intro: '<strong>Subscription:</strong> Manage your station tier. Higher tiers unlock more storage and advanced PLN playlist features.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="connections.php"]'),
                intro: '<strong>Social Matrix:</strong> Connect Facebook, Instagram, TikTok, and YouTube to sync your station presence.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/label/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('.stat-card:nth-of-type(3)') || document.querySelector('.stat-card'),
                intro: '<strong>Roster Stats:</strong> Unified view of your signed artists and their collective performance.',
                position: 'bottom'
            },
            {
                element: document.querySelector('a[href*="roster.php"]'),
                intro: '<strong>Unified Roster:</strong> Manage all your signed artists from a single view and monitor their aggregated analytics.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="campaigns.php"]'),
                intro: '<strong>Marketing ROI:</strong> Create targeted email campaigns for your roster and track conversion rates and engagement.',
                position: 'right'
            }
        ];
    } else if (path.includes('/dashboard/venue/')) {
        steps = [
            ...commonSteps,
            {
                element: document.querySelector('a[href*="shows.php"]'),
                intro: '<strong>Show Calendar:</strong> Publish your monthly events. You can generate and print unique QR codes for each event to link to tickets.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="artist-discovery.php"]') || document.querySelector('a[href*="analytics.php"]'),
                intro: '<strong>Talent Discovery:</strong> Search the Artist directory by "Local" to find the perfect openers for your upcoming headliners.',
                position: 'right'
            },
            {
                element: document.querySelector('a[href*="bookings.php"]'),
                intro: '<strong>Booking Desk:</strong> Manage incoming performance requests and track contracts for every show.',
                position: 'right'
            }
        ];
    }

    // Add Mock Data step if Test Account Controls exist
    const testControls = document.querySelector('form[method="post"] button i.bi-magic')?.closest('.card');
    if (testControls) {
        steps.push({
            element: testControls,
            intro: '<strong>Test Account Feature:</strong> Use this magic button to instantly populate your dashboard with mock data for testing!',
            position: 'top'
        });
    }

    if (steps.length > 0) {
        introJs().setOptions({
            steps: steps,
            showProgress: true,
            showBullets: false,
            exitOnOverlayClick: false,
            nextLabel: 'Next &rarr;',
            prevLabel: '&larr; Back',
            doneLabel: 'Got it!'
        }).start();
    }
}

// Global helper to start tour
window.startNgnTour = initTour;