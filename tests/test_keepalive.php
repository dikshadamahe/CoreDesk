<?php
// =====================================================================
// tests/test_keepalive.php
// Verification tests for Keepalive & Health Check API
// =====================================================================

declare(strict_types=1);

define('C_GREEN', "\033[32m");
define('C_RED', "\033[31m");
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

echo PHP_EOL . C_CYAN . "================================================================" . C_RESET . PHP_EOL;
echo C_CYAN . "  COREDESK KEEPALIVE & HEALTH CHECK API TEST SUITE" . C_RESET . PHP_EOL;
echo C_CYAN . "================================================================" . C_RESET . PHP_EOL;

// 1. Test standard GET execution of api/keepalive.php
$cmd = 'php -d display_errors=1 ' . escapeshellarg(__DIR__ . '/../api/keepalive.php');
$output = shell_exec($cmd);
$data = json_decode($output, true);

assertTest("Keepalive", "Valid JSON payload returned", is_array($data));
assertTest("Keepalive", "Status reports 'ok'", ($data['status'] ?? '') === 'ok');
assertTest("Keepalive", "Service name identified as CoreDesk", strpos($data['service'] ?? '', 'CoreDesk') !== false);
assertTest("Keepalive", "Database status reports 'connected'", ($data['database']['status'] ?? '') === 'connected');
assertTest("Keepalive", "Database latency metric included", isset($data['database']['latency_ms']) && is_numeric($data['database']['latency_ms']));
assertTest("Keepalive", "Total latency metric included", isset($data['latency_ms']) && is_numeric($data['latency_ms']));
assertTest("Keepalive", "System memory usage included", isset($data['system']['memory_mb']) && is_numeric($data['system']['memory_mb']));
assertTest("Keepalive", "PHP version detected", !empty($data['system']['php_version']));
assertTest("Keepalive", "Server timestamp present (ISO 8601)", !empty($data['timestamp']));

// 2. Test api/health.php alias
$cmdHealth = 'php -d display_errors=1 ' . escapeshellarg(__DIR__ . '/../api/health.php');
$healthOutput = shell_exec($cmdHealth);
$healthData = json_decode($healthOutput, true);

assertTest("Health Alias", "Valid JSON returned from api/health.php", is_array($healthData));
assertTest("Health Alias", "Health status is 'ok'", ($healthData['status'] ?? '') === 'ok');
assertTest("Health Alias", "Health DB connection verified", ($healthData['database']['status'] ?? '') === 'connected');

// 3. Test skip_db query parameter
$cmdSkip = 'QUERY_STRING="skip_db=1" php -d display_errors=1 -r \'parse_str(getenv("QUERY_STRING"), $_GET); include "' . __DIR__ . '/../api/keepalive.php";\'';
$skipOutput = shell_exec($cmdSkip);
$skipData = json_decode($skipOutput, true);

assertTest("Lightweight Ping", "skip_db=1 bypasses database query", ($skipData['database']['status'] ?? '') === 'skipped');
assertTest("Lightweight Ping", "Service status remains 'ok' when DB check skipped", ($skipData['status'] ?? '') === 'ok');

// 4. Test Optional Authentication Guard
$cmdAuthFail = 'KEEPALIVE_SECRET="secret123" KEEPALIVE_REQUIRE_AUTH="true" php -d display_errors=1 ' . escapeshellarg(__DIR__ . '/../api/keepalive.php');
$authFailOutput = shell_exec($cmdAuthFail);
$authFailData = json_decode($authFailOutput, true);

assertTest("Auth Guard", "Rejects unauthenticated ping when auth is enforced", ($authFailData['status'] ?? '') === 'unauthorized');

$cmdAuthPass = 'KEEPALIVE_SECRET="secret123" KEEPALIVE_REQUIRE_AUTH="true" php -d display_errors=1 -r \'$_GET["key"] = "secret123"; include "' . __DIR__ . '/../api/keepalive.php";\'';
$authPassOutput = shell_exec($cmdAuthPass);
$authPassData = json_decode($authPassOutput, true);

assertTest("Auth Guard", "Accepts authenticated ping with valid secret key", ($authPassData['status'] ?? '') === 'ok');

echo PHP_EOL . C_CYAN . "================================================================" . C_RESET . PHP_EOL;
echo C_CYAN . "  TEST SUITE RESULTS: " . C_GREEN . "{$totalPassed} PASSED" . C_RESET . ", " . ($totalFailed > 0 ? C_RED : C_GREEN) . "{$totalFailed} FAILED" . C_RESET . PHP_EOL;
echo C_CYAN . "================================================================" . C_RESET . PHP_EOL . PHP_EOL;

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
