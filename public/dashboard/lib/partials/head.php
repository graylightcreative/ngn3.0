<?php
/**
 * Dashboard Head Partial
 * Common <head> content for all dashboard pages
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$entityType = dashboard_get_entity_type() ?? 'artist';
$entityLabel = ucfirst($entityType);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | NGN <?= $entityLabel ?> Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --bg-card: #111111;
            --bg-hover: #1a1a1a;
            --border: #222222;
            --border-hover: #333333;
            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --brand: #1DB954;
            --brand-hover: #1ed760;
            --accent: #00d4ff;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #22c55e;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        a { color: var(--brand); text-decoration: none; }
        a:hover { color: var(--brand-hover); }
        
        /* Layout */
        .dashboard-layout {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .sidebar-logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--brand);
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }
        
        .nav-section {
            margin-bottom: 24px;
        }
        
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            padding: 0 12px;
            margin-bottom: 8px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        
        .nav-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background: var(--brand);
            color: #000;
        }
        
        .nav-item i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }
        
        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: 8px;
            background: var(--bg-card);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        
        .page-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-secondary);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .page-content {
            padding: 32px;
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.15s ease;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--brand);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }
        
        .btn-primary {
            background: var(--brand);
            color: #000;
        }
        
        .btn-primary:hover {
            background: var(--brand-hover);
        }
        
        .btn-secondary {
            background: var(--bg-hover);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            border-color: var(--border-hover);
        }
        
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        
        /* Grid */
        .grid { display: grid; gap: 24px; }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (max-width: 1024px) {
            .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }
        
        /* Stats */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 4px;
        }
        
        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--danger); }
        
        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--warning);
        }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="/js/ngn-tour.js"></script>
</head>
<body>

