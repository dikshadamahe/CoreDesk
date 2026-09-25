<?php
// =====================================================================
// includes/header.php
// Zendesk Agent Workspace Layout (Zero Emojis, No Frameworks)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

$currentUser = getLoggedInUser();
$csrfToken = generateCsrfToken();
$activePage = basename($_SERVER['PHP_SELF']);

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
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | CoreDesk' : 'CoreDesk' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (empty($isAuthPage)): ?>
<div class="zd-app-layout">
    
    <!-- 1. Left Icon Rail (Zendesk Vertical Rail) -->
    <aside class="zd-icon-rail">
        <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
            <a href="index.php" class="zd-rail-brand" title="CoreDesk">
                CD
            </a>

            <nav class="zd-rail-nav">
                <a href="index.php" class="zd-rail-btn <?= $activePage === 'index.php' ? 'active' : '' ?>" title="Dashboard Views">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </a>
                
                <a href="tickets.php" class="zd-rail-btn <?= $activePage === 'tickets.php' ? 'active' : '' ?>" title="Ticket Views">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </a>

                <a href="create-ticket.php" class="zd-rail-btn <?= $activePage === 'create-ticket.php' ? 'active' : '' ?>" title="Add Ticket">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </a>

                <a href="https://github.com/dikshadamahe/CoreDesk" target="_blank" rel="noopener" class="zd-rail-btn" title="Architecture Repository">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                </a>
            </nav>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <div class="zd-rail-avatar" title="<?= e($currentUser['name'] ?? '') ?> (<?= e($currentUser['role'] ?? '') ?>)">
                <?= e($initials) ?>
            </div>
            <a href="logout.php" class="zd-rail-btn" title="Sign Out" style="width: 32px; height: 32px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </aside>

    <!-- 2. Main Pane -->
    <div class="zd-main-pane">
        <!-- Top Workspace Bar with Ticket Tabs (Photo 5) -->
        <header class="zd-top-bar">
            <div class="zd-tabs-wrap">
                <a href="index.php" class="zd-tab <?= $activePage === 'index.php' ? 'active' : '' ?>">
                    <span>Dashboard Views</span>
                </a>
                
                <a href="tickets.php" class="zd-tab <?= $activePage === 'tickets.php' ? 'active' : '' ?>">
                    <span>Tickets Queue</span>
                </a>

                <?php if (isset($ticket) && !empty($ticket['ticket_code'])): ?>
                    <a href="ticket-view.php?id=<?= (int)$ticket['id'] ?>" class="zd-tab active">
                        <span class="zd-tab-status"></span>
                        <span><?= e($ticket['ticket_code']) ?> &middot; <?= e(substr($ticket['subject'], 0, 24)) ?>...</span>
                    </a>
                <?php endif; ?>

                <a href="create-ticket.php" class="zd-tab <?= $activePage === 'create-ticket.php' ? 'active' : '' ?>" style="color: var(--zd-primary); font-weight: 600;">
                    <span>+ Add</span>
                </a>
            </div>

            <div class="zd-top-tools">
                <input type="text" id="global-search-bar" class="zd-search-input" placeholder="Search tickets, clients...">

                <!-- Role Switcher -->
                <div style="display: flex; align-items: center; gap: 4px;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--zd-text-subtle); margin-right: 4px;">ROLE:</span>
                    <a href="switch-role.php?role=admin" class="zd-role-pill-btn <?= ($currentUser['role'] ?? '') === 'admin' ? 'active' : '' ?>">
                        Admin
                    </a>
                    <a href="switch-role.php?role=agent" class="zd-role-pill-btn <?= ($currentUser['role'] ?? '') === 'agent' ? 'active' : '' ?>">
                        Support
                    </a>
                    <a href="switch-role.php?role=customer" class="zd-role-pill-btn <?= ($currentUser['role'] ?? '') === 'customer' ? 'active' : '' ?>">
                        Client
                    </a>
                </div>
            </div>
        </header>

        <main style="flex: 1; display: flex; flex-direction: column;">
<?php endif; ?>
