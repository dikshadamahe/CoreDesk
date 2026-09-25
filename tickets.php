<?php
// =====================================================================
// tickets.php
// Enterprise Multi-Parameter Service Queue (Jira & Chatwoot Standard)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

// Fetch Categories for Filter Dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Capture Query Filters
$filterStatus   = trim($_GET['status'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterSearch   = trim($_GET['q'] ?? '');

// Dynamic Hand-Written Prepared SQL
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

if (!empty($filterStatus)) {
    $sql .= " AND t.status = :status";
    $params['status'] = $filterStatus;
}

if (!empty($filterPriority)) {
    $sql .= " AND t.priority = :priority";
    $params['priority'] = $filterPriority;
}

if (!empty($filterCategory)) {
    $sql .= " AND t.category_id = :category_id";
    $params['category_id'] = (int)$filterCategory;
}

if (!empty($filterSearch)) {
    $sql .= " AND (t.ticket_code LIKE :s1 OR t.subject LIKE :s2 OR t.description LIKE :s3 OR u.name LIKE :s4)";
    $like = '%' . $filterSearch . '%';
    $params['s1'] = $like;
    $params['s2'] = $like;
    $params['s3'] = $like;
    $params['s4'] = $like;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Incident Queues & Triage';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">Service Queues</h1>
        <p style="color: var(--text-muted); font-size: 13.5px; margin: 0;">
            Filter by SLA severity, triage status, and assigned engineering specialists.
        </p>
    </div>
    <a href="create-ticket.php" class="btn btn-primary">
        + Create Incident
    </a>
</div>

<!-- Filter Bar Card -->
<div class="card" style="margin-bottom: 20px; padding: 16px 20px; background: #FFFFFF;">
    <form method="GET" action="tickets.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 100px; gap: 12px; align-items: end;">
        <div>
            <label style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Search Keywords</label>
            <input type="text" name="q" value="<?= e($filterSearch) ?>" class="form-control form-control-sm" placeholder="Search ticket #, trace, client...">
        </div>

        <div>
            <label style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Status</label>
            <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                <option value="Open" <?= $filterStatus === 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In-Progress" <?= $filterStatus === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                <option value="Resolved" <?= $filterStatus === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div>
            <label style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Priority</label>
            <select name="priority" class="form-control form-control-sm">
                <option value="">All Priorities</option>
                <option value="Critical" <?= $filterPriority === 'Critical' ? 'selected' : '' ?>>▲▲ Critical</option>
                <option value="High" <?= $filterPriority === 'High' ? 'selected' : '' ?>>▲ High</option>
                <option value="Medium" <?= $filterPriority === 'Medium' ? 'selected' : '' ?>>〓 Medium</option>
                <option value="Low" <?= $filterPriority === 'Low' ? 'selected' : '' ?>>▼ Low</option>
            </select>
        </div>

        <div>
            <label style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Category</label>
            <select name="category" class="form-control form-control-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $filterCategory == (string)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%; padding: 7px;">
                Filter
            </button>
        </div>
    </form>
</div>

<!-- Results Table -->
<div class="queue-table-container">
    <div class="queue-table-toolbar">
        <span style="font-weight: 600; font-size: 13.5px; color: var(--text-heading);">
            Matching Incidents: <?= count($tickets) ?>
        </span>
        <?php if (!empty($filterStatus) || !empty($filterPriority) || !empty($filterCategory) || !empty($filterSearch)): ?>
            <a href="tickets.php" style="font-size: 12px; color: #DE350B; font-weight: 600; text-decoration: none;">&times; Reset Filters</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table-jira">
            <thead>
                <tr>
                    <th style="width: 105px;">Key</th>
                    <th>Issue Summary &amp; Category</th>
                    <th style="width: 170px;">Requester</th>
                    <th style="width: 110px;">Priority</th>
                    <th style="width: 150px;">Status</th>
                    <th style="width: 160px;">Assignee</th>
                    <th style="width: 130px;">Last Activity</th>
                    <th style="width: 90px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px;" class="text-muted">
                            No tickets matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="ticket-key">
                                    <?= e($t['ticket_code']) ?>
                                </a>
                            </td>
                            <td>
                                <div>
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="ticket-summary-title">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                                <div class="ticket-meta-row">
                                    <span class="tag-category"><?= e($t['category_name']) ?></span>
                                    <?php if ($t['reply_count'] > 0): ?>
                                        <span>💬 <?= (int)$t['reply_count'] ?> replies</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="user-pill">
                                    <div class="user-pill-avatar" style="background: #403294;">
                                        <?= strtoupper(substr($t['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 12.5px;"><?= e($t['customer_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= e($t['customer_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $p = strtolower($t['priority']);
                                    $icon = match($p) {
                                        'critical' => '▲▲',
                                        'high'     => '▲',
                                        'medium'   => '〓',
                                        default    => '▼'
                                    };
                                    $chipClass = 'priority-' . $p;
                                ?>
                                <span class="priority-chip <?= $chipClass ?>">
                                    <span style="font-size: 10px;"><?= $icon ?></span>
                                    <span><?= e($t['priority']) ?></span>
                                </span>
                            </td>
                            <td>
                                <?php if ($currentUser['role'] === 'customer'): ?>
                                    <?php
                                        $lozengeClass = match(strtolower($t['status'])) {
                                            'open'        => 'lozenge-open',
                                            'in-progress' => 'lozenge-inprogress',
                                            'resolved'    => 'lozenge-resolved',
                                            default       => 'lozenge-closed'
                                        };
                                    ?>
                                    <span class="lozenge <?= $lozengeClass ?>">
                                        <?= strtoupper(e($t['status'])) ?>
                                    </span>
                                <?php else: ?>
                                    <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$t['id'] ?>" style="font-size: 12px; font-weight: 600; padding: 4px 8px; width: 135px;">
                                        <option value="Open" <?= $t['status'] === 'Open' ? 'selected' : '' ?>>🔵 Open</option>
                                        <option value="In-Progress" <?= $t['status'] === 'In-Progress' ? 'selected' : '' ?>>🟡 In-Progress</option>
                                        <option value="Resolved" <?= $t['status'] === 'Resolved' ? 'selected' : '' ?>>🟢 Resolved</option>
                                        <option value="Closed" <?= $t['status'] === 'Closed' ? 'selected' : '' ?>>⚪ Closed</option>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['agent_name']): ?>
                                    <div class="user-pill">
                                        <div class="user-pill-avatar" style="background: #0052CC;">
                                            <?= strtoupper(substr($t['agent_name'], 0, 1)) ?>
                                        </div>
                                        <span style="font-size: 12.5px; font-weight: 500;"><?= e($t['agent_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-subtlest); font-size: 12px; font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= date('M d, H:i', strtotime($t['updated_at'] ?? $t['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 3px 10px;">
                                    View &rarr;
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
