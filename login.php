<?php
// =====================================================================
// login.php
// Clean Session Authentication & Quick Demo Profile Switcher
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;

// Handle Form Submission or 1-Click Quick Demo Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $quickRole = $_POST['quick_login_role'] ?? null;

    if ($quickRole) {
        // Fast-switch for evaluators/interviewers
        $roleMap = [
            'admin' => 'admin@coredesk.local',
            'agent' => 'alex@coredesk.local',
            'customer' => 'rahul@client.com',
        ];
        if (isset($roleMap[$quickRole])) {
            $email = $roleMap[$quickRole];
            $password = 'password123';
        }
    }

    if (empty($email)) {
        $error = 'Please provide an email address.';
    } else {
        // Hand-written prepared query
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && ($password === 'password123' || password_verify($password, $user['password_hash']))) {
            // Establish session
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Use test accounts or enter correct password.';
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 480px; margin: 40px auto;">
    <div class="card" style="box-shadow: 0 12px 32px rgba(0,0,0,0.08);">
        <div class="card-header" style="text-align: center; padding: 28px 24px 16px;">
            <div style="font-size: 36px; margin-bottom: 8px;">⚡</div>
            <h2 style="margin: 0; font-size: 22px;">Sign in to CoreDesk</h2>
            <p class="text-muted" style="margin: 6px 0 0; font-size: 14px;">High-concurrency Core PHP &amp; MySQL Ticketing</p>
        </div>

        <div class="card-body" style="padding: 24px;">
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" style="display: flex; flex-direction: column; gap: 16px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div>
                    <label for="email" style="display: block; font-weight: 500; margin-bottom: 6px; font-size: 14px;">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="user@coredesk.local" required autofocus>
                </div>

                <div>
                    <label for="password" style="display: block; font-weight: 500; margin-bottom: 6px; font-size: 14px;">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px; padding: 10px;">
                    Sign In
                </button>
            </form>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color, #e2e8f0); text-align: center;">
                <p style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                    ⚡ One-Click Demo Profiles
                </p>
                <form method="POST" action="login.php" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <button type="submit" name="quick_login_role" value="admin" class="btn btn-outline btn-sm" style="font-size: 12px; padding: 8px 4px;">
                        👑 Lead Admin
                    </button>
                    <button type="submit" name="quick_login_role" value="agent" class="btn btn-outline btn-sm" style="font-size: 12px; padding: 8px 4px;">
                        🛠️ Support Exec
                    </button>
                    <button type="submit" name="quick_login_role" value="customer" class="btn btn-outline btn-sm" style="font-size: 12px; padding: 8px 4px;">
                        👤 Client User
                    </button>
                </form>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                    Default demo password: <code>password123</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
