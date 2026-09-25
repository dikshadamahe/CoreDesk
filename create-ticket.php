<?php
// =====================================================================
// create-ticket.php
// Clean SaaS Ticket Creation Form
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$error = null;
$categories = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC")->fetchAll();
$staffAgents = [];
if ($currentUser['role'] !== 'customer') {
    $staffAgents = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin', 'agent') ORDER BY role ASC, name ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        $error = 'Invalid security token (CSRF). Please resubmit.';
    } else {
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $priority    = trim($_POST['priority'] ?? 'Medium');

        $assignedAgentId = null;
        if ($currentUser['role'] !== 'customer' && !empty($_POST['assigned_agent_id'])) {
            $assignedAgentId = (int)$_POST['assigned_agent_id'] > 0 ? (int)$_POST['assigned_agent_id'] : null;
        }

        if (empty($subject) || empty($description) || $categoryId <= 0) {
            $error = 'Please fill in all required fields (Category, Subject, and Description).';
        } else {
            $ticketCode = 'CD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_code, user_id, category_id, subject, description, priority, status, assigned_agent_id, created_at, updated_at)
                    VALUES (:ticket_code, :user_id, :category_id, :subject, :description, :priority, 'Open', :assigned_agent_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    'ticket_code'       => $ticketCode,
                    'user_id'           => $currentUser['id'],
                    'category_id'       => $categoryId,
                    'subject'           => $subject,
                    'description'       => $description,
                    'priority'          => $priority,
                    'assigned_agent_id' => $assignedAgentId
                ]);
                $ticketId = (int)$pdo->lastInsertId();

                $logStmt = $pdo->prepare("
                    INSERT INTO ticket_logs (ticket_id, user_id, action, new_value)
                    VALUES (:ticket_id, :user_id, 'Ticket Created', 'Open')
                ");
                $logStmt->execute([
                    'ticket_id' => $ticketId,
                    'user_id'   => $currentUser['id']
                ]);

                if ($assignedAgentId) {
                    $agentStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                    $agentStmt->execute(['id' => $assignedAgentId]);
                    $agentName = $agentStmt->fetchColumn() ?: 'Specialist';

                    $assignLogStmt = $pdo->prepare("
                        INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value)
                        VALUES (:ticket_id, :user_id, 'Specialist Assigned', 'Unassigned', :new_val)
                    ");
                    $assignLogStmt->execute([
                        'ticket_id' => $ticketId,
                        'user_id'   => $currentUser['id'],
                        'new_val'   => $agentName
                    ]);
                }

                $pdo->commit();
                header("Location: ticket-view.php?id={$ticketId}&created=1");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to submit ticket: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Submit New Ticket';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <a href="tickets.php" class="btn btn-secondary btn-sm" style="margin-bottom: 12px;">
            &larr; Back to Tickets
        </a>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 6px;">Submit a Support Ticket</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin: 0;">
            Provide reproduction steps, expected behavior, and error logs for the engineering team.
        </p>
    </div>

    <div class="card">
        <div class="card-header">
            <strong style="color: var(--text-heading); font-size: 15px;">New Request Details</strong>
            <span class="category-tag">Prepared Statement Input</span>
        </div>

        <div class="card-body">
            <?php if ($error): ?>
                <div class="toast-alert toast-error" style="position: static; margin-bottom: 20px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="create-ticket.php" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 6px;">
                            Category *
                        </label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select issue category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>">
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 6px;">
                            Severity / Priority *
                        </label>
                        <select name="priority" class="form-control" required>
                            <option value="Low">Low — General inquiry</option>
                            <option value="Medium" selected>Medium — Standard operational defect</option>
                            <option value="High">High — Production feature impaired</option>
                            <option value="Critical">Critical — P0 Outage / Blocker</option>
                        </select>
                    </div>
                </div>

                <?php if ($currentUser['role'] !== 'customer'): ?>
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 6px;">
                            Assign Specialist (Optional)
                        </label>
                        <select name="assigned_agent_id" class="form-control">
                            <option value="">-- Leave Unassigned (Triage Queue) --</option>
                            <?php foreach ($staffAgents as $sa): ?>
                                <option value="<?= (int)$sa['id'] ?>" <?= ((int)$currentUser['id'] === (int)$sa['id']) ? 'selected' : '' ?>>
                                    <?= e($sa['name']) ?> (<?= ucfirst(e($sa['role'])) ?>) <?= ((int)$currentUser['id'] === (int)$sa['id']) ? '— Assign to Myself' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Select which support executive or administrator will own this incident.
                        </div>
                    </div>
                <?php else: ?>
                    <div style="font-size: 12.5px; color: var(--text-muted); background: #f8fafc; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 10px 14px;">
                        <strong>Ticket Routing:</strong> Upon submission, your request will enter our engineering queue and be assigned to a designated support specialist for triage.
                    </div>
                <?php endif; ?>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 6px;">
                        Subject *
                    </label>
                    <input type="text" name="subject" class="form-control" placeholder="E.g., 500 Internal Server Error on Payment Webhook Callback" required>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 6px;">
                        Description &amp; Reproduction Steps *
                    </label>
                    <textarea name="description" rows="6" class="form-control" placeholder="Describe the problem, stack traces, expected outcome, and exact URL/endpoint..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-subtle); padding-top: 18px;">
                    <a href="tickets.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
