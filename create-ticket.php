<?php
// =====================================================================
// create-ticket.php
// New Support Incident Submission with Input Sanitization & Audit Entry
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$currentUser = getLoggedInUser();

$error = null;
$success = null;

// Fetch active categories
$categories = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        $error = 'Invalid security token (CSRF). Please resubmit.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Medium');

        if (empty($subject) || empty($description) || $categoryId <= 0) {
            $error = 'Please fill in all required fields (Category, Subject, and Description).';
        } else {
            // Generate clean human-readable ticket code
            $ticketCode = 'CD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            try {
                $pdo->beginTransaction();

                // 1. Insert ticket record
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_code, user_id, category_id, subject, description, priority, status, created_at, updated_at)
                    VALUES (:ticket_code, :user_id, :category_id, :subject, :description, :priority, 'Open', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    'ticket_code' => $ticketCode,
                    'user_id' => $currentUser['id'],
                    'category_id' => $categoryId,
                    'subject' => $subject,
                    'description' => $description,
                    'priority' => $priority
                ]);
                $ticketId = (int)$pdo->lastInsertId();

                // 2. Insert audit log
                $logStmt = $pdo->prepare("
                    INSERT INTO ticket_logs (ticket_id, user_id, action, new_value)
                    VALUES (:ticket_id, :user_id, 'Ticket Created', 'Open')
                ");
                $logStmt->execute([
                    'ticket_id' => $ticketId,
                    'user_id' => $currentUser['id']
                ]);

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

<div style="max-width: 760px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <a href="tickets.php" class="text-muted" style="text-decoration: none; font-size: 13px;">&larr; Back to Ticket Queue</a>
        <h1 style="font-size: 26px; margin: 8px 0 4px;">Submit Support Request</h1>
        <p class="text-muted" style="margin: 0; font-size: 14px;">Describe the system issue, outage, or technical inquiry with repro steps.</p>
    </div>

    <div class="card" style="box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
        <div class="card-body" style="padding: 28px;">
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="create-ticket.php" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label for="category_id" style="display: block; font-weight: 500; font-size: 14px; margin-bottom: 6px;">Category *</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Select issue category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="priority" style="display: block; font-weight: 500; font-size: 14px; margin-bottom: 6px;">Severity / Priority *</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="Low">Low — General inquiry</option>
                            <option value="Medium" selected>Medium — Standard operational defect</option>
                            <option value="High">High — Service degradation or blocking bug</option>
                            <option value="Critical">Critical — P0 Production outage or severe data loss</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="subject" style="display: block; font-weight: 500; font-size: 14px; margin-bottom: 6px;">Subject *</label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="E.g., HTTP 504 Gateway Timeout when executing report export" required>
                </div>

                <div>
                    <label for="description" style="display: block; font-weight: 500; font-size: 14px; margin-bottom: 6px;">Detailed Description &amp; Trace Logs *</label>
                    <textarea id="description" name="description" rows="6" class="form-control" placeholder="Provide step-by-step reproduction steps, stack traces, expected vs actual results..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px;">
                    <a href="tickets.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
