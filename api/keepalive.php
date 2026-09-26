<?php
// =====================================================================
// api/keepalive.php
// Keepalive & System Health Check Endpoint for CoreDesk
// Prevents cloud instances (e.g. Render Free Tier) from sleeping
// Provides real-time service health, latency, and database diagnostics
// =====================================================================

declare(strict_types=1);

$requestStartTime = microtime(true);

// 1. Anti-caching and CORS headers
// Critical for keepalive: prevents proxies/CDNs from caching responses
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-KeepAlive-Key');
    header('X-Robots-Tag: noindex, nofollow');
}

// 2. Handle HTTP OPTIONS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 3. Handle HTTP HEAD request (some ping monitors only issue HEAD)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
    http_response_code(200);
    exit;
}

// 4. Optional Security / Token Check
// If KEEPALIVE_REQUIRE_AUTH is set to 'true', verify authorization
$secretKey = getenv('KEEPALIVE_SECRET') ?: getenv('CRON_SECRET');
if (!empty($secretKey) && getenv('KEEPALIVE_REQUIRE_AUTH') === 'true') {
    $providedKey = $_GET['token'] 
        ?? $_GET['key'] 
        ?? ($_SERVER['HTTP_X_KEEPALIVE_KEY'] ?? '');

    if (empty($providedKey) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $providedKey = $matches[1];
        }
    }

    if (!hash_equals($secretKey, (string)$providedKey)) {
        http_response_code(401);
        echo json_encode([
            'status'    => 'unauthorized',
            'error'     => 'Invalid or missing keepalive authorization token',
            'timestamp' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// 5. Database Diagnostic & Keepalive Query
$skipDb = isset($_GET['skip_db']) && in_array($_GET['skip_db'], ['1', 'true', 'yes'], true);
$dbStatus = 'skipped';
$dbLatencyMs = null;
$dbDriver = getenv('DB_DRIVER') ?: 'sqlite';
$dbError = null;

if (!$skipDb) {
    try {
        $dbStart = microtime(true);
        require_once __DIR__ . '/../config/database.php';

        if (isset($pdo) && $pdo instanceof PDO) {
            // Lightweight ping query: tests connection and keeps connection pool / SQLite / MySQL active
            $stmt = $pdo->query('SELECT 1');
            if ($stmt !== false) {
                $stmt->fetchColumn();
                $dbStatus = 'connected';
                $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
            } else {
                $dbStatus = 'unresponsive';
            }
        } else {
            $dbStatus = 'uninitialized';
        }
    } catch (Throwable $e) {
        $dbStatus = 'error';
        $dbError = $e->getMessage();
    }
}

// 6. Overall Service Status
$isHealthy = ($skipDb || $dbStatus === 'connected');
$status = $isHealthy ? 'ok' : 'degraded';
$httpStatus = $isHealthy ? 200 : 503;

http_response_code($httpStatus);

$totalLatencyMs = round((microtime(true) - $requestStartTime) * 1000, 2);

// 7. Response Payload
$response = [
    'status'     => $status,
    'service'    => 'CoreDesk Support Desk',
    'timestamp'  => gmdate('c'),
    'epoch'      => time(),
    'latency_ms' => $totalLatencyMs,
    'database'   => [
        'status'     => $dbStatus,
        'driver'     => $dbDriver,
        'latency_ms' => $dbLatencyMs,
    ],
    'system'     => [
        'php_version'    => PHP_VERSION,
        'memory_mb'      => round(memory_get_usage(true) / (1024 * 1024), 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
        'server_time'    => date('Y-m-d H:i:s T'),
    ],
    'message'    => 'Instance is warm and operational',
];

if ($dbError !== null) {
    $response['database']['error'] = $dbError;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
