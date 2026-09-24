<?php
// =====================================================================
// api/metrics.php
// Returns SLA and ticket counts via Hand-Written SQL Aggregations
// =====================================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$currentUser = getLoggedInUser();

try {
    $whereClause = "";
    $params = [];
    if ($currentUser['role'] === 'customer') {
        $whereClause = "WHERE user_id = :uid";
        $params['uid'] = $currentUser['id'];
    }

    // Single pass aggregation query with raw SQL CASE expressions
    $sql = "SELECT 
                COUNT(*) AS total_tickets,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_tickets,
                SUM(CASE WHEN status = 'In-Progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
                SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
                SUM(CASE WHEN priority = 'Critical' AND status != 'Resolved' AND status != 'Closed' THEN 1 ELSE 0 END) AS critical_unresolved
            FROM tickets
            {$whereClause}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $metrics = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total'               => (int)($metrics['total_tickets'] ?? 0),
            'open'                => (int)($metrics['open_tickets'] ?? 0),
            'in_progress'         => (int)($metrics['in_progress_tickets'] ?? 0),
            'resolved'            => (int)($metrics['resolved_tickets'] ?? 0),
            'critical_unresolved' => (int)($metrics['critical_unresolved'] ?? 0),
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
