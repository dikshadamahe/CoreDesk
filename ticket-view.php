<?php
// =====================================================================
// ticket-view.php
// Dual-Pane Conversation Workspace (Clean SaaS Standard)
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

$staffAgents = [];
if ($currentUser['role'] !== 'customer') {
    $staffAgents = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin', 'agent') ORDER BY role ASC, name ASC")->fetchAll();
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
    LIMIT 10
");
$logsStmt->execute(['ticket_id' => $ticketId]);
$logs = $logsStmt->fetchAll();

$pageTitle = $ticket['ticket_code'] . ' - ' . $ticket['subject'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Top Action Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <a href="tickets.php" class="btn btn-secondary btn-sm">
            &larr; Back to Tickets
        </a>
        <span style="color: var(--border-subtle); font-size: 18px;">|</span>
        <span class="ticket-key" style="font-size: 15px;"><?= e($ticket['ticket_code']) ?></span>
        
        <span class="priority-pill priority-<?= strtolower($ticket['priority']) ?>">
            <?= e($ticket['priority']) ?>
        </span>

        <span class="status-pill status-<?= strtolower(str_replace('-', '', $ticket['status'])) ?> js-status-badge-<?= (int)$ticket['id'] ?>">
            <?= e($ticket['status']) ?>
        </span>
    </div>

    <?php if ($currentUser['role'] !== 'customer'): ?>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Change Status:</span>
            <select class="form-control form-control-sm js-status-select" data-ticket-id="<?= (int)$ticket['id'] ?>" style="width: 140px; font-weight: 600;">
                <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In-Progress" <?= $ticket['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
    <?php endif; ?>
</div>

<!-- Dual Pane Layout -->
<div class="workspace-grid">
    
    <!-- Left Conversation Column -->
    <div>
        <!-- Main Issue Description Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #ffffff;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="avatar-initial" style="background: var(--primary);">
                        <?= strtoupper(substr($ticket['customer_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <strong style="color: var(--text-heading); font-size: 14px;"><?= e($ticket['customer_name']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 12px;">(<?= e($ticket['customer_email']) ?>)</span>
                    </div>
                </div>
                <span style="color: var(--text-muted); font-size: 12.5px;">
                    <?= date('M d, Y · H:i', strtotime($ticket['created_at'])) ?>
                </span>
            </div>

            <div class="card-body">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 14px; color: var(--text-heading);">
                    <?= e($ticket['subject']) ?>
                </h2>
                <div style="background: #f8fafc; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 18px; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">
<?= e($ticket['description']) ?>
                </div>
            </div>
        </div>

        <!-- Thread Messages Stream -->
        <div id="ticket-replies-list" class="thread-stream">
            <?php foreach ($replies as $reply): ?>
                <?php 
                    $isInternal = (int)$reply['is_internal_note'] === 1;
                    $initial = strtoupper(substr($reply['user_name'], 0, 1));
                    $bg = $reply['user_role'] === 'admin' ? '#3244e8' : ($reply['user_role'] === 'agent' ? '#4457ff' : '#6366f1');
                ?>
                <div class="reply-card <?= $isInternal ? 'internal-note' : '' ?>">
                    <div class="reply-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="avatar-initial" style="background: <?= $bg ?>; width: 24px; height: 24px; font-size: 10px;">
                                <?= $initial ?>
                            </div>
                            <div>
                                <strong style="font-size: 13px; color: var(--text-heading);"><?= e($reply['user_name']) ?></strong>
                                <span class="status-pill status-open" style="font-size: 9.5px; padding: 1px 6px; margin-left: 4px;">
                                    <?= strtoupper(e($reply['user_role'])) ?>
                                </span>
                                <?php if ($isInternal): ?>
                                    <span class="status-pill status-inprogress" style="font-size: 9.5px; padding: 1px 6px; margin-left: 4px;">
                                        Private Staff Note
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span style="font-size: 12px; color: var(--text-muted);">
                            <?= date('M d, H:i', strtotime($reply['created_at'])) ?>
                        </span>
                    </div>
                    <div class="reply-body">
                        <?= e($reply['message']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Reply Editor Form -->
        <div class="card" style="box-shadow: var(--shadow-card); overflow: hidden;">
            <?php if ($currentUser['role'] !== 'customer'): ?>
                <div style="display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-subtle);">
                    <button type="button" id="tab-public-reply" class="btn btn-subtle" onclick="switchEditorMode('public')" style="border-radius: 0; padding: 12px 20px; font-weight: 600; color: var(--primary); border-bottom: 2px solid var(--primary); background: #ffffff;">
                        Public Reply
                    </button>
                    <button type="button" id="tab-internal-note" class="btn btn-subtle" onclick="switchEditorMode('internal')" style="border-radius: 0; padding: 12px 20px; font-weight: 600; color: var(--text-muted);">
                        Private Staff Note
                    </button>
                </div>
            <?php else: ?>
                <div style="padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-subtle); font-weight: 600; font-size: 13.5px;">
                    Reply to Support Team
                </div>
            <?php endif; ?>

            <form id="ticket-reply-form">
                <input type="hidden" id="ticket-id" value="<?= (int)$ticket['id'] ?>">
                <input type="checkbox" id="is-internal-note" value="1" style="display: none;">

                <div style="padding: 18px;">
                    <textarea id="reply-message" rows="4" class="form-control" placeholder="Type your response, troubleshooting logs, or resolution notes..." required style="resize: vertical;"></textarea>
                </div>

                <div style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 12px; color: var(--text-muted);">
                        Supports raw logs &amp; code blocks
                    </div>
                    <button type="submit" class="btn btn-primary" id="reply-submit-btn">
                        Send Response
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Sidebar Inspector Column -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Ticket Information</strong>
            </div>
            <div class="card-body" style="font-size: 13.5px; display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Category</span>
                    <span class="category-tag"><?= e($ticket['category_name']) ?></span>
                </div>

                <div>
                    <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">Assigned Specialist</span>
                    <?php if ($currentUser['role'] !== 'customer'): ?>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <select id="ticket-assignee-select" class="form-control form-control-sm" style="font-weight: 600;" onchange="assignSpecialist(<?= (int)$ticket['id'] ?>, this.value)">
                                <option value="" <?= empty($ticket['assigned_agent_id']) ? 'selected' : '' ?>>-- Unassigned --</option>
                                <?php foreach ($staffAgents as $sa): ?>
                                    <option value="<?= (int)$sa['id'] ?>" <?= ((int)($ticket['assigned_agent_id'] ?? 0) === (int)$sa['id']) ? 'selected' : '' ?>>
                                        <?= e($sa['name']) ?> (<?= ucfirst(e($sa['role'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ((int)($ticket['assigned_agent_id'] ?? 0) !== (int)$currentUser['id']): ?>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="assignSpecialist(<?= (int)$ticket['id'] ?>, <?= (int)$currentUser['id'] ?>)" style="align-self: flex-start; font-size: 11.5px; padding: 4px 10px;">
                                    Assign to me
                                </button>
                            <?php else: ?>
                                <span style="font-size: 11.5px; color: #047857; font-weight: 600;">Assigned to you</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <strong style="color: var(--text-heading);"><?= e($ticket['agent_name'] ?? 'Support Team Triage') ?></strong>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Assigned engineering specialist</div>
                    <?php endif; ?>
                </div>

                <div>
                    <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Client Requester</span>
                    <strong style="color: var(--text-heading);"><?= e($ticket['customer_name']) ?></strong>
                    <div style="font-size: 12px; color: var(--text-muted);"><?= e($ticket['customer_email']) ?></div>
                </div>

                <div>
                    <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Submitted Date</span>
                    <span style="color: var(--text-muted);"><?= date('M d, Y · H:i:s', strtotime($ticket['created_at'])) ?></span>
                </div>

                <div>
                    <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Last Modified</span>
                    <span style="color: var(--text-muted);"><?= date('M d, Y · H:i:s', strtotime($ticket['updated_at'] ?? $ticket['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Activity Audit Log -->
        <div class="card">
            <div class="card-header">
                <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Activity Audit Log</strong>
            </div>
            <div class="card-body" style="padding: 16px 20px;">
                <?php if (empty($logs)): ?>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">No audit events recorded yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ($logs as $log): ?>
                            <li style="border-left: 2px solid var(--primary); padding-left: 10px; font-size: 12.5px;">
                                <div style="font-weight: 600; color: var(--text-heading);"><?= e($log['action']) ?></div>
                                <div style="color: var(--text-muted); font-size: 11.5px;">
                                    by <strong><?= e($log['actor_name']) ?></strong>
                                    <?php if ($log['new_value']): ?>
                                        &rarr; <span class="status-pill status-open" style="font-size: 9px; padding: 0 4px;"><?= e($log['new_value']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: var(--text-subtlest); font-size: 11px; margin-top: 2px;">
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

<script>
function switchEditorMode(mode) {
    const tabPublic = document.getElementById('tab-public-reply');
    const tabInternal = document.getElementById('tab-internal-note');
    const internalCheckbox = document.getElementById('is-internal-note');
    const textarea = document.getElementById('reply-message');
    const submitBtn = document.getElementById('reply-submit-btn');

    if (mode === 'internal') {
        tabPublic.style.color = 'var(--text-muted)';
        tabPublic.style.borderBottom = 'none';
        tabPublic.style.background = 'transparent';

        tabInternal.style.color = '#b45309';
        tabInternal.style.borderBottom = '2px solid #b45309';
        tabInternal.style.background = '#fffdf5';

        if (internalCheckbox) internalCheckbox.checked = true;
        textarea.style.backgroundColor = '#fffdf5';
        textarea.placeholder = 'Type private internal note (visible to staff only)...';
        if (submitBtn) {
            submitBtn.textContent = 'Post Private Note';
            submitBtn.className = 'btn btn-secondary';
            submitBtn.style.color = '#b45309';
            submitBtn.style.borderColor = '#fde68a';
            submitBtn.style.background = '#fef3c7';
        }
    } else {
        tabInternal.style.color = 'var(--text-muted)';
        tabInternal.style.borderBottom = 'none';
        tabInternal.style.background = 'transparent';

        tabPublic.style.color = 'var(--primary)';
        tabPublic.style.borderBottom = '2px solid var(--primary)';
        tabPublic.style.background = '#ffffff';

        if (internalCheckbox) internalCheckbox.checked = false;
        textarea.style.backgroundColor = '#ffffff';
        textarea.placeholder = 'Type your response, troubleshooting logs, or resolution notes...';
        if (submitBtn) {
            submitBtn.textContent = 'Send Response';
            submitBtn.className = 'btn btn-primary';
            submitBtn.style.color = '#ffffff';
            submitBtn.style.borderColor = 'transparent';
            submitBtn.style.background = 'var(--primary)';
        }
    }
}

async function assignSpecialist(ticketId, agentId) {
    try {
        const res = await fetch('api/assign_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: parseInt(ticketId, 10),
                agent_id: agentId !== '' && agentId !== null ? parseInt(agentId, 10) : null
            })
        });
        const data = await res.json();
        if (res.ok && data.success) {
            showToast(data.message || 'Specialist updated');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.error || 'Failed to update assignment', 'error');
        }
    } catch (e) {
        console.error('Assignment error:', e);
        showToast('Network error while assigning ticket', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
