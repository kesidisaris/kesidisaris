<?php
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/products/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT p.*, u.name AS created_by_name FROM products p LEFT JOIN users u ON p.created_by = u.id WHERE p.id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { redirectWithFlash(APP_URL . '/products/index.php', 'error', t('error_not_found')); }

$pageTitle = $product['name_el'];
$cfFields  = getCustomFields('products');
$cfValues  = getCustomFieldValues('products', $id);
$langKey   = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'label_en' : 'label_el';
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-box-seam me-2 text-primary"></i><?= e($product['name_el']) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/products/edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i><?= e(t('edit')) ?>
        </a>
        <a href="<?= APP_URL ?>/products/delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
           data-confirm="<?= e(t('confirm_delete')) ?>">
            <i class="bi bi-trash me-1"></i><?= e(t('delete')) ?>
        </a>
        <a href="<?= APP_URL ?>/products/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-info-circle me-1 text-primary"></i><?= e(t('product_details')) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('product_code')) ?></div>
                        <div class="detail-value font-monospace"><?= e($product['code'] ?: '—') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('active')) ?></div>
                        <div class="detail-value">
                            <?php if ($product['active']): ?>
                            <span class="badge bg-success"><?= e(t('active')) ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= e(t('inactive')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('name_el')) ?></div>
                        <div class="detail-value fw-semibold"><?= e($product['name_el']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('name_en')) ?></div>
                        <div class="detail-value fw-semibold"><?= e($product['name_en']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="detail-label"><?= e(t('price')) ?></div>
                        <div class="detail-value fw-bold text-primary fs-5"><?= formatMoney($product['price']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="detail-label"><?= e(t('unit')) ?></div>
                        <div class="detail-value"><?= e($product['unit'] ?: '—') ?></div>
                    </div>
                    <div class="col-4">
                        <div class="detail-label"><?= e(t('vat_rate')) ?></div>
                        <div class="detail-value"><?= number_format($product['vat_rate'], 0) ?>%</div>
                    </div>
                    <?php if ($product['description_el']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('description_el')) ?></div>
                        <div class="detail-value small" style="white-space:pre-wrap;"><?= e($product['description_el']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($product['description_en']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('description_en')) ?></div>
                        <div class="detail-value small" style="white-space:pre-wrap;"><?= e($product['description_en']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('created_at')) ?></div>
                        <div class="detail-value small text-muted"><?= formatDateTime($product['created_at']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('created_by')) ?></div>
                        <div class="detail-value small text-muted"><?= e($product['created_by_name'] ?: '—') ?></div>
                    </div>
                </div>

                <?php if (!empty($cfFields)): ?>
                <hr>
                <?php foreach ($cfFields as $cf): ?>
                <div class="mb-2">
                    <div class="detail-label"><?= e($cf[$langKey] ?: $cf['label_el']) ?></div>
                    <div class="detail-value">
                        <?php
                        $val = $cfValues[$cf['id']] ?? '';
                        if ($cf['field_type'] === 'checkbox') echo $val ? '<i class="bi bi-check-square-fill text-success"></i>' : '<i class="bi bi-square text-muted"></i>';
                        elseif ($cf['field_type'] === 'date') echo e(formatDate($val));
                        else echo e($val ?: '—');
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
