<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';

$db      = getDB();
$perPage = (int)getSetting('items_per_page', '20');
$page    = max(1, intParam('page'));
$search  = strParam('q');
$active  = strParam('active');

$where  = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (code LIKE ? OR name_el LIKE ? OR name_en LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = [$s, $s, $s];
}
if ($active !== '') {
    $where   .= ' AND active = ?';
    $params[] = (int)$active;
}

$countSql = "SELECT COUNT(*) FROM products $where";
$dataSql  = "SELECT * FROM products $where ORDER BY name_el ASC";

$pager = paginate($countSql, $dataSql, $params, $page, $perPage);
$langKey = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'name_en' : 'name_el';
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-box-seam me-2 text-primary"></i><?= e(t('products')) ?></h1>
    <a href="<?= APP_URL ?>/products/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_product')) ?>
    </a>
</div>

<div class="search-bar">
    <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1"><?= e(t('search')) ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= e($search) ?>"
                       placeholder="<?= e(t('product_code')) ?>, <?= e(t('product_name')) ?>...">
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1"><?= e(t('active')) ?></label>
            <select class="form-select" name="active">
                <option value=""><?= e(t('all')) ?></option>
                <option value="1" <?= $active === '1' ? 'selected' : '' ?>><?= e(t('active')) ?></option>
                <option value="0" <?= $active === '0' ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i><?= e(t('filter')) ?></button>
            <a href="<?= APP_URL ?>/products/index.php" class="btn btn-outline-secondary ms-1"><i class="bi bi-x me-1"></i><?= e(t('reset')) ?></a>
        </div>
    </form>
</div>

<div class="card card-shadow">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <span class="small text-muted"><?= e(t('showing')) ?> <?= $pager['total'] ?> <?= e(t('results')) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-crm mb-0">
            <thead>
                <tr>
                    <th><?= e(t('product_code')) ?></th>
                    <th><?= e(t('product_name')) ?></th>
                    <th class="text-end"><?= e(t('price')) ?></th>
                    <th class="text-center"><?= e(t('vat_rate')) ?></th>
                    <th><?= e(t('unit')) ?></th>
                    <th class="text-center"><?= e(t('active')) ?></th>
                    <th class="action-col"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pager['data'])): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('no_results')) ?></td></tr>
            <?php else: ?>
            <?php foreach ($pager['data'] as $row): ?>
            <tr>
                <td class="small text-muted font-monospace"><?= e($row['code'] ?: '—') ?></td>
                <td>
                    <a href="<?= APP_URL ?>/products/view.php?id=<?= $row['id'] ?>" class="fw-semibold text-decoration-none">
                        <?= e($row[$langKey]) ?>
                    </a>
                    <?php if ($row[$langKey] !== $row[$langKey === 'name_en' ? 'name_el' : 'name_en']): ?>
                    <small class="text-muted d-block"><?= e($row[$langKey === 'name_en' ? 'name_el' : 'name_en']) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-end fw-semibold"><?= formatMoney($row['price']) ?></td>
                <td class="text-center small"><?= number_format($row['vat_rate'], 0) ?>%</td>
                <td class="small"><?= e($row['unit'] ?: '—') ?></td>
                <td class="text-center">
                    <?php if ($row['active']): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><?= e(t('active')) ?></span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= e(t('inactive')) ?></span>
                    <?php endif; ?>
                </td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/products/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <a href="<?= APP_URL ?>/products/edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= APP_URL ?>/products/delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger"
                       data-confirm="<?= e(t('confirm_delete')) ?>"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
    <div class="card-footer bg-white py-2">
        <?= renderPagination($pager, APP_URL . '/products/index.php?' . http_build_query(['q' => $search, 'active' => $active])) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
