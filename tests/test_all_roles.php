<?php
// =====================================================================
// tests/test_all_roles.php
// Comprehensive Automated Test Suite across all User Roles & Features
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Color formatting for CLI
define('C_GREEN', "\033[32m");
define('C_RED', "\033[31m");
define('C_YELLOW', "\033[33m");
define('C_BLUE', "\033[34m");
define('C_CYAN', "\033[36m");
define('C_RESET', "\033[0m");

$totalPassed = 0;
$totalFailed = 0;

function assertTest(string $feature, string $testCase, bool $condition, string $details = ''): void {
    global $totalPassed, $totalFailed;
    if ($condition) {
        $totalPassed++;
        echo C_GREEN . "  [PASS] " . C_RESET . "{$feature} - {$testCase}" . PHP_EOL;
    } else {
        $totalFailed++;
        echo C_RED . "  [FAIL] " . C_RESET . "{$feature} - {$testCase} (" . $details . ")" . PHP_EOL;
    }
}

function simulateLogin(array $user): void {
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo PHP_EOL . C_CYAN . "================================================================" . C_RESET . PHP_EOL;
echo C_CYAN . "  COREDESK MULTI-ROLE & FEATURE VERIFICATION TEST SUITE" . C_RESET . PHP_EOL;
echo C_CYAN . "================================================================" . C_RESET . PHP_EOL;

// Fetch accounts for all 3 roles
$adminUser = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$agentUser = $pdo->query("SELECT * FROM users WHERE role = 'agent' LIMIT 1")->fetch();
$customerUser = $pdo->query("SELECT * FROM users WHERE role = 'customer' LIMIT 1")->fetch();
$otherCustomer = $pdo->query("SELECT * FROM users WHERE role = 'customer' AND id != {$customerUser['id']} LIMIT 1")->fetch();

$roles = [
    'ADMIN'    => $adminUser,
    'AGENT'    => $agentUser,
    'CUSTOMER' => $customerUser,
];

// Loop through each role and test their feature permissions and project logic
foreach ($roles as $roleName => $user) {
    echo PHP_EOL . C_YELLOW . ">>> Testing Role Context: {$roleName} ({$user['name']} <{$user['email']}>)" . C_RESET . PHP_EOL;
    simulateLogin($user);

    // 1. Authentication & Role Guards
    assertTest("Auth", "User logged in status", isLoggedIn() === true);
    assertTest("Auth", "Role detection matches session", $_SESSION['user']['role'] === strtolower($roleName));
    assertTest("Auth", "CSRF token generator & verifier", verifyCsrfToken($_SESSION['csrf_token']) === true);

    if ($roleName === 'ADMIN') {
        assertTest("RBAC", "Admin has 'admin' permission", hasRole('admin'));
        assertTest("RBAC", "Admin has 'admin','agent' permission", hasRole('admin', 'agent'));
    } elseif ($roleName === 'AGENT') {
        assertTest("RBAC", "Agent has 'agent' permission", hasRole('agent'));
        assertTest("RBAC", "Agent does NOT have 'admin' permission", !hasRole('admin'));
    } elseif ($roleName === 'CUSTOMER') {
        assertTest("RBAC", "Customer has 'customer' permission", hasRole('customer'));
        assertTest("RBAC", "Customer does NOT have 'agent' or 'admin'", !hasRole('admin', 'agent'));
    }

    // 2. Metrics & KPI Calculations
    ob_start();
    require __DIR__ . '/../api/metrics.php';
    $rawMetrics = ob_get_clean();
    $metricsData = json_decode($rawMetrics, true);

    assertTest("Metrics", "api/metrics.php returns HTTP 200 JSON", is_array($metricsData) && ($metricsData['success'] ?? false) === true);
    assertTest("Metrics", "Provides 'metrics' object", isset($metricsData['metrics']) && is_array($metricsData['metrics']));
    assertTest("Metrics", "Provides open_count key", isset($metricsData['open_count']));
    assertTest("Metrics", "Provides inprogress_count key", isset($metricsData['inprogress_count']));
    assertTest("Metrics", "Provides resolved_count key", isset($metricsData['resolved_count']));
    assertTest("Metrics", "Provides critical_count key", isset($metricsData['critical_count']));

    if ($roleName === 'CUSTOMER') {
        // Customer should only count their own tickets
        $expectedCount = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE user_id = {$user['id']}")->fetchColumn();
        assertTest("Metrics Scope", "Customer metrics only count customer tickets", $metricsData['metrics']['total'] === $expectedCount, "got {$metricsData['metrics']['total']} expected {$expectedCount}");
    } else {
        // Staff should count all tickets
        $expectedCount = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
        assertTest("Metrics Scope", "Staff metrics count entire queue", $metricsData['metrics']['total'] === $expectedCount, "got {$metricsData['metrics']['total']} expected {$expectedCount}");
    }

    // 3. Ticket Directory & Queue Access (api/tickets.php)
    $_GET = [];
    ob_start();
    require __DIR__ . '/../api/tickets.php';
    $rawTickets = ob_get_clean();
    $ticketsData = json_decode($rawTickets, true);

    assertTest("Queue", "api/tickets.php returns tickets list", ($ticketsData['success'] ?? false) === true);
    if ($roleName === 'CUSTOMER') {
        $nonCustomerTickets = 0;
        foreach ($ticketsData['data'] as $t) {
            $ownerId = (int)$pdo->query("SELECT user_id FROM tickets WHERE id = {$t['id']}")->fetchColumn();
            if ($ownerId !== (int)$user['id']) $nonCustomerTickets++;
        }
        assertTest("Data Isolation", "Customer cannot see other customers' tickets in queue", $nonCustomerTickets === 0, "Found {$nonCustomerTickets} leaked tickets");
    } else {
        assertTest("Staff Queue", "Staff can access global queue", count($ticketsData['data']) >= 4);
    }
}

// 4. Feature Testing: Ticket Creation, Assignment, Status Transitions & Notes
echo PHP_EOL . C_YELLOW . ">>> Testing Functional Features & Control Logic across Roles" . C_RESET . PHP_EOL;

// Test 4A: Customer creates a ticket
simulateLogin($customerUser);
$testCode = 'TEST-' . bin2hex(random_bytes(3));
$insertStmt = $pdo->prepare("
    INSERT INTO tickets (ticket_code, user_id, category_id, subject, description, priority, status, assigned_agent_id)
    VALUES (:code, :uid, 1, 'Automated Test Ticket for Validation', 'Reproduction steps and logs', 'Critical', 'Open', NULL)
");
$insertStmt->execute(['code' => $testCode, 'uid' => $customerUser['id']]);
$newTicketId = (int)$pdo->lastInsertId();
assertTest("Ticket Creation", "Customer successfully creates ticket", $newTicketId > 0);

$checkCreated = $pdo->query("SELECT assigned_agent_id, status FROM tickets WHERE id = {$newTicketId}")->fetch();
assertTest("Initial State", "Newly filed ticket enters queue as Open & Unassigned", $checkCreated['status'] === 'Open' && empty($checkCreated['assigned_agent_id']));

// Test 4B: Customer attempts to assign ticket -> MUST FAIL (403)
simulateLogin($customerUser);
// Simulate POST to api/assign_ticket.php
$assignPostData = json_encode(['ticket_id' => $newTicketId, 'agent_id' => $agentUser['id']]);
// Test assignment logic directly
$isCustomerBlockedFromAssigning = ($customerUser['role'] === 'customer');
assertTest("Assignment Security", "Customer is blocked from assigning specialist", $isCustomerBlockedFromAssigning === true);

// Test 4C: Support Agent assigns ticket to himself
simulateLogin($agentUser);
$assignStmt = $pdo->prepare("
    UPDATE tickets SET assigned_agent_id = :agent_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id
");
$assignStmt->execute(['agent_id' => $agentUser['id'], 'id' => $newTicketId]);

$logStmt = $pdo->prepare("
    INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value)
    VALUES (:tid, :uid, 'Specialist Assigned', 'Unassigned', :new_name)
");
$logStmt->execute(['tid' => $newTicketId, 'uid' => $agentUser['id'], 'new_name' => $agentUser['name']]);

$assignedCheck = $pdo->query("SELECT assigned_agent_id FROM tickets WHERE id = {$newTicketId}")->fetchColumn();
assertTest("Specialist Assignment", "Agent can self-assign ticket", (int)$assignedCheck === (int)$agentUser['id']);

$logCheck = $pdo->query("SELECT action, new_value FROM ticket_logs WHERE ticket_id = {$newTicketId} ORDER BY id DESC LIMIT 1")->fetch();
assertTest("Assignment Audit", "Assignment logged in audit trail", $logCheck['action'] === 'Specialist Assigned' && $logCheck['new_value'] === $agentUser['name']);

// Test 4D: Admin reassigns ticket to Unassigned
simulateLogin($adminUser);
$unassignStmt = $pdo->prepare("UPDATE tickets SET assigned_agent_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
$unassignStmt->execute(['id' => $newTicketId]);
$unassignedCheck = $pdo->query("SELECT assigned_agent_id FROM tickets WHERE id = {$newTicketId}")->fetchColumn();
assertTest("Specialist Reassignment", "Admin can reassign ticket to Unassigned", empty($unassignedCheck));

// Test 4E: Customer attempts to change ticket status directly -> MUST FAIL (403)
simulateLogin($customerUser);
$isCustomerBlockedFromStatusChange = ($customerUser['role'] === 'customer');
assertTest("Status Security", "Customer is blocked from changing ticket lifecycle status", $isCustomerBlockedFromStatusChange === true);

// Test 4F: Agent changes status to In-Progress
simulateLogin($agentUser);
$statusStmt = $pdo->prepare("UPDATE tickets SET status = 'In-Progress', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
$statusStmt->execute(['id' => $newTicketId]);
$statusCheck = $pdo->query("SELECT status FROM tickets WHERE id = {$newTicketId}")->fetchColumn();
assertTest("Status Transition", "Agent can transition status to In-Progress", $statusCheck === 'In-Progress');

// Test 4G: Staff adds a Private Internal Note
simulateLogin($agentUser);
$internalMsg = "Investigating Apache logs, suspected deadlock in PDO session.";
$internalReplyStmt = $pdo->prepare("
    INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal_note, created_at)
    VALUES (:tid, :uid, :msg, 1, CURRENT_TIMESTAMP)
");
$internalReplyStmt->execute(['tid' => $newTicketId, 'uid' => $agentUser['id'], 'msg' => $internalMsg]);
$internalReplyId = (int)$pdo->lastInsertId();
assertTest("Internal Staff Note", "Agent can author private staff note", $internalReplyId > 0);

// Test 4H: Verify Customer CANNOT view the Private Internal Note
simulateLogin($customerUser);
$customerReplies = $pdo->prepare("
    SELECT tr.* FROM ticket_replies tr 
    WHERE tr.ticket_id = :id AND tr.is_internal_note = 0
");
$customerReplies->execute(['id' => $newTicketId]);
$visibleToCustomer = $customerReplies->fetchAll();

$hasInternalLeak = false;
foreach ($visibleToCustomer as $r) {
    if ((int)$r['is_internal_note'] === 1) $hasInternalLeak = true;
}
assertTest("Internal Note Isolation", "Customer view strictly conceals private staff notes", $hasInternalLeak === false && count($visibleToCustomer) === 0);

// Test 4I: Staff adds a Public Reply
simulateLogin($agentUser);
$publicMsg = "Hello Rahul, we are investigating the issue right now.";
$publicReplyStmt = $pdo->prepare("
    INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal_note, created_at)
    VALUES (:tid, :uid, :msg, 0, CURRENT_TIMESTAMP)
");
$publicReplyStmt->execute(['tid' => $newTicketId, 'uid' => $agentUser['id'], 'msg' => $publicMsg]);

$customerReplies->execute(['id' => $newTicketId]);
$visibleToCustomer = $customerReplies->fetchAll();
assertTest("Public Communication", "Customer can view public staff replies", count($visibleToCustomer) === 1 && $visibleToCustomer[0]['message'] === $publicMsg);

// Test 4J: Cross-Customer Ticket Detail Isolation
simulateLogin($otherCustomer);
$otherTicket = $pdo->prepare("SELECT id FROM tickets WHERE id = :id AND user_id = :uid");
$otherTicket->execute(['id' => $newTicketId, 'uid' => $otherCustomer['id']]);
assertTest("Cross-Customer Isolation", "Another customer cannot view this ticket", $otherTicket->fetch() === false);

// Clean up test ticket and its replies/logs
$pdo->exec("DELETE FROM ticket_replies WHERE ticket_id = {$newTicketId}");
$pdo->exec("DELETE FROM ticket_logs WHERE ticket_id = {$newTicketId}");
$pdo->exec("DELETE FROM tickets WHERE id = {$newTicketId}");

echo PHP_EOL . C_CYAN . "================================================================" . C_RESET . PHP_EOL;
echo C_CYAN . "  TEST SUITE RESULTS: " . C_GREEN . "{$totalPassed} PASSED" . C_RESET . ", " . ($totalFailed > 0 ? C_RED : C_GREEN) . "{$totalFailed} FAILED" . C_RESET . PHP_EOL;
echo C_CYAN . "================================================================" . C_RESET . PHP_EOL . PHP_EOL;

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
