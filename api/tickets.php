<?php
// =====================================================================
// api/tickets.php
// Returns filtered ticket records via Hand-Written SQL Multi-Table JOINs
// =====================================================================

declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$currentUser = getLoggedInUser();

$status   = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$assigned = trim($_GET['assigned'] ?? '');
$search   = trim($_GET['search'] ?? '');

try {
    // Base SQL query hand-crafted with multi-table JOINs (zero ORM)
    $sql = "SELECT 
                t.id,
                t.ticket_code,
                t.subject,
                t.priority,
                t.status,
                t.created_at,
                t.updated_at,
                c.name AS category_name,
                u.name AS requester_name,
                u.email AS requester_email,
                agent.name AS assigned_agent_name,
                (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) AS reply_count
            FROM tickets t
            INNER JOIN users u ON t.user_id = u.id
            INNER JOIN categories c ON t.category_id = c.id
            LEFT JOIN users agent ON t.assigned_agent_id = agent.id
            WHERE 1=1";

    $params = [];

    // If customer role, only return tickets created by that customer
    if ($currentUser['role'] === 'customer') {
        $sql .= " AND t.user_id = :current_user_id";
        $params['current_user_id'] = $currentUser['id'];
    }

    if (!empty($status) && $status !== 'all') {
        $sql .= " AND t.status = :status";
        $params['status'] = $status;
    }

    if (!empty($priority) && $priority !== 'all') {
        $sql .= " AND t.priority = :priority";
        $params['priority'] = $priority;
    }

    if (!empty($assigned) && $currentUser['role'] !== 'customer') {
        if ($assigned === 'me') {
            $sql .= " AND t.assigned_agent_id = :assigned_me";
            $params['assigned_me'] = $currentUser['id'];
        } elseif ($assigned === 'unassigned') {
            $sql .= " AND (t.assigned_agent_id IS NULL OR t.assigned_agent_id = 0)";
        } elseif (is_numeric($assigned)) {
            $sql .= " AND t.assigned_agent_id = :assigned_agent";
            $params['assigned_agent'] = (int)$assigned;
        }
    }

    if (!empty($search)) {
        $sql .= " AND (t.subject LIKE :search_sub OR t.ticket_code LIKE :search_code OR u.name LIKE :search_req)";
        $params['search_sub'] = '%' . $search . '%';
        $params['search_code'] = '%' . $search . '%';
        $params['search_req'] = '%' . $search . '%';
    }

    // High priority ordering
    $sql .= " ORDER BY 
                CASE t.priority 
                    WHEN 'Critical' THEN 1 
                    WHEN 'High' THEN 2 
                    WHEN 'Medium' THEN 3 
                    ELSE 4 
                END ASC,
                t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($tickets),
        'data'    => $tickets
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database query error: ' . $e->getMessage()
    ]);
}
