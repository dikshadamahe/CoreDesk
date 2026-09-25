<?php
// =====================================================================
// includes/header.php
// Clean High-End SaaS Navigation (Zero 3rd party branding)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

$currentUser = getLoggedInUser();
$csrfToken = generateCsrfToken();
$activePage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CoreDesk - High-performance Helpdesk & Support Ticketing System built in Pure Core PHP, MySQL & Vanilla JS">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | CoreDesk' : 'CoreDesk — Ticketing & Support' ?></title>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- CoreDesk Design System -->
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>

<header class="site-navbar">
    <div class="container navbar-inner">
        <!-- Logo -->
        <a href="index.php" class="brand-wrap">
            <span>Core<span class="brand-highlight">Desk</span></span>
        </a>

        <!-- Center Navigation -->
        <nav class="nav-links-menu">
            <a href="index.php" class="nav-item-link <?= $activePage === 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="tickets.php" class="nav-item-link <?= $activePage === 'tickets.php' ? 'active' : '' ?>">Tickets Queue</a>
            <a href="create-ticket.php" class="nav-item-link <?= $activePage === 'create-ticket.php' ? 'active' : '' ?>">New Ticket</a>
            <a href="https://github.com/dikshadamahe/CoreDesk" target="_blank" rel="noopener" class="nav-item-link">Architecture</a>
        </nav>

        <!-- Right User Actions -->
        <div class="nav-right-actions">
            <?php if ($currentUser): ?>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <span style="font-weight: 600; color: var(--text-heading);"><?= e($currentUser['name']) ?></span>
                    <span class="status-pill status-open" style="font-size: 10px; padding: 2px 7px;">
                        <?= strtoupper(e($currentUser['role'])) ?>
                    </span>
                </div>
                <a href="create-ticket.php" class="btn btn-primary btn-sm">
                    + Raise Ticket
                </a>
                <a href="logout.php" class="btn btn-secondary btn-sm" title="Sign Out">
                    Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm">
                    Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main style="flex: 1; padding: 36px 0 48px;">
    <div class="container">
