<?php
// =====================================================================
// config/database.php
// Pure Native PDO Database Connection (Zero ORM / Zero Framework)
// =====================================================================

declare(strict_types=1);

// Database connection configuration (configurable via environment or defaults)
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'coredesk_db';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbDriver = getenv('DB_DRIVER') ?: 'mysql';

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if ($dbDriver === 'sqlite' || (!extension_loaded('pdo_mysql') && extension_loaded('pdo_sqlite'))) {
        // SQLite fallback for standalone portable evaluation
        $sqlitePath = __DIR__ . '/../database/coredesk.sqlite';
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $pdoOptions);
    } else {
        // Primary Enterprise MySQL Connection with Prepared Statements
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
    }
} catch (PDOException $e) {
    // If running in development without active MySQL service, create portable fallback
    try {
        $sqlitePath = __DIR__ . '/../database/coredesk.sqlite';
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $pdoOptions);
        initializeSqliteFallback($pdo);
    } catch (Exception $sqliteErr) {
        http_response_code(500);
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Initializes SQLite schema if MySQL daemon is not running locally.
 */
function initializeSqliteFallback(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'customer',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
        );
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_code TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            description TEXT NOT NULL,
            priority TEXT NOT NULL DEFAULT 'Medium',
            status TEXT NOT NULL DEFAULT 'Open',
            assigned_agent_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME
        );
        CREATE TABLE IF NOT EXISTS ticket_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            is_internal_note INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS ticket_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            old_value TEXT,
            new_value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Populate seed data if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role) VALUES
            (1, 'Diksha Support Lead', 'admin@coredesk.local', '{$hash}', 'admin'),
            (2, 'Support Executive Alex', 'alex@coredesk.local', '{$hash}', 'agent'),
            (3, 'Support Executive Priya', 'priya@coredesk.local', '{$hash}', 'agent'),
            (4, 'Rahul Verma', 'rahul@client.com', '{$hash}', 'customer'),
            (5, 'Ananya Sen', 'ananya@fintech.io', '{$hash}', 'customer');

            INSERT INTO categories (id, name, description) VALUES
            (1, 'Technical Support', 'Software bugs and runtime errors'),
            (2, 'Database & Storage', 'Query latency and backups'),
            (3, 'API & Integrations', 'REST payload failures and webhooks'),
            (4, 'Billing & Accounts', 'Invoices and permissions');

            INSERT INTO tickets (id, ticket_code, user_id, category_id, subject, description, priority, status, assigned_agent_id) VALUES
            (1, 'CD-1001', 4, 1, '500 Internal Server Error on Payment Webhook Callback', 'Our webhook receiver triggers an uncaught PDOException during peak transactional bursts.', 'Critical', 'In-Progress', 2),
            (2, 'CD-1002', 5, 2, 'Slow MySQL Query Latency on Order History Dashboard', 'Selecting customer order histories takes over 3,200ms when filtered by date range.', 'High', 'Open', 1),
            (3, 'CD-1003', 4, 3, 'Bearer Token Expiration Issue in Customer REST API', 'JWT tokens appear to expire 15 minutes ahead of documented TTL header.', 'Medium', 'Resolved', 3),
            (4, 'CD-1004', 5, 1, 'SSL Handshake Timeout on Staging Subdomain', 'Staging server fails with SSL certificate verify error during external curl requests.', 'Medium', 'Open', NULL);

            INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal_note) VALUES
            (1, 2, 'Investigated Apache error logs. Preparing a prepared statement with retry logic.', 0),
            (1, 4, 'Thank you Alex! Please notify us once the patch is live on staging.', 0);
        ");
    }
}
