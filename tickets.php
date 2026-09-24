<?php
// =====================================================================
// tickets.php
// Full Ticket Queue Management with Multi-Parameter SQL Filtering
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

// Fetch Categories for Filter Dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Capture Query Filters
$filterStatus = trim($_GET['status'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterSearch = trim($_GET['q'] ?? '');

// Build Hand-Written Dynamic Raw SQL with Prepared Bindings
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
    $sql .= " AND (t.ticket_code LIKE :search1 OR t.subject LIKE :search2 OR t.description LIKE :search3 OR u.name LIKE :search4)";
    $like = '%' . $filterSearch . '%';
    $params['search1'] = $like;
    $params['search2'] = $like;
    $params['search3'] = $like;
    $params['search4'] = $like;
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
        <h1 style="font-size: 26px; margin: 0 0 6px;">Support Ticket Queue</h1>
        <p class="text-muted" style="margin: 0; font-size: 14px;">
            Filter and inspect all client tickets, audit trails, and status escalations.
        </p>
    </div>
    <a href="create-ticket.php" class="btn btn-primary">+ Submit Ticket</a>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="tickets.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 12px; align-items: end;">
        <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 6px;">Keyword / Code</label>
            <input type="text" name="q" value="<?= e($filterSearch) ?>" class="form-control form-control-sm" placeholder="Search ticket #, text...">
        </div>

        <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 6px;">Status</label>
            <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                <option value="Open" <?= $filterStatus === 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In-Progress" <?= $filterStatus === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                <option value="Resolved" <?= $filterStatus === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 6px;">Priority</label>
            <select name="priority" class="form-control form-control-sm">
                <option value="">All Priorities</option>
                <option value="Critical" <?= $filterPriority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                <option value="High" <?= $filterPriority === 'High' ? 'selected' : '' ?>>High</option>
                <option value="Medium" <?= $filterPriority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="Low" <?= $filterPriority === 'Low' ? 'selected' : '' ?>>Low</option>
            </select>
        </div>

        <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 6px;">Category</label>
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
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 600; font-size: 14px;">Showing <?= count($tickets) ?> Tickets</span>
        <?php if (!empty($filterStatus) || !empty($filterPriority) || !empty($filterCategory) || !empty($filterSearch)): ?>
            <a href="tickets.php" style="font-size: 13px; color: #dc2626; text-decoration: none;">&times; Clear all filters</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width: 110px;">Ticket #</th>
                    <th>Subject &amp; Category</th>
                    <th style="width: 160px;">Submitted By</th>
                    <th style="width: 100px;">Priority</th>
                    <th style="width: 140px;">Status</th>
                    <th style="width: 150px;">Assigned</th>
                    <th style="width: 140px;">Updated</th>
                    <th style="width: 80px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;" class="text-muted">
                            No tickets matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
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
                                        <span style="margin-left: 6px;">💬 <?= (int)$t['reply_count'] ?></span>
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
                                    <span class="badge badge-<?= strtolower(str_replace('-', '', $t['status'])) ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                <?php else: ?>
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
                            <td style="font-size: 12px; color: #64748b;">
                                <?= date('M d, H:i', strtotime($t['updated_at'] ?? $t['created_at'])) ?>
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
