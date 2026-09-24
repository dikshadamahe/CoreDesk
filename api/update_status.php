<?php
// =====================================================================
// api/update_status.php
// Mutates ticket status asynchronously with Transaction & Audit Logging
// =====================================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$currentUser = getLoggedInUser();

if ($currentUser['role'] === 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. Only support agents and admins can triage tickets.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$ticketId  = (int)($data['ticket_id'] ?? 0);
$newStatus = trim($data['new_status'] ?? '');
$agentId   = isset($data['assigned_agent_id']) ? (int)$data['assigned_agent_id'] : null;

$validStatuses = ['Open', 'In-Progress', 'Resolved', 'Closed'];
if ($ticketId <= 0 || !in_array($newStatus, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ticket ID or status payload provided.']);
    exit;
}

try {
    // Begin ACID transaction
    $pdo->beginTransaction();

    // 1. Fetch current status for audit log comparison
    $fetchStmt = $pdo->prepare("SELECT status, assigned_agent_id FROM tickets WHERE id = :id");
    $fetchStmt->execute(['id' => $ticketId]);
    $currentTicket = $fetchStmt->fetch();

    if (!$currentTicket) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
        exit;
    }

    $oldStatus = $currentTicket['status'];

    // 2. Perform raw SQL UPDATE with prepared statements
    $updateSql = "UPDATE tickets 
                  SET status = :status,
                      resolved_at = CASE WHEN :status = 'Resolved' THEN CURRENT_TIMESTAMP ELSE resolved_at END,
                      assigned_agent_id = COALESCE(:agent_id, assigned_agent_id)
                  WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        'status'   => $newStatus,
        'agent_id' => $agentId,
        'id'       => $ticketId
    ]);

    // 3. Write audit log entry
    if ($oldStatus !== $newStatus) {
        $logSql = "INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value)
                   VALUES (:ticket_id, :user_id, 'Status Transition', :old_val, :new_val)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            'ticket_id' => $ticketId,
            'user_id'   => $currentUser['id'],
            'old_val'   => $oldStatus,
            'new_val'   => $newStatus
        ]);
    }

    // Commit ACID transaction
    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => "Ticket #{$ticketId} status successfully transitioned to {$newStatus}.",
        'new_status' => $newStatus
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database execution failure: ' . $e->getMessage()
    ]);
}
