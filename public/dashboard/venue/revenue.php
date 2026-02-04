<?php
/**
 * Venue Revenue Tracking - Ticket Sales Analytics by Event
 * Pro/Premium Feature
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Revenue Tracking';
$currentPage = 'revenue';

// For now, this is a placeholder showing the feature structure
// Real implementation would query ticket sales from database
$revenue_data = [
    'total_revenue' => 0,
    'total_tickets_sold' => 0,
    'this_month_revenue' => 0,
    'events' => [],
];

// Sample event data structure (would be fetched from database)
$sample_events = [
    [
        'id' => 1,
        'title' => 'Summer Concert Series',
        'date' => '2025-06-15',
        'total_capacity' => 500,
        'tickets_sold' => 425,
        'attendance' => 412,
        'revenue_by_tier' => [
            'General Admission' => ['sold' => 300, 'price' => 25, 'revenue' => 7500],
            'VIP' => ['sold' => 100, 'price' => 75, 'revenue' => 7500],
            'Early Bird' => ['sold' => 25, 'price' => 15, 'revenue' => 375],
        ],
        'total_revenue' => 15375,
        'ngn_fee' => 1537.50,
        'venue_revenue' => 13837.50,
    ],
    [
        'id' => 2,
        'title' => 'Metal Fest 2025',
        'date' => '2025-07-22',
        'total_capacity' => 1000,
        'tickets_sold' => 850,
        'attendance' => 812,
        'revenue_by_tier' => [
            'General Admission' => ['sold' => 600, 'price' => 35, 'revenue' => 21000],
            'VIP' => ['sold' => 200, 'price' => 100, 'revenue' => 20000],
            'GA + Parking' => ['sold' => 50, 'price' => 45, 'revenue' => 2250],
        ],
        'total_revenue' => 43250,
        'ngn_fee' => 4325,
        'venue_revenue' => 38925,
    ],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Revenue Tracking</h1>
        <p class="page-subtitle">Track ticket sales revenue by event and ticket type</p>
    </header>

    <div class="page-content">
        <!-- Feature Status -->
        <div style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 32px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
                <div>
                    <h3 style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">
                        <i class="bi-lock" style="color: #a855f7; margin-right: 8px;"></i>Pro Feature
                    </h3>
                    <p style="font-size: 14px; color: var(--text-secondary); margin: 0;">
                        Revenue tracking and analytics are available on Pro and Premium plans. Upgrade to track all your ticket sales revenue in real-time.
                    </p>
                </div>
                <a href="tiers.php" style="padding: 10px 20px; background: #a855f7; color: #fff; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; white-space: nowrap;">
                    Upgrade Now
                </a>
            </div>
        </div>

        <!-- Revenue Summary (Locked) -->
        <div class="grid grid-4" style="margin-bottom: 32px; opacity: 0.6; pointer-events: none;">
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value" style="color: var(--brand);">—</div>
                <div class="stat-change">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tickets Sold</div>
                <div class="stat-value">—</div>
                <div class="stat-change">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Month</div>
                <div class="stat-value" style="color: var(--accent);">—</div>
                <div class="stat-change">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Ticket Price</div>
                <div class="stat-value">—</div>
                <div class="stat-change">All time</div>
            </div>
        </div>

        <!-- Events Revenue Table (Locked) -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="bi-table" style="margin-right: 8px;"></i>Events Revenue</h2>
                <a href="#" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; opacity: 0.5; pointer-events: none;">Export CSV</a>
            </div>
            <div style="position: relative;">
                <table style="width: 100%; border-collapse: collapse; opacity: 0.5;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="text-align: left; padding: 12px; font-weight: 600;">Event</th>
                            <th style="text-align: center; padding: 12px; font-weight: 600;">Date</th>
                            <th style="text-align: center; padding: 12px; font-weight: 600;">Tickets Sold</th>
                            <th style="text-align: center; padding: 12px; font-weight: 600;">Attendance</th>
                            <th style="text-align: right; padding: 12px; font-weight: 600;">Total Revenue</th>
                            <th style="text-align: right; padding: 12px; font-weight: 600;">Your Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px;">Summer Concert Series</td>
                            <td style="text-align: center; padding: 12px;">Jun 15, 2025</td>
                            <td style="text-align: center; padding: 12px;">425 / 500</td>
                            <td style="text-align: center; padding: 12px;">412 (96.9%)</td>
                            <td style="text-align: right; padding: 12px; color: var(--brand); font-weight: 600;">$15,375.00</td>
                            <td style="text-align: right; padding: 12px; color: var(--brand); font-weight: 600;">$13,837.50</td>
                        </tr>
                        <tr>
                            <td style="padding: 12px;">Metal Fest 2025</td>
                            <td style="text-align: center; padding: 12px;">Jul 22, 2025</td>
                            <td style="text-align: center; padding: 12px;">850 / 1000</td>
                            <td style="text-align: center; padding: 12px;">812 (95.5%)</td>
                            <td style="text-align: right; padding: 12px; color: var(--brand); font-weight: 600;">$43,250.00</td>
                            <td style="text-align: right; padding: 12px; color: var(--brand); font-weight: 600;">$38,925.00</td>
                        </tr>
                    </tbody>
                </table>
                <div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.5) 0%, rgba(255,255,255,0.2) 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <div style="text-align: center;">
                        <i class="bi-lock" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="font-weight: 600;">Unlock detailed revenue tracking with Pro</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Tier Breakdown (Locked) -->
        <div class="card" style="margin-top: 32px;">
            <div class="card-header">
                <h2 class="card-title"><i class="bi-pie-chart" style="margin-right: 8px;"></i>Ticket Tier Breakdown</h2>
            </div>
            <div style="position: relative; padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 48px; font-weight: bold; color: var(--brand); margin-bottom: 8px;">—</div>
                        <div style="font-size: 14px; color: var(--text-secondary);">General Admission</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">— sold • $— revenue</div>
                    </div>
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 48px; font-weight: bold; color: #a855f7; margin-bottom: 8px;">—</div>
                        <div style="font-size: 14px; color: var(--text-secondary);">VIP</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">— sold • $— revenue</div>
                    </div>
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 48px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">—</div>
                        <div style="font-size: 14px; color: var(--text-secondary);">Early Bird</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">— sold • $— revenue</div>
                    </div>
                </div>
                <div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.5) 0%, rgba(255,255,255,0.2) 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <div style="text-align: center;">
                        <i class="bi-lock" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="font-weight: 600;">Unlock ticket tier analytics with Pro</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payout Schedule (Locked) -->
        <div class="card" style="margin-top: 32px;">
            <div class="card-header">
                <h2 class="card-title"><i class="bi-calendar-event" style="margin-right: 8px;"></i>Payout Schedule</h2>
            </div>
            <div style="position: relative; padding: 24px;">
                <div style="text-align: center; opacity: 0.5;">
                    <i class="bi-calendar3" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                    <p style="font-weight: 600; margin-bottom: 8px;">Payouts processed weekly</p>
                    <p style="font-size: 14px; color: var(--text-secondary);">Funds transferred 7 days after event</p>
                </div>
                <div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.5) 0%, rgba(255,255,255,0.2) 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <div style="text-align: center;">
                        <i class="bi-lock" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="font-weight: 600;">Unlock payout tracking with Pro</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
