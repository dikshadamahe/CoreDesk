<?php
// =====================================================================
// index.php
// Clean SaaS Dashboard (Exact theme matching user reference)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

// Single-pass raw SQL aggregation
$metricsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'In-Progress' THEN 1 ELSE 0 END) AS inprogress_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN priority = 'Critical' AND status != 'Resolved' AND status != 'Closed' THEN 1 ELSE 0 END) AS critical_count
    FROM tickets
");
$metrics = $metricsStmt->fetch() ?: [
    'total_tickets' => 0,
    'open_count' => 0,
    'inprogress_count' => 0,
    'resolved_count' => 0,
    'critical_count' => 0
];

$activeTab = trim($_GET['tab'] ?? 'all');

$sql = "
    SELECT 
        t.id,
        t.ticket_code,
        t.subject,
        t.priority,
        t.status,
        t.created_at,
        t.updated_at,
        c.name AS category_name,
        u.name AS customer_name,
        u.email AS customer_email,
        a.name AS agent_name,
        (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count
    FROM tickets t
    INNER JOIN categories c ON t.category_id = c.id
    INNER JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_agent_id = a.id
    WHERE 1=1
";

$params = [];
if ($currentUser['role'] === 'customer') {
    $sql .= " AND t.user_id = :uid";
    $params['uid'] = $currentUser['id'];
}

if ($activeTab === 'open') {
    $sql .= " AND t.status = 'Open'";
} elseif ($activeTab === 'inprogress') {
    $sql .= " AND t.status = 'In-Progress'";
} elseif ($activeTab === 'resolved') {
    $sql .= " AND t.status = 'Resolved'";
} elseif ($activeTab === 'critical') {
    $sql .= " AND t.priority = 'Critical'";
}

$sql .= " ORDER BY CASE t.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.created_at DESC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Incident Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header (Exact Match to User Reference Screenshot) -->
<div style="margin-bottom: 36px;">
    <div class="hero-pill">
        <span class="hero-pill-dot"></span>
        <span>Built on Core PHP, MySQL, vanilla JS</span>
    </div>

    <h1 style="font-size: 38px; font-weight: 800; letter-spacing: -0.035em; color: var(--text-heading); margin-bottom: 14px; line-height: 1.2;">
        One queue for tickets.<br>
        One thread for <span style="color: var(--primary);">conversations.</span>
    </h1>

    <p style="font-size: 16px; color: var(--text-muted); max-width: 680px; line-height: 1.6; margin-bottom: 24px;">
        CoreDesk gives every request a structured ticket and every customer a real-time thread &mdash; issue tracking and live chat, running on a codebase you can actually read end to end.
    </p>

    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <a href="create-ticket.php" class="btn btn-primary">
            Create Ticket
        </a>
        <a href="https://github.com/dikshadamahe/CoreDesk" target="_blank" rel="noopener" class="btn btn-secondary">
            Read the architecture
        </a>

        <!-- Fast Role Switcher Pills (Zero Emojis, Direct Instant Switch) -->
        <div style="margin-left: auto; display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">SWITCH ROLE:</span>
            <a href="switch-role.php?role=admin" class="btn btn-secondary btn-sm" style="<?= $currentUser['role'] === 'admin' ? 'border-color: var(--primary); color: var(--primary); font-weight: 700; background: var(--primary-subtle);' : '' ?>">
                Admin
            </a>
            <a href="switch-role.php?role=agent" class="btn btn-secondary btn-sm" style="<?= $currentUser['role'] === 'agent' ? 'border-color: var(--primary); color: var(--primary); font-weight: 700; background: var(--primary-subtle);' : '' ?>">
                Support
            </a>
            <a href="switch-role.php?role=customer" class="btn btn-secondary btn-sm" style="<?= $currentUser['role'] === 'customer' ? 'border-color: var(--primary); color: var(--primary); font-weight: 700; background: var(--primary-subtle);' : '' ?>">
                Client
            </a>
        </div>
    </div>
</div>

<!-- 4 Elevated Metric Cards -->
<div class="metrics-grid">
    <div class="metric-card">
        <span class="metric-label">Active Open</span>
        <div class="metric-value" id="metric-open"><?= (int)$metrics['open_count'] ?></div>
        <div class="metric-sub">Awaiting engineering triage</div>
    </div>

    <div class="metric-card">
        <span class="metric-label">In-Progress</span>
        <div class="metric-value" id="metric-inprogress" style="color: #b45309;"><?= (int)$metrics['inprogress_count'] ?></div>
        <div class="metric-sub">Under active investigation</div>
    </div>

    <div class="metric-card">
        <span class="metric-label">Critical Escalations</span>
        <div class="metric-value" id="metric-critical" style="color: #dc2626;"><?= (int)$metrics['critical_count'] ?></div>
        <div class="metric-sub">High-severity blockers</div>
    </div>

    <div class="metric-card">
        <span class="metric-label">Resolved Tickets</span>
        <div class="metric-value" id="metric-resolved" style="color: #047857;"><?= (int)$metrics['resolved_count'] ?></div>
        <div class="metric-sub">Successfully closed inquiries</div>
    </div>
</div>

<!-- Tickets Queue Table Card -->
<div class="table-container">
    <div class="table-toolbar">
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="index.php?tab=all" class="btn btn-sm <?= $activeTab === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="index.php?tab=open" class="btn btn-sm <?= $activeTab === 'open' ? 'btn-primary' : 'btn-secondary' ?>">Open</a>
            <a href="index.php?tab=inprogress" class="btn btn-sm <?= $activeTab === 'inprogress' ? 'btn-primary' : 'btn-secondary' ?>">In-Progress</a>
            <a href="index.php?tab=critical" class="btn btn-sm <?= $activeTab === 'critical' ? 'btn-primary' : 'btn-secondary' ?>" style="<?= $activeTab !== 'critical' ? 'color: #dc2626;' : '' ?>">Critical</a>
            <a href="index.php?tab=resolved" class="btn btn-sm <?= $activeTab === 'resolved' ? 'btn-primary' : 'btn-secondary' ?>">Resolved</a>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="table-search-input" class="form-control form-control-sm" placeholder="Search ticket #, title, client..." style="width: 250px;">
            <a href="tickets.php" class="btn btn-secondary btn-sm">Full Queue &rarr;</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 100px;">Ticket #</th>
                    <th>Issue Summary &amp; Category</th>
                    <th style="width: 170px;">Requester</th>
                    <th style="width: 110px;">Priority</th>
                    <th style="width: 150px;">Status</th>
                    <th style="width: 150px;">Assigned</th>
                    <th style="width: 90px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                            No tickets currently in this view.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr class="js-ticket-row">
                            <td>
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="ticket-key">
                                    <?= e($t['ticket_code']) ?>
                                </a>
                            </td>
                            <td>
                                <div>
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="ticket-title">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center; margin-top: 3px; font-size: 12px; color: var(--text-muted);">
                                    <span class="category-tag"><?= e($t['category_name']) ?></span>
                                    <?php if ($t['reply_count'] > 0): ?>
                                        <span><?= (int)$t['reply_count'] ?> replies</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px; color: var(--text-heading);"><?= e($t['customer_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($t['customer_email']) ?></div>
                            </td>
                            <td>
                                <span class="priority-pill priority-<?= strtolower($t['priority']) ?>">
                                    <?= e($t['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($currentUser['role'] === 'customer'): ?>
                                    <span class="status-pill status-<?= strtolower(str_replace('-', '', $t['status'])) ?> js-status-badge-<?= (int)$t['id'] ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$t['id'] ?>" style="font-size: 12px; font-weight: 600; padding: 4px 8px; width: 130px;">
                                        <option value="Open" <?= $t['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                        <option value="In-Progress" <?= $t['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                                        <option value="Resolved" <?= $t['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="Closed" <?= $t['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: <?= $t['agent_name'] ? 'var(--text-heading)' : 'var(--text-subtlest)' ?>;">
                                    <?= e($t['agent_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px;">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
