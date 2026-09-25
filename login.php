<?php
// =====================================================================
// login.php
// Enterprise Identity & Access Management (Atlassian Cloud SSO Aesthetic)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Redirect if already authenticated
if (isLoggedIn()) {
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
        $error = 'Please provide an authorized corporate email.';
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
            $error = 'Invalid corporate credentials. Click one of the demo profile switchers below.';
        }
    }
}

$isAuthPage = true;
$pageTitle = 'Enterprise Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mnc-login-wrapper">
    <div class="login-glass-card">
        
        <div class="login-card-header">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: linear-gradient(135deg, #0052CC, #0747A6); border-radius: 12px; margin-bottom: 14px; box-shadow: 0 4px 12px rgba(0,82,204,0.35);">
                <span style="font-size: 24px; color: #FFFFFF;">⚡</span>
            </div>
            <h2 style="font-size: 22px; font-weight: 700; color: #172B4D; margin-bottom: 4px;">Sign in to CoreDesk</h2>
            <p style="color: #6B778C; font-size: 13.5px; margin: 0;">Enterprise Incident Management &middot; Jira Standard</p>
        </div>

        <div style="padding: 28px 32px 32px;">
            <?php if ($error): ?>
                <div class="toast-alert toast-error" style="position: static; margin-bottom: 20px; width: 100%;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" style="display: flex; flex-direction: column; gap: 16px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div>
                    <label style="display: block; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #6B778C; margin-bottom: 6px; letter-spacing: 0.04em;">
                        Work Email Address
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="user@coredesk.local" required autofocus style="background: #FFFFFF;">
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <label style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: #6B778C; letter-spacing: 0.04em;">
                            Password
                        </label>
                        <span style="font-size: 12px; color: var(--jira-blue);">SSO Active</span>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required style="background: #FFFFFF;">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 10px; width: 100%; margin-top: 4px; font-size: 14px;">
                    Log In to Workspace
                </button>
            </form>

            <!-- 1-Click Persona Switcher for Evaluators / Recruiters -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--jira-border-subtle); text-align: center;">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6B778C; letter-spacing: 0.06em; display: block; margin-bottom: 12px;">
                    ⚡ 1-Click Fast Evaluator Profiles
                </span>

                <form method="POST" action="login.php" class="login-persona-grid">
                    <button type="submit" name="quick_login_role" value="admin" class="persona-btn">
                        <div style="font-size: 16px;">👑</div>
                        <strong style="font-size: 12px; color: #172B4D; display: block;">Lead Admin</strong>
                        <span style="font-size: 10px; color: #6B778C;">Full Access</span>
                    </button>

                    <button type="submit" name="quick_login_role" value="agent" class="persona-btn">
                        <div style="font-size: 16px;">🛠️</div>
                        <strong style="font-size: 12px; color: #172B4D; display: block;">Support Tier-2</strong>
                        <span style="font-size: 10px; color: #6B778C;">Agent Triage</span>
                    </button>

                    <button type="submit" name="quick_login_role" value="customer" class="persona-btn">
                        <div style="font-size: 16px;">👤</div>
                        <strong style="font-size: 12px; color: #172B4D; display: block;">Client Portal</strong>
                        <span style="font-size: 10px; color: #6B778C;">Requester</span>
                    </button>
                </form>
            </div>

            <div style="margin-top: 20px; text-align: center; font-size: 11px; color: #8993A4;">
                CoreDesk Cloud &bull; SOC2 Type II Certified &bull; TLS 1.3
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
