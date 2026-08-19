<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/validation.php';

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeOutput($pageTitle); ?> | <?= APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Lora:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css?v=20260812-password-controls">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/journal_editor.css?v=20260730-v8">
    <script>window.BASE_URL = <?= json_encode(BASE_URL); ?>;</script>
    <style>
    /* Global Sidebar Navigation Styles */
    :root {
        --color-primary: #236a54;
        --color-primary-active: #174d3d;
        --color-primary-disabled: #e2f1ea;
        --color-ink: #17231b;
        --color-body: #344338;
        --color-body-strong: #17231b;
        --color-muted: #647064;
        --color-muted-soft: #9bb59e;
        --color-hairline: #dbe4d7;
        --color-hairline-soft: #eaf1e5;
        --color-canvas: #f4f7f2;
        --color-surface-soft: #f9fbf7;
        --color-surface-card: #eaf1e5;
        --color-surface-dark: #174d3d;
        --color-on-primary: #ffffff;
        
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-full: 9999px;
        
        --spacing-xxs: 4px;
        --spacing-xs: 8px;
        --spacing-sm: 12px;
        --spacing-md: 16px;
        --spacing-lg: 24px;
        --spacing-xl: 32px;
        --spacing-xxl: 48px;
    }

    body.has-sidebar {
        display: flex !important;
        height: 100vh !important;
        max-height: 100vh !important;
        overflow: hidden !important;
        flex-direction: row !important;
        margin: 0 !important;
        padding: 0 !important;
        background-color: var(--color-canvas) !important;
    }

    body.has-sidebar .page-shell {
        flex: 1 !important;
        max-width: 100% !important;
        width: auto !important;
        margin: 0 !important;
        padding: var(--spacing-xl) var(--spacing-xxl) !important;
        height: 100vh !important;
        max-height: 100vh !important;
        overflow-y: auto !important;
        box-sizing: border-box !important;
        min-height: 0 !important;
        background-color: var(--color-canvas) !important;
    }

    body.has-sidebar .footer {
        display: none !important;
    }

    .dashboard-sidebar {
        width: 260px;
        background-color: #ffffff;
        border-right: 1px solid var(--color-hairline);
        padding: var(--spacing-lg) var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
        height: 100%;
        flex-shrink: 0;
        box-sizing: border-box;
        z-index: 100;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 var(--spacing-xs);
        text-decoration: none;
    }

    .sidebar-logo .logo-spike {
        width: 26px;
        height: 26px;
        position: relative;
        background-color: var(--color-primary);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sidebar-logo .logo-spike::after {
        content: '';
        position: absolute;
        width: 14px;
        height: 14px;
        background-color: #ffffff;
        clip-path: polygon(
            50% 0%, 55% 35%, 85% 15%, 65% 45%, 100% 50%, 65% 55%, 85% 85%, 55% 65%, 50% 100%, 45% 65%, 15% 85%, 35% 55%, 0% 50%, 35% 45%, 15% 15%, 45% 35%
        );
    }

    .sidebar-logo-text {
        font-family: "Lora", "Playfair Display", Georgia, serif;
        font-size: 18px;
        font-weight: 500;
        color: var(--color-ink);
        line-height: 1.2;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }

    .sidebar-nav-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: 10px 14px;
        color: var(--color-muted);
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        border-radius: var(--radius-md);
        transition: background 0.15s ease, color 0.15s ease;
    }

    .sidebar-nav-item i {
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-nav-item:hover {
        background-color: var(--color-canvas);
        color: var(--color-primary);
    }

    .sidebar-nav-item.is-active {
        background-color: var(--color-primary-disabled) !important;
        color: var(--color-primary-active) !important;
        font-weight: 600;
    }

    .dashboard-mobile-header {
        display: none;
        justify-content: space-between;
        align-items: center;
        padding: 12px var(--spacing-md);
        background-color: #ffffff;
        border-bottom: 1px solid var(--color-hairline);
        width: 100%;
        box-sizing: border-box;
    }

    .dashboard-mobile-header .dashboard-logout-btn {
        padding: 8px 12px;
        background-color: #ffffff;
        border: 1px solid var(--color-hairline) !important;
        color: var(--color-muted) !important;
        text-decoration: none;
        border-radius: var(--radius-md);
    }

    @media (max-width: 860px) {
        body.has-sidebar {
            flex-direction: column !important;
            height: auto !important;
            max-height: none !important;
            overflow-y: auto !important;
        }
        body.has-sidebar .page-shell {
            grid-template-columns: 1fr !important;
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
            padding: var(--spacing-md) !important;
        }
        .dashboard-sidebar {
            display: none !important;
        }
        .dashboard-mobile-header {
            display: flex !important;
        }
    }
    </style>
</head>
<body class="<?= isLoggedIn() ? 'has-sidebar' : ''; ?>">
    <?php require __DIR__ . '/navbar.php'; ?>
    <main class="page-shell">
        <?php displayFlash(); ?>
