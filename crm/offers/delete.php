<?php
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$id = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT id, offer_number, title FROM offers WHERE id = ?');
$stmt->execute([$id]);
$offer = $stmt->fetch();
if (!$offer) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $db->prepare('DELETE FROM custom_field_values WHERE module = ? AND record_id = ?')->execute(['offers', $id]);
    // offer_items cascade deletes via FK
    $db->prepare('DELETE FROM offers WHERE id = ?')->execute([$id]);
    redirectWithFlash(APP_URL . '/offers/index.php', 'success', t('success_delete'));
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1 class="text-danger"><i class="bi bi-trash me-2"></i><?= e(t('delete_offer')) ?></h1>
</div>

<div class="card card-shadow" style="max-width:500px;">
    <div class="card-body text-center py-4">
        <i class="bi bi-exclamation-triangle-fill text-warning display-4 mb-3"></i>
        <p class="fs-5 mb-1"><?= e(t('confirm_delete')) ?></p>
        <p class="fw-bold"><?= e($offer['offer_number']) ?> — <?= e($offer['title']) ?></p>
        <form method="post" class="d-flex gap-2 justify-content-center mt-3">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i><?= e(t('delete')) ?></button>
            <a href="<?= APP_URL ?>/offers/view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
