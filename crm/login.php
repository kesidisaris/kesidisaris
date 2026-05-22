<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startAppSession();

// Already logged in
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// Handle language switch on login page
if (isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'en'], true)) {
    $_SESSION['user_lang'] = $_GET['lang'];
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['user_lang'])) {
    $_SESSION['user_lang'] = DEFAULT_LANG;
}

$lang  = getLang();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = $lang['error_invalid_csrf'];
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Check if user is active but password wrong vs not active
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && !$user['active']) {
            $error = $lang['login_inactive'];
        } elseif (!loginUser($email, $password)) {
            $error = $lang['login_error'];
        } else {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
    }
}

$currentLang = $_SESSION['user_lang'] ?? DEFAULT_LANG;
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($lang['login']) ?> - <?= e($lang['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">
<div class="login-wrapper">
    <div class="login-card card shadow-lg p-4">
        <div class="text-center mb-4">
            <div class="login-logo"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <h2 class="fw-bold text-dark"><?= e($lang['app_name']) ?></h2>
            <p class="text-muted small"><?= e($lang['login']) ?></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold"><?= e($lang['email']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" name="email"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="admin@crm.local" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold"><?= e($lang['password']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i><?= e($lang['login']) ?>
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                <?= e($lang['lang_el']) ?>:
                <a href="?lang=el" class="text-decoration-none <?= $currentLang === 'el' ? 'fw-bold' : '' ?>">ΕΛ</a> |
                <a href="?lang=en" class="text-decoration-none <?= $currentLang === 'en' ? 'fw-bold' : '' ?>">EN</a>
            </small>
        </div>

        <div class="text-center mt-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                <?= $currentLang === 'el' ? 'Demo: admin@crm.local / admin123' : 'Demo: admin@crm.local / admin123' ?>
            </small>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
