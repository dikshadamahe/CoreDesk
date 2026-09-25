<?php
// =====================================================================
// create-ticket.php
// Enterprise Incident Submission Portal (Jira ITIL Standard)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$error = null;
$categories = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        $error = 'Invalid security token (CSRF). Please resubmit.';
    } else {
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $priority    = trim($_POST['priority'] ?? 'Medium');

        if (empty($subject) || empty($description) || $categoryId <= 0) {
            $error = 'Please fill in all required fields (Category, Subject, and Description).';
        } else {
            $ticketCode = 'CD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_code, user_id, category_id, subject, description, priority, status, created_at, updated_at)
                    VALUES (:ticket_code, :user_id, :category_id, :subject, :description, :priority, 'Open', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    'ticket_code' => $ticketCode,
                    'user_id'     => $currentUser['id'],
                    'category_id' => $categoryId,
                    'subject'     => $subject,
                    'description' => $description,
                    'priority'    => $priority
                ]);
                $ticketId = (int)$pdo->lastInsertId();

                $logStmt = $pdo->prepare("
                    INSERT INTO ticket_logs (ticket_id, user_id, action, new_value)
                    VALUES (:ticket_id, :user_id, 'Incident Created', 'Open')
                ");
                $logStmt->execute([
                    'ticket_id' => $ticketId,
                    'user_id'   => $currentUser['id']
                ]);

                $pdo->commit();
                header("Location: ticket-view.php?id={$ticketId}&created=1");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to submit incident: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Raise New Incident';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 820px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <a href="tickets.php" class="btn btn-secondary btn-sm" style="margin-bottom: 12px;">
            &larr; Back to Queues
        </a>
        <h1 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">Raise New Support Incident</h1>
        <p style="color: var(--text-muted); font-size: 13.5px; margin: 0;">
            Provide reproduction steps, expected versus observed results, and technical environment parameters.
        </p>
    </div>

    <div class="card" style="box-shadow: var(--shadow-modal);">
        <div class="card-header" style="background: #FAFBFC; display: flex; justify-content: space-between; align-items: center;">
            <strong style="color: var(--text-heading); font-size: 14px;">Incident Details &amp; SLA Classification</strong>
            <span class="tag-category">ITIL Incident Management</span>
        </div>

        <div class="card-body" style="padding: 28px;">
            <?php if ($error): ?>
                <div class="toast-alert toast-error" style="position: static; margin-bottom: 20px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="create-ticket.php" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                            Category / Affected Service *
                        </label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select affected subsystem...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>">
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                            Severity / Impact Level *
                        </label>
                        <select name="priority" class="form-control" required>
                            <option value="Low">▼ Low — General inquiry, minimal business impact</option>
                            <option value="Medium" selected>〓 Medium — Standard operational bug / degradation</option>
                            <option value="High">▲ High — Production feature impaired</option>
                            <option value="Critical">▲▲ Critical — P0 Complete system outage / blocker</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                        Incident Summary *
                    </label>
                    <input type="text" name="subject" class="form-control" placeholder="E.g., HTTP 504 Gateway Timeout during batch transaction export" required>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                        Reproduction Steps &amp; Stack Traces *
                    </label>
                    <textarea name="description" rows="7" class="form-control" placeholder="1. Navigate to endpoint /api/v1/...
2. Pass authorization bearer token
3. Observe uncaught exception or latency spike
4. Attach stack trace / server logs here..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--jira-border-subtle); padding-top: 16px;">
                    <a href="tickets.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 9px 24px;">
                        Submit Incident Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
