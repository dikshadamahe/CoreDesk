<?php
// =====================================================================
// includes/header.php
// Reusable Application Navigation & Head Layout
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

$currentUser = getLoggedInUser();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CoreDesk - High-performance Web Helpdesk & Support Ticketing System built in pure Core PHP and Vanilla JS">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | CoreDesk' : 'CoreDesk — Support Ticketing System' ?></title>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Core Native Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="brand">
                <span class="brand-icon">⚡</span>
                <span>Core<strong>Desk</strong></span>
                <span class="badge badge-neutral" style="margin-left:8px; font-size:11px;">Core PHP 8.x</span>
            </a>

            <nav class="nav-links">
                <a href="index.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">Dashboard</a>
                <a href="tickets.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'tickets.php') ? 'active' : '' ?>">Tickets</a>
                <a href="create-ticket.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'create-ticket.php') ? 'active' : '' ?>">+ New Ticket</a>
            </nav>

            <div class="user-panel">
                <?php if ($currentUser): ?>
                    <div class="user-info">
                        <span class="user-name"><?= e($currentUser['name']) ?></span>
                        <span class="badge badge-<?= $currentUser['role'] === 'admin' ? 'danger' : ($currentUser['role'] === 'agent' ? 'primary' : 'neutral') ?>">
                            <?= ucfirst(e($currentUser['role'])) ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main class="main-content container">
