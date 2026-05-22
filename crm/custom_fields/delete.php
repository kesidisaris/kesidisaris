<?php
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$id = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/custom_fields/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT * FROM custom_fields WHERE id = ?');
$stmt->execute([$id]);
$field = $stmt->fetch();
if (!$field) { redirectWithFlash(APP_URL . '/custom_fields/index.php', 'error', t('error_not_found')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    // Values cascade delete via FK
    $db->prepare('DELETE FROM custom_fields WHERE id = ?')->execute([$id]);
    redirectWithFlash(APP_URL . '/custom_fields/index.php', 'success', t('success_delete'));
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1 class="text-danger"><i class="bi bi-trash me-2"></i><?= e(t('delete')) ?> <?= e(t('custom_field')) ?></h1>
</div>

<div class="card card-shadow" style="max-width:500px;">
    <div class="card-body text-center py-4">
        <i class="bi bi-exclamation-triangle-fill text-warning display-4 mb-3"></i>
        <p class="fs-5 mb-1"><?= e(t('confirm_delete')) ?></p>
        <p class="fw-bold"><?= e($field['label_el']) ?> / <?= e($field['label_en']) ?></p>
        <p class="text-muted small"><?= e(t('module')) ?>: <?= e(ucfirst(t($field['module']))) ?> | <?= e($field['field_name']) ?></p>
        <div class="alert alert-warning small">
            This will also delete all values stored for this field across all records.
        </div>
        <form method="post" class="d-flex gap-2 justify-content-center mt-3">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i><?= e(t('delete')) ?></button>
            <a href="<?= APP_URL ?>/custom_fields/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
