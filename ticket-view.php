<?php
// =====================================================================
// ticket-view.php
// Interactive Ticket Conversation Thread, Audit Log & Status Control
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: tickets.php');
    exit;
}

// Fetch ticket record with user, category, and assigned agent details
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.name AS category_name,
        u.name AS customer_name,
        u.email AS customer_email,
        a.name AS agent_name,
        a.email AS agent_email
    FROM tickets t
    INNER JOIN categories c ON t.category_id = c.id
    INNER JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_agent_id = a.id
    WHERE t.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    die("Ticket not found.");
}

// Access control: Customers can only view their own tickets
if ($currentUser['role'] === 'customer' && $ticket['user_id'] != $currentUser['id']) {
    http_response_code(403);
    die("Access denied: You do not have permission to view this ticket.");
}

// Fetch Replies (hide internal notes from customers)
$repliesSql = "
    SELECT 
        tr.*,
        u.name AS user_name,
        u.role AS user_role,
        u.email AS user_email
    FROM ticket_replies tr
    INNER JOIN users u ON tr.user_id = u.id
    WHERE tr.ticket_id = :ticket_id
";
if ($currentUser['role'] === 'customer') {
    $repliesSql .= " AND tr.is_internal_note = 0";
}
$repliesSql .= " ORDER BY tr.created_at ASC";

$repliesStmt = $pdo->prepare($repliesSql);
$repliesStmt->execute(['ticket_id' => $ticketId]);
$replies = $repliesStmt->fetchAll();

// Fetch Audit Logs
$logsStmt = $pdo->prepare("
    SELECT 
        tl.*,
        u.name AS actor_name
    FROM ticket_logs tl
    INNER JOIN users u ON tl.user_id = u.id
    WHERE tl.ticket_id = :ticket_id
    ORDER BY tl.created_at DESC
    LIMIT 10
");
$logsStmt->execute(['ticket_id' => $ticketId]);
$logs = $logsStmt->fetchAll();

$pageTitle = $ticket['ticket_code'] . ' - ' . $ticket['subject'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="tickets.php" class="text-muted" style="text-decoration: none; font-size: 13px;">&larr; Back to Ticket Queue</a>
</div>

<!-- Ticket Title & Status Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <span style="font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 700; color: #2563eb;">
                <?= e($ticket['ticket_code']) ?>
            </span>
            <?php
                $pClass = match(strtolower($ticket['priority'])) {
                    'critical' => 'badge-danger',
                    'high' => 'badge-warning',
                    'medium' => 'badge-primary',
                    default => 'badge-neutral',
                };
            ?>
            <span class="badge <?= $pClass ?>"><?= e($ticket['priority']) ?> Priority</span>
            <span class="badge badge-<?= strtolower(str_replace('-', '', $ticket['status'])) ?> js-status-badge-<?= (int)$ticket['id'] ?>">
                <?= e($ticket['status']) ?>
            </span>
        </div>
        <h1 style="font-size: 24px; margin: 0;"><?= e($ticket['subject']) ?></h1>
    </div>

    <?php if ($currentUser['role'] !== 'customer'): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Update Status:</label>
            <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$ticket['id'] ?>" style="width: 140px; font-weight: 500;">
                <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In-Progress" <?= $ticket['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
    <?php endif; ?>
</div>

<!-- Main Grid Layout -->
<div style="display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start;">
    
    <!-- Left Column: Incident Description & Conversation Thread -->
    <div>
        <!-- Original Issue Description -->
        <div class="card" style="margin-bottom: 20px; border-left: 4px solid #2563eb;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <strong><?= e($ticket['customer_name']) ?></strong>
                    <span class="text-muted" style="font-size: 13px;">(Client / Author)</span>
                </div>
                <span class="text-muted" style="font-size: 12px;"><?= date('M d, Y · H:i', strtotime($ticket['created_at'])) ?></span>
            </div>
            <div class="card-body">
                <p style="white-space: pre-wrap; margin: 0; line-height: 1.6; font-size: 14.5px;"><?= e($ticket['description']) ?></p>
            </div>
        </div>

        <!-- Replies List Container -->
        <div id="ticket-replies-list">
            <?php foreach ($replies as $reply): ?>
                <?php $isInternal = (int)$reply['is_internal_note'] === 1; ?>
                <div class="card reply-card <?= $isInternal ? 'internal-note' : '' ?>" style="margin-bottom: 16px; <?= $isInternal ? 'background-color: #fefce8; border-color: #fde047;' : '' ?>">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; <?= $isInternal ? 'background-color: #fef9c3;' : '' ?>">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong><?= e($reply['user_name']) ?></strong>
                            <span class="badge badge-<?= $reply['user_role'] === 'admin' ? 'danger' : ($reply['user_role'] === 'agent' ? 'primary' : 'neutral') ?>" style="font-size: 11px;">
                                <?= ucfirst(e($reply['user_role'])) ?>
                            </span>
                            <?php if ($isInternal): ?>
                                <span class="badge badge-warning" style="font-size: 11px;">🔒 Internal Note (Staff Only)</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-muted" style="font-size: 12px;"><?= date('M d, H:i', strtotime($reply['created_at'])) ?></span>
                    </div>
                    <div class="card-body">
                        <p style="white-space: pre-wrap; margin: 0; line-height: 1.6; font-size: 14px;"><?= e($reply['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Add Reply Form (Vanilla JS AJAX powered) -->
        <div class="card" style="margin-top: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
            <div class="card-header">
                <h4 style="margin: 0; font-size: 15px;">Add Reply / Communication Note</h4>
            </div>
            <div class="card-body">
                <form id="ticket-reply-form">
                    <input type="hidden" id="ticket-id" value="<?= (int)$ticket['id'] ?>">

                    <div style="margin-bottom: 14px;">
                        <textarea id="reply-message" rows="4" class="form-control" placeholder="Type your response, troubleshooting steps, or resolution note..." required></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <?php if ($currentUser['role'] !== 'customer'): ?>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; color: #475569;">
                                    <input type="checkbox" id="is-internal-note" value="1">
                                    <span>Post as private internal note (visible to staff only)</span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">
                            Post Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Meta Information & Audit Trail -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h4 style="margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Ticket Information</h4>
            </div>
            <div class="card-body" style="font-size: 13.5px; display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <span class="text-muted" style="display: block; font-size: 12px;">Category</span>
                    <strong><?= e($ticket['category_name']) ?></strong>
                </div>
                <div>
                    <span class="text-muted" style="display: block; font-size: 12px;">Requester</span>
                    <strong><?= e($ticket['customer_name']) ?></strong>
                    <div style="font-size: 12px; color: #64748b;"><?= e($ticket['customer_email']) ?></div>
                </div>
                <div>
                    <span class="text-muted" style="display: block; font-size: 12px;">Assigned Support Engineer</span>
                    <strong><?= e($ticket['agent_name'] ?? 'Unassigned') ?></strong>
                    <?php if ($ticket['agent_email']): ?>
                        <div style="font-size: 12px; color: #64748b;"><?= e($ticket['agent_email']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-muted" style="display: block; font-size: 12px;">Created At</span>
                    <span><?= date('M d, Y · H:i:s', strtotime($ticket['created_at'])) ?></span>
                </div>
                <div>
                    <span class="text-muted" style="display: block; font-size: 12px;">Last Updated</span>
                    <span><?= date('M d, Y · H:i:s', strtotime($ticket['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Audit Trail / Incident Log -->
        <div class="card">
            <div class="card-header">
                <h4 style="margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Audit Activity Log</h4>
            </div>
            <div class="card-body" style="padding: 12px 16px;">
                <?php if (empty($logs)): ?>
                    <p class="text-muted" style="font-size: 12px; margin: 8px 0;">No audit events logged yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($logs as $log): ?>
                            <li style="font-size: 12.5px; border-left: 2px solid #cbd5e1; padding-left: 10px; margin-left: 4px;">
                                <div style="font-weight: 600; color: #334155;"><?= e($log['action']) ?></div>
                                <div style="color: #64748b; font-size: 11.5px;">
                                    by <strong><?= e($log['actor_name']) ?></strong>
                                    <?php if ($log['new_value']): ?>
                                        &rarr; <span class="badge badge-neutral" style="font-size: 10px;"><?= e($log['new_value']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #94a3b8; font-size: 11px; margin-top: 2px;">
                                    <?= date('M d, H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
