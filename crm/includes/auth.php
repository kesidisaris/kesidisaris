<?php
// Authentication and session helpers

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/db.php';

function startAppSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startAppSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        $_SESSION['flash_error'] = 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη σελίδα.';
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function getCurrentUser(): array {
    startAppSession();
    if (!isLoggedIn()) return [];
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['user_name'],
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
        'language' => $_SESSION['user_lang'] ?? DEFAULT_LANG,
    ];
}

function loginUser(string $email, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        startAppSession();
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_lang']  = $user['language'];
        return true;
    }
    return false;
}

function logoutUser(): void {
    startAppSession();
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

// CSRF Token functions
function generateCsrfToken(): string {
    startAppSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    startAppSession();
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function checkCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed.');
        }
    }
}

// Flash messages
function setFlash(string $type, string $message): void {
    startAppSession();
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): string {
    startAppSession();
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return '';
}

function renderFlash(): string {
    $html = '';
    $success = getFlash('success');
    $error   = getFlash('error');
    $info    = getFlash('info');
    if ($success) $html .= '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($success) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    if ($error)   $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    if ($info)    $html .= '<div class="alert alert-info alert-dismissible fade show" role="alert">' . htmlspecialchars($info) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    return $html;
}

function canEdit(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'sales'], true);
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isManager(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true);
}
