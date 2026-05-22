<?php
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();
$id = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/users/index.php', 'error', t('error_not_found')); }

// Cannot delete self
if ((int)$id === (int)$_SESSION['user_id']) {
    redirectWithFlash(APP_URL . '/users/index.php', 'error', t('error_permission'));
}

$stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { redirectWithFlash(APP_URL . '/users/index.php', 'error', t('error_not_found')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    redirectWithFlash(APP_URL . '/users/index.php', 'success', t('success_delete'));
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1 class="text-danger"><i class="bi bi-trash me-2"></i><?= e(t('delete_user')) ?></h1>
</div>

<div class="card card-shadow" style="max-width:500px;">
    <div class="card-body text-center py-4">
        <i class="bi bi-exclamation-triangle-fill text-warning display-4 mb-3"></i>
        <p class="fs-5 mb-1"><?= e(t('confirm_delete')) ?></p>
        <p class="fw-bold"><?= e($user['name']) ?></p>
        <p class="text-muted small"><?= e($user['email']) ?></p>
        <form method="post" class="d-flex gap-2 justify-content-center mt-3">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i><?= e(t('delete')) ?></button>
            <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
