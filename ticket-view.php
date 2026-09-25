<?php
// =====================================================================
// ticket-view.php
// Dual-Pane Incident Workspace (Atlassian Jira & Chatwoot Hybrid)
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
    die("Incident not found.");
}

// Customer isolation
if ($currentUser['role'] === 'customer' && $ticket['user_id'] != $currentUser['id']) {
    http_response_code(403);
    die("Access denied: You do not have permission to view this incident.");
}

// Fetch conversation replies
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

// Fetch activity audit log
$logsStmt = $pdo->prepare("
    SELECT 
        tl.*,
        u.name AS actor_name
    FROM ticket_logs tl
    INNER JOIN users u ON tl.user_id = u.id
    WHERE tl.ticket_id = :ticket_id
    ORDER BY tl.created_at DESC
    LIMIT 12
");
$logsStmt->execute(['ticket_id' => $ticketId]);
$logs = $logsStmt->fetchAll();

$pageTitle = $ticket['ticket_code'] . ' - ' . $ticket['subject'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Top Action & Navigation Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="tickets.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">
            &larr; Back to Queue
        </a>
        <span style="color: var(--jira-border); font-size: 16px;">|</span>
        <span class="ticket-key" style="font-size: 14px;"><?= e($ticket['ticket_code']) ?></span>
        
        <?php
            $p = strtolower($ticket['priority']);
            $icon = match($p) {
                'critical' => '▲▲',
                'high'     => '▲',
                'medium'   => '〓',
                default    => '▼'
            };
        ?>
        <span class="priority-chip priority-<?= $p ?>">
            <span><?= $icon ?></span>
            <span><?= e($ticket['priority']) ?></span>
        </span>

        <?php
            $lozengeClass = match(strtolower($ticket['status'])) {
                'open'        => 'lozenge-open',
                'in-progress' => 'lozenge-inprogress',
                'resolved'    => 'lozenge-resolved',
                default       => 'lozenge-closed'
            };
        ?>
        <span class="lozenge <?= $lozengeClass ?> js-status-badge-<?= (int)$ticket['id'] ?>">
            <?= strtoupper(e($ticket['status'])) ?>
        </span>
    </div>

    <!-- Triage Quick Action -->
    <?php if ($currentUser['role'] !== 'customer'): ?>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">TRANSITION STATUS:</span>
            <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$ticket['id'] ?>" style="width: 140px; font-weight: 600;">
                <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>🔵 Open</option>
                <option value="In-Progress" <?= $ticket['status'] === 'In-Progress' ? 'selected' : '' ?>>🟡 In-Progress</option>
                <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>🟢 Resolved</option>
                <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>⚪ Closed</option>
            </select>
        </div>
    <?php endif; ?>
</div>

<!-- Jira & Chatwoot Dual-Pane Workspace -->
<div class="ticket-workspace-grid">
    
    <!-- Left / Center Conversation & Bug Report Column -->
    <div>
        <!-- Main Incident Header Card -->
        <div class="incident-header-card">
            <h1 class="incident-title-heading"><?= e($ticket['subject']) ?></h1>
            
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; font-size: 12.5px; color: var(--text-muted);">
                <div class="user-pill">
                    <div class="user-pill-avatar" style="background: #403294; width: 26px; height: 26px; font-size: 11px;">
                        <?= strtoupper(substr($ticket['customer_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <strong style="color: var(--text-heading);"><?= e($ticket['customer_name']) ?></strong>
                        <span>(<?= e($ticket['customer_email']) ?>)</span>
                    </div>
                </div>
                <span>&bull;</span>
                <span>Reported <?= date('M d, Y · H:i', strtotime($ticket['created_at'])) ?></span>
            </div>

            <div class="incident-description-box">
                <?= e($ticket['description']) ?>
            </div>
        </div>

        <!-- Conversation Timeline Feed -->
        <div id="ticket-replies-list" class="thread-timeline">
            <?php foreach ($replies as $reply): ?>
                <?php 
                    $isInternal = (int)$reply['is_internal_note'] === 1;
                    $initial = strtoupper(substr($reply['user_name'], 0, 1));
                    $avatarBg = $reply['user_role'] === 'admin' ? '#0747A6' : ($reply['user_role'] === 'agent' ? '#0052CC' : '#403294');
                ?>
                <div class="timeline-message-card <?= $isInternal ? 'internal-note' : '' ?>">
                    <div class="message-card-header">
                        <div class="message-author-box">
                            <div class="user-pill-avatar" style="background: <?= $avatarBg ?>; width: 26px; height: 26px; font-size: 11px;">
                                <?= $initial ?>
                            </div>
                            <div>
                                <strong style="font-size: 13px; color: var(--text-heading);"><?= e($reply['user_name']) ?></strong>
                                <span class="lozenge <?= $reply['user_role'] === 'admin' ? 'lozenge-closed' : ($reply['user_role'] === 'agent' ? 'lozenge-inprogress' : 'lozenge-open') ?>" style="font-size: 9px; padding: 1px 5px; margin-left: 4px;">
                                    <?= strtoupper(e($reply['user_role'])) ?>
                                </span>
                                <?php if ($isInternal): ?>
                                    <span class="lozenge" style="background: #FFE380; color: #614700; border: 1px solid #FFAB00; font-size: 9px; margin-left: 4px;">
                                        🔒 Private Staff Note
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span style="font-size: 11.5px; color: var(--text-muted);">
                            <?= date('M d, H:i', strtotime($reply['created_at'])) ?>
                        </span>
                    </div>
                    <div class="message-card-body">
                        <?= e($reply['message']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Chatwoot-Style Interactive Action Editor -->
        <div class="chatwoot-editor-card">
            <?php if ($currentUser['role'] !== 'customer'): ?>
                <div class="editor-mode-tabs">
                    <button type="button" id="tab-public-reply" class="editor-tab active" onclick="switchEditorMode('public')">
                        <span>💬</span>
                        <span>Public Reply to Customer</span>
                    </button>
                    <button type="button" id="tab-internal-note" class="editor-tab tab-note" onclick="switchEditorMode('internal')">
                        <span>🔒</span>
                        <span>Private Staff Note (Internal)</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="editor-mode-tabs">
                    <div class="editor-tab active" style="cursor: default;">
                        <span>💬</span>
                        <span>Reply to Support Engineer</span>
                    </div>
                </div>
            <?php endif; ?>

            <form id="ticket-reply-form">
                <input type="hidden" id="ticket-id" value="<?= (int)$ticket['id'] ?>">
                <input type="checkbox" id="is-internal-note" value="1" style="display: none;">

                <textarea id="reply-message" rows="4" class="editor-textarea" placeholder="Type your response, troubleshooting logs, or resolution summary..." required></textarea>

                <div class="editor-toolbar">
                    <div style="font-size: 11.5px; color: var(--text-muted);">
                        <span>Markdown supported</span> &middot; <span>Prepared statements active</span>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="submit" class="btn btn-primary" id="reply-submit-btn">
                            Send Response
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Inspector Column (Atlassian Jira Details) -->
    <div class="inspector-panel">
        
        <!-- Incident Information Card -->
        <div class="inspector-card">
            <div class="inspector-header">
                <span>Incident Attributes</span>
                <span style="font-size: 11px; color: var(--jira-blue); font-weight: 600;">Jira ITIL</span>
            </div>
            
            <div class="inspector-row">
                <span class="inspector-field-label">Issue Key</span>
                <span class="ticket-key"><?= e($ticket['ticket_code']) ?></span>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Category</span>
                <span class="tag-category"><?= e($ticket['category_name']) ?></span>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Priority / Impact</span>
                <span class="priority-chip priority-<?= strtolower($ticket['priority']) ?>">
                    <span><?= e($ticket['priority']) ?></span>
                </span>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Assigned Specialist</span>
                <span style="font-weight: 600; color: var(--text-heading);">
                    <?= e($ticket['agent_name'] ?? 'Unassigned') ?>
                </span>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Reporter</span>
                <div style="text-align: right;">
                    <div style="font-weight: 600;"><?= e($ticket['customer_name']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($ticket['customer_email']) ?></div>
                </div>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Created At</span>
                <span style="font-size: 12px; color: var(--text-muted);"><?= date('M d, Y · H:i:s', strtotime($ticket['created_at'])) ?></span>
            </div>

            <div class="inspector-row">
                <span class="inspector-field-label">Last Touch</span>
                <span style="font-size: 12px; color: var(--text-muted);"><?= date('M d, Y · H:i:s', strtotime($ticket['updated_at'] ?? $ticket['created_at'])) ?></span>
            </div>
        </div>

        <!-- SLA Compliance Target Card -->
        <div class="inspector-card">
            <div class="inspector-header">
                <span>SLA Metrics Target</span>
                <span style="color: #006644; font-weight: 700;">ACTIVE</span>
            </div>
            <div style="padding: 14px 18px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">First Response SLA</span>
                    <strong style="color: #006644;">Target Met (12m)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 10px;">
                    <span style="color: var(--text-muted);">Resolution Target</span>
                    <strong style="color: var(--jira-blue);">3.5 Hours Remaining</strong>
                </div>
                <div class="sla-progress-track" style="margin-top: 10px; background: #EBECF0; height: 6px;">
                    <div class="sla-progress-bar" style="width: 78%; background: var(--jira-blue);"></div>
                </div>
            </div>
        </div>

        <!-- Immutable Audit Trail Card -->
        <div class="inspector-card">
            <div class="inspector-header">
                <span>Incident Audit History</span>
                <span style="font-size: 11px; color: var(--text-muted);">Immutable</span>
            </div>
            <?php if (empty($logs)): ?>
                <div style="padding: 16px; color: var(--text-muted); font-size: 12px; text-align: center;">
                    No audit records logged yet.
                </div>
            <?php else: ?>
                <ul class="audit-list">
                    <?php foreach ($logs as $log): ?>
                        <li class="audit-entry">
                            <div class="audit-action-text"><?= e($log['action']) ?></div>
                            <div class="audit-actor-meta">
                                by <strong><?= e($log['actor_name']) ?></strong>
                                <?php if ($log['new_value']): ?>
                                    &rarr; <span class="lozenge lozenge-open" style="font-size: 9px; padding: 0 4px;"><?= e($log['new_value']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 10.5px; color: var(--text-subtlest); margin-top: 2px;">
                                <?= date('M d, H:i:s', strtotime($log['created_at'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
        textarea.style.backgroundColor = '#FFFBE6';
        textarea.placeholder = 'Type private internal note (visible only to support engineers & admins)...';
        if (submitBtn) {
            submitBtn.textContent = 'Post Private Note';
            submitBtn.className = 'btn btn-secondary';
            submitBtn.style.backgroundColor = '#FFF0B3';
            submitBtn.style.color = '#614700';
            submitBtn.style.borderColor = '#FFE380';
        }
    } else {
        tabInternal?.classList.remove('active');
        tabPublic?.classList.add('active');
        if (internalCheckbox) internalCheckbox.checked = false;
        textarea.style.backgroundColor = '#FFFFFF';
        textarea.placeholder = 'Type your response, troubleshooting logs, or resolution summary...';
        if (submitBtn) {
            submitBtn.textContent = 'Send Response';
            submitBtn.className = 'btn btn-primary';
            submitBtn.style.backgroundColor = '';
            submitBtn.style.color = '';
            submitBtn.style.borderColor = '';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
