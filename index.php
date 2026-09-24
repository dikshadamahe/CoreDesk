<?php
// =====================================================================
// index.php
// Main Helpdesk Operations Dashboard with Real-Time KPIs & Ticket Triage
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

// Fetch aggregate operational metrics via single-pass raw SQL
$metricsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'In-Progress' THEN 1 ELSE 0 END) AS inprogress_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN priority = 'Critical' AND status != 'Resolved' THEN 1 ELSE 0 END) AS critical_count
    FROM tickets
");
$metrics = $metricsStmt->fetch() ?: [
    'total_tickets' => 0,
    'open_count' => 0,
    'inprogress_count' => 0,
    'resolved_count' => 0,
    'critical_count' => 0
];

// Role-tailored SQL Query for recent tickets
$sql = "
    SELECT 
        t.id,
        t.ticket_code,
        t.subject,
        t.priority,
        t.status,
        t.created_at,
        c.name AS category_name,
        u.name AS customer_name,
        u.email AS customer_email,
        a.name AS agent_name,
        (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count
    FROM tickets t
    INNER JOIN categories c ON t.category_id = c.id
    INNER JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_agent_id = a.id
";

$params = [];
if ($currentUser['role'] === 'customer') {
    // Customers only see their own tickets
    $sql .= " WHERE t.user_id = :uid";
    $params['uid'] = $currentUser['id'];
}
$sql .= " ORDER BY CASE t.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.created_at DESC LIMIT 15";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 26px; margin: 0 0 6px;">Support Operations Dashboard</h1>
        <p class="text-muted" style="margin: 0; font-size: 14px;">
            Logged in as <strong><?= e($currentUser['name']) ?></strong> (<?= ucfirst(e($currentUser['role'])) ?>). Real-time triage monitor.
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="create-ticket.php" class="btn btn-primary">+ Create New Ticket</a>
    </div>
</div>

<!-- KPI Metric Cards Grid -->
<div id="dashboard-metrics" class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div class="card metric-card" style="border-left: 4px solid var(--primary, #2563eb);">
        <div class="metric-title text-muted" style="font-size: 13px; font-weight: 600;">ACTIVE OPEN TICKETS</div>
        <div class="metric-value" id="metric-open" style="font-size: 32px; font-weight: 700; margin-top: 6px; color: #1e293b;">
            <?= (int)$metrics['open_count'] ?>
        </div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Awaiting triage or review</div>
    </div>

    <div class="card metric-card" style="border-left: 4px solid var(--warning, #eab308);">
        <div class="metric-title text-muted" style="font-size: 13px; font-weight: 600;">IN-PROGRESS INVESTIGATION</div>
        <div class="metric-value" id="metric-inprogress" style="font-size: 32px; font-weight: 700; margin-top: 6px; color: #d97706;">
            <?= (int)$metrics['inprogress_count'] ?>
        </div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Assigned to support engineers</div>
    </div>

    <div class="card metric-card" style="border-left: 4px solid var(--danger, #ef4444);">
        <div class="metric-title text-muted" style="font-size: 13px; font-weight: 600;">CRITICAL ESCALATIONS</div>
        <div class="metric-value" id="metric-critical" style="font-size: 32px; font-weight: 700; margin-top: 6px; color: #dc2626;">
            <?= (int)$metrics['critical_count'] ?>
        </div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">P0 severity requiring immediate SLA</div>
    </div>

    <div class="card metric-card" style="border-left: 4px solid var(--success, #10b981);">
        <div class="metric-title text-muted" style="font-size: 13px; font-weight: 600;">RESOLVED TICKETS</div>
        <div class="metric-value" id="metric-resolved" style="font-size: 32px; font-weight: 700; margin-top: 6px; color: #059669;">
            <?= (int)$metrics['resolved_count'] ?>
        </div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Verified closed inquiries</div>
    </div>
</div>

<!-- Ticket Triage Live Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 18px 24px;">
        <div>
            <h3 style="margin: 0; font-size: 17px;">Live Queue &amp; Incident Triage</h3>
            <span class="text-muted" style="font-size: 13px;">Showing recent active inquiries with instant AJAX transitions</span>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="table-search-input" class="form-control" placeholder="Search ticket #, keyword, client..." style="width: 260px; font-size: 13px;">
            <a href="tickets.php" class="btn btn-outline btn-sm">View All Tickets &rarr;</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width: 110px;">Ticket ID</th>
                    <th>Subject &amp; Category</th>
                    <th style="width: 160px;">Submitted By</th>
                    <th style="width: 110px;">Priority</th>
                    <th style="width: 140px;">Status</th>
                    <th style="width: 150px;">Assigned Agent</th>
                    <th style="width: 90px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px;" class="text-muted">
                            No tickets currently in this view. <a href="create-ticket.php">Create your first ticket</a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr class="js-ticket-row">
                            <td>
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" style="font-weight: 600; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #2563eb;">
                                    <?= e($t['ticket_code']) ?>
                                </a>
                            </td>
                            <td>
                                <div style="font-weight: 500; font-size: 14px;">
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                    <span class="badge badge-neutral" style="font-size: 11px;"><?= e($t['category_name']) ?></span>
                                    <?php if ($t['reply_count'] > 0): ?>
                                        <span style="margin-left: 6px;">💬 <?= (int)$t['reply_count'] ?> replies</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 500;"><?= e($t['customer_name']) ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?= e($t['customer_email']) ?></div>
                            </td>
                            <td>
                                <?php
                                    $pClass = match(strtolower($t['priority'])) {
                                        'critical' => 'badge-danger',
                                        'high' => 'badge-warning',
                                        'medium' => 'badge-primary',
                                        default => 'badge-neutral',
                                    };
                                ?>
                                <span class="badge <?= $pClass ?>"><?= e($t['priority']) ?></span>
                            </td>
                            <td>
                                <?php if ($currentUser['role'] === 'customer'): ?>
                                    <span class="badge badge-<?= strtolower(str_replace('-', '', $t['status'])) ?> js-status-badge-<?= (int)$t['id'] ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <!-- AJAX Quick Inline Select for Support Staff -->
                                    <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$t['id'] ?>" style="font-size: 12px; padding: 4px 8px; width: 125px;">
                                        <option value="Open" <?= $t['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                        <option value="In-Progress" <?= $t['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                                        <option value="Resolved" <?= $t['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="Closed" <?= $t['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: <?= $t['agent_name'] ? '#334155' : '#94a3b8' ?>;">
                                    <?= e($t['agent_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 12px;">
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
