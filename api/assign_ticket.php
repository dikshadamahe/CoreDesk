<?php
// =====================================================================
// api/assign_ticket.php
// Assigns or reassigns an incident ticket to a Support Agent or Admin
// =====================================================================

declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$currentUser = getLoggedInUser();

// Authorization: Only internal staff (admin, agent) can assign tickets
if ($currentUser['role'] === 'customer') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Permission denied. Only support executives and administrators can assign tickets.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$ticketId = (int)($data['ticket_id'] ?? 0);
$agentId  = isset($data['agent_id']) && $data['agent_id'] !== '' && (int)$data['agent_id'] > 0
    ? (int)$data['agent_id']
    : null;

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid ticket ID is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch current ticket & assignee
    $ticketStmt = $pdo->prepare("
        SELECT t.id, t.assigned_agent_id, u.name AS old_agent_name
        FROM tickets t
        LEFT JOIN users u ON t.assigned_agent_id = u.id
        WHERE t.id = :id
    ");
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!$ticket) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
        exit;
    }

    $oldAgentName = $ticket['old_agent_name'] ?? 'Unassigned';
    $newAgentName = 'Unassigned';

    // 2. Validate new agent if provided
    if ($agentId !== null) {
        $agentStmt = $pdo->prepare("
            SELECT id, name, role FROM users 
            WHERE id = :id AND role IN ('admin', 'agent')
            LIMIT 1
        ");
        $agentStmt->execute(['id' => $agentId]);
        $agent = $agentStmt->fetch();

        if (!$agent) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Selected user is not an active support specialist or administrator.']);
            exit;
        }
        $newAgentName = $agent['name'];
    }

    // 3. Update ticket assignment
    $updateStmt = $pdo->prepare("
        UPDATE tickets 
        SET assigned_agent_id = :agent_id,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $updateStmt->execute([
        'agent_id' => $agentId,
        'id'       => $ticketId
    ]);

    // 4. Record in audit trail if assignment actually changed
    if ($ticket['assigned_agent_id'] !== $agentId) {
        $logStmt = $pdo->prepare("
            INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value)
            VALUES (:ticket_id, :user_id, 'Specialist Assigned', :old_val, :new_val)
        ");
        $logStmt->execute([
            'ticket_id' => $ticketId,
            'user_id'   => $currentUser['id'],
            'old_val'   => $oldAgentName,
            'new_val'   => $newAgentName
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => "Ticket successfully assigned to {$newAgentName}.",
        'agent_id'   => $agentId,
        'agent_name' => $newAgentName
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
