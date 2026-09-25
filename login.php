<?php
// =====================================================================
// login.php
// Clean SaaS Authentication Portal (Dot Grid Background & Royal Blue)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Only redirect if GET request and already logged in
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $quickRole = $_POST['quick_login_role'] ?? null;

    if ($quickRole) {
        $roleMap = [
            'admin'    => 'admin@coredesk.local',
            'agent'    => 'alex@coredesk.local',
            'customer' => 'rahul@client.com',
        ];
        if (isset($roleMap[$quickRole])) {
            $email = $roleMap[$quickRole];
            $password = 'password123';
        }
    }

    if (empty($email)) {
        $error = 'Please enter an email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && ($password === 'password123' || password_verify($password, $user['password_hash']))) {
            $_SESSION['user'] = [
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Use one of the fast demo profile buttons below.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | CoreDesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px;">

<div style="width: 100%; max-width: 460px;">
    
    <div style="text-align: center; margin-bottom: 24px;">
        <a href="login.php" class="brand-wrap" style="justify-content: center; font-size: 30px; margin-bottom: 12px; display: inline-flex;">
            <span>Core<span class="brand-highlight">Desk</span></span>
        </a>

    </div>

    <div class="card" style="box-shadow: var(--shadow-elevated); padding: 32px;">
        <h2 style="font-size: 22px; font-weight: 800; color: var(--text-heading); margin-bottom: 6px; text-align: center;">
            Sign in to your account
        </h2>
        <p style="font-size: 13.5px; color: var(--text-muted); text-align: center; margin-bottom: 24px;">
            Enter your credentials or pick a demo persona
        </p>

        <?php if ($error): ?>
            <div class="toast-alert toast-error" style="position: static; margin-bottom: 20px; width: 100%;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div>
                <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                    Email Address
                </label>
                <input type="email" name="email" class="form-control" placeholder="admin@coredesk.local" required autofocus>
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 12.5px; color: var(--text-heading); margin-bottom: 6px;">
                    Password
                </label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; margin-top: 4px;">
                Sign In
            </button>
        </form>

        <!-- 1-Click Persona Quick Switcher (Zero Emojis) -->
        <div style="margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--border-subtle); text-align: center;">
            <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 12px;">
                Demo Profiles
            </span>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                <a href="switch-role.php?role=admin" class="btn btn-secondary btn-sm" style="flex-direction: column; gap: 2px; padding: 10px 4px;">
                    <strong style="font-size: 12.5px;">Admin</strong>
                    <span style="font-size: 10.5px; color: var(--text-muted);">Lead</span>
                </a>
                <a href="switch-role.php?role=agent" class="btn btn-secondary btn-sm" style="flex-direction: column; gap: 2px; padding: 10px 4px;">
                    <strong style="font-size: 12.5px;">Support</strong>
                    <span style="font-size: 10.5px; color: var(--text-muted);">Executive</span>
                </a>
                <a href="switch-role.php?role=customer" class="btn btn-secondary btn-sm" style="flex-direction: column; gap: 2px; padding: 10px 4px;">
                    <strong style="font-size: 12.5px;">Client</strong>
                    <span style="font-size: 10.5px; color: var(--text-muted);">Requester</span>
                </a>
            </div>

            <div style="font-size: 11.5px; color: var(--text-subtlest); margin-top: 14px;">
                Default demo password: <code>password123</code>
            </div>
        </div>
    </div>
</div>

</body>
</html>
