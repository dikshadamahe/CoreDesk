<?php
// =====================================================================
// ticket-view.php
// Zendesk Agent Workspace 3-Column Incident Console (Exact Match to Photo 5)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: index.php');
    exit;
}

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

if ($currentUser['role'] === 'customer' && $ticket['user_id'] != $currentUser['id']) {
    http_response_code(403);
    die("Access denied: You do not have permission to view this ticket.");
}

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

$logsStmt = $pdo->prepare("
    SELECT 
        tl.*,
        u.name AS actor_name
    FROM ticket_logs tl
    INNER JOIN users u ON tl.user_id = u.id
    WHERE tl.ticket_id = :ticket_id
    ORDER BY tl.created_at DESC
    LIMIT 8
");
$logsStmt->execute(['ticket_id' => $ticketId]);
$logs = $logsStmt->fetchAll();

$pageTitle = $ticket['ticket_code'] . ' - ' . $ticket['subject'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- 3-Column Zendesk Agent Workspace (Photo 5 Layout) -->
<div class="zd-agent-workspace">
    
    <!-- Column 1: Left Ticket Details & Properties Pane -->
    <div class="zd-left-pane">
        <div class="zd-panel-card">
            <span class="zd-field-label">Requester</span>
            <div class="zd-user-row">
                <div class="zd-avatar-circle" style="background: #2e74b5;">
                    <?= strtoupper(substr($ticket['customer_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 13px;"><?= e($ticket['customer_name']) ?></div>
                    <div style="font-size: 11px; color: var(--zd-text-muted);"><?= e($ticket['customer_email']) ?></div>
                </div>
            </div>
        </div>

        <div class="zd-panel-card">
            <span class="zd-field-label">Assignee</span>
            <div class="zd-user-row">
                <div class="zd-avatar-circle" style="background: #17494d;">
                    <?= strtoupper(substr($ticket['agent_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 13px;"><?= e($ticket['agent_name'] ?? 'Unassigned') ?></div>
                    <div style="font-size: 11px; color: var(--zd-text-muted);">Support Specialist</div>
                </div>
            </div>
        </div>

        <div class="zd-panel-card" style="display: flex; flex-direction: column; gap: 12px;">
            <div>
                <span class="zd-field-label">Status</span>
                <?php if ($currentUser['role'] === 'customer'): ?>
                    <?php
                        $badgeClass = match(strtolower($ticket['status'])) {
                            'open'        => 'zd-badge-open',
                            'in-progress' => 'zd-badge-progress',
                            'resolved'    => 'zd-badge-solved',
                            default       => 'zd-badge-closed'
                        };
                    ?>
                    <span class="zd-badge <?= $badgeClass ?> js-status-badge-<?= (int)$ticket['id'] ?>">
                        <?= strtoupper(e($ticket['status'])) ?>
                    </span>
                <?php else: ?>
                    <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$ticket['id'] ?>" style="font-weight: 600;">
                        <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="In-Progress" <?= $ticket['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                        <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>Solved</option>
                        <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <span class="zd-field-label">Priority</span>
                <span class="zd-badge <?= strtolower($ticket['priority']) === 'critical' ? 'zd-badge-open' : 'zd-badge-progress' ?>">
                    <?= e($ticket['priority']) ?>
                </span>
            </div>

            <div>
                <span class="zd-field-label">Category</span>
                <span style="font-size: 12px; font-weight: 500; color: var(--zd-text-main); background: #e9ebed; padding: 3px 8px; border-radius: 3px;">
                    <?= e($ticket['category_name']) ?>
                </span>
            </div>

            <div>
                <span class="zd-field-label">Tags</span>
                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                    <span style="font-size: 11px; color: #556877; background: #eef2f5; padding: 2px 6px; border-radius: 3px;">production</span>
                    <span style="font-size: 11px; color: #556877; background: #eef2f5; padding: 2px 6px; border-radius: 3px;">api</span>
                </div>
            </div>
        </div>

        <div style="font-size: 11.5px; color: var(--zd-text-muted); padding: 0 4px;">
            <div>Created: <?= date('M d, Y · H:i', strtotime($ticket['created_at'])) ?></div>
            <div>Updated: <?= date('M d, Y · H:i', strtotime($ticket['updated_at'] ?? $ticket['created_at'])) ?></div>
        </div>
    </div>

    <!-- Column 2: Center Main Conversation Stream (Photo 5) -->
    <div class="zd-center-pane">
        
        <!-- Header Strip -->
        <div class="zd-ticket-header-strip">
            <div style="font-size: 12px; color: var(--zd-text-muted); display: flex; align-items: center; gap: 8px;">
                <span><?= e($ticket['customer_name']) ?></span>
                <span>&bull;</span>
                <span style="font-family: 'JetBrains Mono', monospace; font-weight: 600;"><?= e($ticket['ticket_code']) ?></span>
                <span>&bull;</span>
                <span>via Web Portal</span>
            </div>
            <h1 class="zd-ticket-subject-title"><?= e($ticket['subject']) ?></h1>
        </div>

        <!-- Scrollable Conversation Feed -->
        <div id="ticket-replies-list" class="zd-conversation-scroll">
            
            <!-- Original Description Card -->
            <div class="zd-message-bubble">
                <div class="zd-message-head">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="zd-avatar-circle" style="width: 24px; height: 24px; font-size: 10px; background: #2e74b5;">
                            <?= strtoupper(substr($ticket['customer_name'], 0, 1)) ?>
                        </div>
                        <strong style="font-size: 13px;"><?= e($ticket['customer_name']) ?></strong>
                        <span style="font-size: 11px; color: var(--zd-text-muted);">via Web Portal</span>
                    </div>
                    <span style="font-size: 11.5px; color: var(--zd-text-muted);">
                        <?= date('M d, Y · H:i', strtotime($ticket['created_at'])) ?>
                    </span>
                </div>
                <div class="zd-message-body">
<?= e($ticket['description']) ?>
                </div>
            </div>

            <!-- Follow-up Replies -->
            <?php foreach ($replies as $reply): ?>
                <?php 
                    $isInternal = (int)$reply['is_internal_note'] === 1;
                    $initial = strtoupper(substr($reply['user_name'], 0, 1));
                    $avatarBg = $reply['user_role'] === 'customer' ? '#2e74b5' : '#17494d';
                ?>
                <div class="zd-message-bubble <?= $isInternal ? 'internal-note' : '' ?>">
                    <div class="zd-message-head">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="zd-avatar-circle" style="width: 24px; height: 24px; font-size: 10px; background: <?= $avatarBg ?>;">
                                <?= $initial ?>
                            </div>
                            <strong style="font-size: 13px;"><?= e($reply['user_name']) ?></strong>
                            <span class="zd-badge <?= $reply['user_role'] === 'customer' ? 'zd-badge-progress' : 'zd-badge-closed' ?>" style="font-size: 9px; padding: 1px 5px;">
                                <?= strtoupper(e($reply['user_role'])) ?>
                            </span>
                            <?php if ($isInternal): ?>
                                <span class="zd-badge zd-badge-new" style="font-size: 9px; padding: 1px 5px;">
                                    Internal Note
                                </span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size: 11.5px; color: var(--zd-text-muted);">
                            <?= date('M d, H:i', strtotime($reply['created_at'])) ?>
                        </span>
                    </div>
                    <div class="zd-message-body">
                        <?= e($reply['message']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Zendesk Bottom Composer / Reply Editor -->
        <div class="zd-composer-box">
            <?php if ($currentUser['role'] !== 'customer'): ?>
                <div class="zd-composer-tabs">
                    <button type="button" id="tab-public-reply" class="zd-composer-tab active" onclick="switchEditorMode('public')">
                        Public Reply
                    </button>
                    <button type="button" id="tab-internal-note" class="zd-composer-tab tab-internal" onclick="switchEditorMode('internal')">
                        Internal Note
                    </button>
                </div>
            <?php else: ?>
                <div style="padding: 10px 16px; background: #f8f9fa; border-bottom: 1px solid var(--zd-border); font-size: 12.5px; font-weight: 600;">
                    Reply to Support
                </div>
            <?php endif; ?>

            <form id="ticket-reply-form">
                <input type="hidden" id="ticket-id" value="<?= (int)$ticket['id'] ?>">
                <input type="checkbox" id="is-internal-note" value="1" style="display: none;">

                <textarea id="reply-message" rows="3" class="zd-composer-textarea" placeholder="Type your response, troubleshooting logs, or resolution notes..." required></textarea>

                <div class="zd-composer-footer">
                    <span style="font-size: 11.5px; color: var(--zd-text-muted);">
                        Prepared PDO statements active
                    </span>
                    <button type="submit" class="btn btn-primary" id="reply-submit-btn">
                        Submit Response
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 3: Right Customer Context & History Pane (Photo 5) -->
    <div class="zd-right-pane">
        
        <!-- Customer Profile Card -->
        <div class="zd-panel-card">
            <div class="zd-customer-hero">
                <div class="zd-customer-avatar-lg">
                    <?= strtoupper(substr($ticket['customer_name'], 0, 1)) ?>
                </div>
                <div style="font-size: 15px; font-weight: 700; color: var(--zd-text-main);"><?= e($ticket['customer_name']) ?></div>
                <div style="font-size: 12px; color: var(--zd-text-muted);"><?= e($ticket['customer_email']) ?></div>
            </div>

            <div style="border-top: 1px solid var(--zd-border-subtle); padding-top: 12px; font-size: 12.5px; display: flex; flex-direction: column; gap: 8px;">
                <div>
                    <span class="zd-field-label">Organization</span>
                    <strong>Client Enterprise</strong>
                </div>
                <div>
                    <span class="zd-field-label">User Access</span>
                    <span class="zd-badge zd-badge-progress">Active Client</span>
                </div>
            </div>
        </div>

        <!-- Recent Interaction History -->
        <div class="zd-panel-card">
            <span class="zd-field-label">Interaction History</span>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <?php if (empty($logs)): ?>
                    <div style="color: var(--zd-text-muted); font-size: 12px;">No activity logged yet.</div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="zd-history-item">
                            <div style="font-weight: 600; color: var(--zd-text-main);"><?= e($log['action']) ?></div>
                            <div style="color: var(--zd-text-muted); font-size: 11px;">
                                by <?= e($log['actor_name']) ?> &middot; <?= date('M d, H:i', strtotime($log['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
function switchEditorMode(mode) {
    const tabPublic = document.getElementById('tab-public-reply');
    const tabInternal = document.getElementById('tab-internal-note');
    const internalCheckbox = document.getElementById('is-internal-note');
    const textarea = document.getElementById('reply-message');
    const submitBtn = document.getElementById('reply-submit-btn');

    if (mode === 'internal') {
        tabPublic?.classList.remove('active');
        tabInternal?.classList.add('active');
        if (internalCheckbox) internalCheckbox.checked = true;
        textarea.style.backgroundColor = '#fffdf5';
        textarea.placeholder = 'Type internal note (visible only to support agents)...';
        if (submitBtn) {
            submitBtn.textContent = 'Submit Internal Note';
        }
    } else {
        tabInternal?.classList.remove('active');
        tabPublic?.classList.add('active');
        if (internalCheckbox) internalCheckbox.checked = false;
        textarea.style.backgroundColor = '#ffffff';
        textarea.placeholder = 'Type your response, troubleshooting logs, or resolution notes...';
        if (submitBtn) {
            submitBtn.textContent = 'Submit Response';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
