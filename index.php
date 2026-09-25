<?php
// =====================================================================
// index.php
// Zendesk Style Incident Views & Operations Queue (Matching Photo 4)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

// Aggregate metrics
$metricsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'In-Progress' THEN 1 ELSE 0 END) AS inprogress_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN priority = 'Critical' AND status != 'Resolved' AND status != 'Closed' THEN 1 ELSE 0 END) AS critical_count,
        SUM(CASE WHEN assigned_agent_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
    FROM tickets
");
$metrics = $metricsStmt->fetch() ?: [
    'total_tickets' => 0,
    'open_count' => 0,
    'inprogress_count' => 0,
    'resolved_count' => 0,
    'critical_count' => 0,
    'unassigned_count' => 0
];

$activeView = trim($_GET['view'] ?? 'all');

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

if ($activeView === 'open') {
    $sql .= " AND t.status = 'Open'";
} elseif ($activeView === 'inprogress') {
    $sql .= " AND t.status = 'In-Progress'";
} elseif ($activeView === 'critical') {
    $sql .= " AND t.priority = 'Critical'";
} elseif ($activeView === 'unassigned') {
    $sql .= " AND t.assigned_agent_id IS NULL";
} elseif ($activeView === 'resolved') {
    $sql .= " AND t.status = 'Resolved'";
}

$sql .= " ORDER BY CASE t.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.created_at DESC LIMIT 25";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Views';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Zendesk Views & Queue Layout (Matching Photo 4) -->
<div class="zd-queue-workspace">
    
    <!-- Left Column: Views Navigation -->
    <aside class="zd-views-nav">
        <div class="zd-views-title">Views</div>
        
        <a href="index.php?view=all" class="zd-view-link <?= $activeView === 'all' ? 'active' : '' ?>">
            <span>All Open Tickets</span>
            <span class="zd-view-count"><?= (int)$metrics['open_count'] + (int)$metrics['inprogress_count'] ?></span>
        </a>

        <a href="index.php?view=critical" class="zd-view-link <?= $activeView === 'critical' ? 'active' : '' ?>">
            <span>Urgent &amp; High Priority</span>
            <span class="zd-view-count" style="color: #c33b24;"><?= (int)$metrics['critical_count'] ?></span>
        </a>

        <a href="index.php?view=unassigned" class="zd-view-link <?= $activeView === 'unassigned' ? 'active' : '' ?>">
            <span>Unassigned Tickets</span>
            <span class="zd-view-count"><?= (int)$metrics['unassigned_count'] ?></span>
        </a>

        <a href="index.php?view=inprogress" class="zd-view-link <?= $activeView === 'inprogress' ? 'active' : '' ?>">
            <span>In-Progress Investigation</span>
            <span class="zd-view-count"><?= (int)$metrics['inprogress_count'] ?></span>
        </a>

        <a href="index.php?view=resolved" class="zd-view-link <?= $activeView === 'resolved' ? 'active' : '' ?>">
            <span>Recently Solved</span>
            <span class="zd-view-count"><?= (int)$metrics['resolved_count'] ?></span>
        </a>

        <div style="margin-top: auto; padding: 16px; border-top: 1px solid var(--zd-border-subtle);">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--zd-text-subtle); margin-bottom: 6px;">
                Session User
            </div>
            <div style="font-weight: 600; font-size: 13px; color: var(--zd-text-main);"><?= e($currentUser['name']) ?></div>
            <div style="font-size: 11.5px; color: var(--zd-text-muted); text-transform: capitalize;"><?= e($currentUser['role']) ?></div>
        </div>
    </aside>

    <!-- Right Column: Queue Table -->
    <div style="display: flex; flex-direction: column; background: #ffffff;">
        
        <!-- Header Strip -->
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--zd-border); display: flex; justify-content: space-between; align-items: center; background: #ffffff;">
            <div>
                <h1 style="font-size: 16px; font-weight: 700; color: var(--zd-text-main); margin: 0;">
                    <?php
                        echo match($activeView) {
                            'critical'   => 'Urgent & High Priority Tickets',
                            'unassigned' => 'Unassigned Tickets',
                            'inprogress' => 'In-Progress Investigation',
                            'resolved'   => 'Recently Solved Tickets',
                            default      => 'All Tickets View'
                        };
                    ?>
                </h1>
                <span style="font-size: 12px; color: var(--zd-text-muted);">
                    Showing <?= count($tickets) ?> matching tickets
                </span>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" id="table-search-input" class="zd-search-input" placeholder="Filter by keyword, client...">
                <a href="create-ticket.php" class="btn btn-primary btn-sm">+ Add Ticket</a>
            </div>
        </div>

        <!-- Table -->
        <div class="zd-table-wrap">
            <table class="zd-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 90px;">Ticket #</th>
                        <th>Subject &amp; Category</th>
                        <th style="width: 170px;">Requester</th>
                        <th style="width: 100px;">Priority</th>
                        <th style="width: 150px;">Assignee</th>
                        <th style="width: 120px;">Requested</th>
                        <th style="width: 80px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: var(--zd-text-muted);">
                                No tickets currently in this view.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr class="js-ticket-row">
                                <td>
                                    <?php
                                        $badgeClass = match(strtolower($t['status'])) {
                                            'open'        => 'zd-badge-open',
                                            'in-progress' => 'zd-badge-progress',
                                            'resolved'    => 'zd-badge-solved',
                                            default       => 'zd-badge-closed'
                                        };
                                    ?>
                                    <span class="zd-badge <?= $badgeClass ?> js-status-badge-<?= (int)$t['id'] ?>">
                                        <?= strtoupper(e($t['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: #17494d;">
                                        <?= e($t['ticket_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div>
                                        <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" style="font-size: 13.5px; font-weight: 600; color: var(--zd-text-main);">
                                            <?= e($t['subject']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--zd-text-muted); margin-top: 2px;">
                                        <span><?= e($t['category_name']) ?></span>
                                        <?php if ($t['reply_count'] > 0): ?>
                                            <span> &middot; <?= (int)$t['reply_count'] ?> replies</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;"><?= e($t['customer_name']) ?></div>
                                    <div style="font-size: 11px; color: var(--zd-text-muted);"><?= e($t['customer_email']) ?></div>
                                </td>
                                <td>
                                    <span class="zd-badge <?= strtolower($t['priority']) === 'critical' ? 'zd-badge-open' : 'zd-badge-progress' ?>">
                                        <?= e($t['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12.5px; color: <?= $t['agent_name'] ? 'var(--zd-text-main)' : 'var(--zd-text-subtle)' ?>;">
                                        <?= e($t['agent_name'] ?? 'Unassigned') ?>
                                    </span>
                                </td>
                                <td style="font-size: 11.5px; color: var(--zd-text-muted);">
                                    <?= date('M d, H:i', strtotime($t['created_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 3px 8px;">
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

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
