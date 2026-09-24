<?php
// =====================================================================
// config/auth.php
// Pure Session Authentication & Role Authorization Middleware
// =====================================================================

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookies
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Returns currently authenticated user session or null.
 */
function getLoggedInUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Checks if user is authenticated.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Verifies role-based access.
 */
function hasRole(string ...$allowedRoles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['user']['role'], $allowedRoles, true);
}

/**
 * Middleware: Enforces login requirement.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Middleware: Enforces specific roles (e.g. 'admin', 'agent').
 */
function requireRole(string ...$allowedRoles): void {
    requireLogin();
    if (!hasRole(...$allowedRoles)) {
        http_response_code(403);
        die('Access Denied: You do not possess the required permission level.');
    }
}

/**
 * CSRF Token Generator.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token Verifier.
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * XSS Sanitation helper.
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
