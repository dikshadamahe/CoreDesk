<?php
// =====================================================================
// includes/header.php
// Enterprise MNC Shell Layout (Atlassian Jira & Chatwoot Standard)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

$currentUser = getLoggedInUser();
$csrfToken = generateCsrfToken();
$activePage = basename($_SERVER['PHP_SELF']);

// Generate user initials for avatar
$initials = 'CD';
if ($currentUser && !empty($currentUser['name'])) {
    $parts = explode(' ', trim($currentUser['name']));
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CoreDesk - High-performance Enterprise Incident & Support Desk Portal engineered in pure Core PHP & Vanilla JS">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | CoreDesk Service Management' : 'CoreDesk — Enterprise Helpdesk' ?></title>
    
    <!-- Enterprise Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Enterprise Design System -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (empty($isAuthPage)): ?>
<div class="app-shell">
    
    <!-- Left Navigation Sidebar (Jira & Chatwoot Hybrid) -->
    <aside class="app-sidebar">
        <div>
            <div class="sidebar-brand-box">
                <div class="brand-icon-sq">⚡</div>
                <div class="brand-meta">
                    <span class="brand-title">Core<strong>Desk</strong></span>
                    <span class="brand-subtitle">
                        <span class="brand-live-pulse"></span>
                        Jira &middot; Service Desk
                    </span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-title">Operations</div>
                
                <a href="index.php" class="nav-item <?= $activePage === 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                
                <a href="tickets.php" class="nav-item <?= $activePage === 'tickets.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📥</span>
                    <span>Ticket Queues</span>
                    <span class="nav-badge-pill">Live</span>
                </a>

                <a href="create-ticket.php" class="nav-item <?= $activePage === 'create-ticket.php' ? 'active' : '' ?>">
                    <span class="nav-icon">➕</span>
                    <span>Raise Incident</span>
                </a>

                <div class="nav-section-title" style="margin-top: 14px;">Service Hubs</div>
                
                <a href="tickets.php?priority=Critical" class="nav-item <?= (isset($_GET['priority']) && $_GET['priority'] === 'Critical') ? 'active' : '' ?>">
                    <span class="nav-icon">🚨</span>
                    <span>P0 Escalations</span>
                </a>

                <a href="tickets.php?status=In-Progress" class="nav-item <?= (isset($_GET['status']) && $_GET['status'] === 'In-Progress') ? 'active' : '' ?>">
                    <span class="nav-icon">🛠️</span>
                    <span>Under Investigation</span>
                </a>
            </nav>
        </div>

        <div>
            <!-- Live SLA Uptime Metric -->
            <div class="sidebar-sla-card">
                <div class="sla-header">
                    <span>SLA Compliance</span>
                    <strong style="color: #36B37E;">99.8%</strong>
                </div>
                <div class="sla-progress-track">
                    <div class="sla-progress-bar"></div>
                </div>
            </div>

            <!-- Authenticated User Profile -->
            <div class="sidebar-footer">
                <?php if ($currentUser): ?>
                    <div class="user-profile-widget">
                        <div class="user-avatar-circle"><?= e($initials) ?></div>
                        <div class="user-details-text">
                            <div class="user-name-label"><?= e($currentUser['name']) ?></div>
                            <div class="user-role-label">
                                <span class="lozenge <?= $currentUser['role'] === 'admin' ? 'lozenge-closed' : ($currentUser['role'] === 'agent' ? 'lozenge-inprogress' : 'lozenge-open') ?>" style="font-size: 9.5px; padding: 1px 5px;">
                                    <?= strtoupper(e($currentUser['role'])) ?>
                                </span>
                            </div>
                        </div>
                        <a href="logout.php" class="user-logout-link" title="Sign Out">⎋</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm" style="width: 100%;">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- Main Content Canvas -->
    <div class="app-main">
        <!-- Top Application Header -->
        <header class="app-topbar">
            <div class="topbar-left">
                <nav class="breadcrumb-nav">
                    <span>CoreDesk</span>
                    <span class="breadcrumb-sep">/</span>
                    <span style="color: var(--text-heading); font-weight: 600;"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></span>
                </nav>
            </div>

            <div class="topbar-center">
                <div class="global-search-box">
                    <span class="search-icon-hint">🔍</span>
                    <input type="text" id="global-search-bar" placeholder="Search incidents, stack traces, client IDs...">
                    <span class="search-kbd-hint">⌘K</span>
                </div>
            </div>

            <div class="topbar-right">
                <div class="system-status-indicator">
                    <span class="system-status-dot"></span>
                    <span>Enterprise Tier</span>
                </div>

                <a href="create-ticket.php" class="btn btn-primary btn-sm">
                    + Raise Incident
                </a>
            </div>
        </header>

        <!-- Page Container -->
        <main class="page-container">
<?php endif; ?>
