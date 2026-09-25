<?php
// =====================================================================
// switch-role.php
// Instant Persona / Role Switcher for Testing & Evaluation
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

$role = trim($_GET['role'] ?? $_POST['role'] ?? '');
$roleMap = [
    'admin'    => 'admin@coredesk.local',
    'agent'    => 'alex@coredesk.local',
    'support'  => 'alex@coredesk.local',
    'customer' => 'rahul@client.com',
    'client'   => 'rahul@client.com',
];

if (isset($roleMap[$role])) {
    $email = $roleMap[$role];
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
    }
}

$redirect = 'index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);
    $path = basename($parsed['path'] ?? '');
    if (!empty($path) && $path !== 'login.php' && $path !== 'logout.php') {
        $redirect = $path . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
    }
}

header("Location: " . $redirect);
exit;
