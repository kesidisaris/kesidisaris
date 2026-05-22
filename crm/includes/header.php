<?php
// Header: HTML head + top navbar
// Expects $pageTitle to be set by the calling page
if (!defined('DB_HOST')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'en'], true)) {
    $_SESSION['user_lang'] = $_GET['lang'];
    // Persist in DB
    $db = getDB();
    $st = $db->prepare('UPDATE users SET language = ? WHERE id = ?');
    $st->execute([$_GET['lang'], $_SESSION['user_id']]);
    // Redirect to remove lang param
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['lang']);
    $qs = http_build_query($params);
    header('Location: ' . $url . ($qs ? '?' . $qs : ''));
    exit;
}

$lang       = getLang();
$currentUser = getCurrentUser();
$pageTitle   = $pageTitle ?? t('app_name');
$currentLang = $_SESSION['user_lang'] ?? DEFAULT_LANG;
$currentUri  = strtok($_SERVER['REQUEST_URI'], '?');

function isActive(string $path): string {
    global $currentUri;
    return (strpos($currentUri, $path) !== false) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e(t('app_name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="crm-body">

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
    <div class="container-fluid px-3">
        <button class="btn btn-sm btn-outline-light me-2 d-lg-none" id="sidebarToggle" type="button">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/index.php">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i><?= e(t('app_name')) ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Language switcher -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-translate me-1"></i>
                    <?= $currentLang === 'el' ? 'ΕΛ' : 'EN' ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item <?= $currentLang === 'el' ? 'active' : '' ?>"
                           href="?lang=el">
                            <i class="bi bi-check2 me-1 <?= $currentLang === 'el' ? '' : 'invisible' ?>"></i>
                            <?= e(t('lang_el')) ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $currentLang === 'en' ? 'active' : '' ?>"
                           href="?lang=en">
                            <i class="bi bi-check2 me-1 <?= $currentLang === 'en' ? '' : 'invisible' ?>"></i>
                            <?= e(t('lang_en')) ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= e($currentUser['name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted"><?= e($currentUser['role']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (isAdmin()): ?>
                    <li>
                        <a class="dropdown-item" href="<?= APP_URL ?>/users/edit.php?id=<?= $currentUser['id'] ?>">
                            <i class="bi bi-person-gear me-1"></i><?= e(t('profile')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i><?= e(t('logout')) ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

<div class="crm-wrapper">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main content -->
    <main class="crm-main">
        <div class="container-fluid py-4 px-4">
