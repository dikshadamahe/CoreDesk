<?php
// =====================================================================
// api/metrics.php
// Returns SLA and ticket counts via Hand-Written SQL Aggregations
// =====================================================================

declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

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

    $total = (int)($metrics['total_tickets'] ?? 0);
    $open = (int)($metrics['open_tickets'] ?? 0);
    $inProgress = (int)($metrics['in_progress_tickets'] ?? 0);
    $resolved = (int)($metrics['resolved_tickets'] ?? 0);
    $critical = (int)($metrics['critical_unresolved'] ?? 0);

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total'               => $total,
            'open'                => $open,
            'in_progress'         => $inProgress,
            'resolved'            => $resolved,
            'critical_unresolved' => $critical,
            'open_count'          => $open,
            'inprogress_count'    => $inProgress,
            'resolved_count'      => $resolved,
            'critical_count'      => $critical,
        ],
        'total'               => $total,
        'open'                => $open,
        'in_progress'         => $inProgress,
        'resolved'            => $resolved,
        'critical'            => $critical,
        'critical_unresolved' => $critical,
        'open_count'          => $open,
        'inprogress_count'    => $inProgress,
        'resolved_count'      => $resolved,
        'critical_count'      => $critical,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
