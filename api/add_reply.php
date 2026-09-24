<?php
// =====================================================================
// api/add_reply.php
// Appends comment/reply to ticket thread via prepared SQL INSERT
// =====================================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$currentUser = getLoggedInUser();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$ticketId   = (int)($data['ticket_id'] ?? 0);
$message    = trim($data['message'] ?? '');
$isInternal = !empty($data['is_internal']) && hasRole('admin', 'agent') ? 1 : 0;

if ($ticketId <= 0 || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message content and valid ticket ID are required.']);
    exit;
}

try {
    // 1. Verify access permissions (Customer can only reply to own tickets)
    if ($currentUser['role'] === 'customer') {
        $checkStmt = $pdo->prepare("SELECT id FROM tickets WHERE id = :id AND user_id = :uid");
        $checkStmt->execute(['id' => $ticketId, 'uid' => $currentUser['id']]);
        if (!$checkStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized: You do not own this ticket.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    // 2. Insert new reply
    $insertSql = "INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal_note, created_at)
                  VALUES (:tid, :uid, :msg, :internal, CURRENT_TIMESTAMP)";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        'tid'      => $ticketId,
        'uid'      => $currentUser['id'],
        'msg'      => $message,
        'internal' => $isInternal
    ]);

    // 3. Update ticket timestamp and touch status if customer replied
    if ($currentUser['role'] === 'customer') {
        $touchStmt = $pdo->prepare("UPDATE tickets SET status = CASE WHEN status = 'Resolved' THEN 'In-Progress' ELSE status END WHERE id = :id");
        $touchStmt->execute(['id' => $ticketId]);
    }

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Reply posted successfully.',
        'user_name' => $currentUser['name'],
        'role'      => $currentUser['role'],
        'time'      => date('M d, Y h:i A')
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
