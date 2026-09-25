<?php
// =====================================================================
// tickets.php
// Clean SaaS Ticket Queue Management (CoreDesk Standard)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$filterStatus   = trim($_GET['status'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterSearch   = trim($_GET['q'] ?? '');

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

$pageTitle = 'All Tickets';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 4px;">Support Tickets</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin: 0;">
            Filter and inspect requests, assignments, and conversation updates.
        </p>
    </div>
    <a href="create-ticket.php" class="btn btn-primary">
        + New Ticket
    </a>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="tickets.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 110px; gap: 14px; align-items: end;">
        <div>
            <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px;">Search Keyword</label>
            <input type="text" name="q" value="<?= e($filterSearch) ?>" class="form-control form-control-sm" placeholder="Ticket #, subject, text...">
        </div>

        <div>
            <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px;">Status</label>
            <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                <option value="Open" <?= $filterStatus === 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In-Progress" <?= $filterStatus === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                <option value="Resolved" <?= $filterStatus === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div>
            <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px;">Priority</label>
            <select name="priority" class="form-control form-control-sm">
                <option value="">All Priorities</option>
                <option value="Critical" <?= $filterPriority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                <option value="High" <?= $filterPriority === 'High' ? 'selected' : '' ?>>High</option>
                <option value="Medium" <?= $filterPriority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="Low" <?= $filterPriority === 'Low' ? 'selected' : '' ?>>Low</option>
            </select>
        </div>

        <div>
            <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px;">Category</label>
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
            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%; padding: 8px;">
                Filter
            </button>
        </div>
    </form>
</div>

<!-- Results Table -->
<div class="table-container">
    <div class="table-toolbar">
        <span style="font-weight: 600; font-size: 14px; color: var(--text-heading);">
            Total Inquiries: <?= count($tickets) ?>
        </span>
        <?php if (!empty($filterStatus) || !empty($filterPriority) || !empty($filterCategory) || !empty($filterSearch)): ?>
            <a href="tickets.php" style="font-size: 12.5px; color: #dc2626; font-weight: 600;">&times; Clear Filters</a>
        <?php endif; ?>
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
                    <th style="width: 130px;">Updated</th>
                    <th style="width: 80px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
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
                                    <a href="ticket-view.php?id=<?= (int)$t['id'] ?>" class="ticket-title">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center; margin-top: 3px; font-size: 12px; color: var(--text-muted);">
                                    <span class="category-tag"><?= e($t['category_name']) ?></span>
                                    <?php if ($t['reply_count'] > 0): ?>
                                        <span>💬 <?= (int)$t['reply_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;"><?= e($t['customer_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($t['customer_email']) ?></div>
                            </td>
                            <td>
                                <span class="priority-pill priority-<?= strtolower($t['priority']) ?>">
                                    <?= e($t['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($currentUser['role'] === 'customer'): ?>
                                    <span class="status-pill status-<?= strtolower(str_replace('-', '', $t['status'])) ?>">
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
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= date('M d, H:i', strtotime($t['updated_at'] ?? $t['created_at'])) ?>
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
