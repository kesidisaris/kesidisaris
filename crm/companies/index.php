<?php
$pageTitle = 'Companies';
require_once __DIR__ . '/../includes/header.php';

$db      = getDB();
$perPage = (int)getSetting('items_per_page', '20');
$page    = max(1, intParam('page'));
$search  = strParam('q');

$where  = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (name LIKE ? OR vat_number LIKE ? OR city LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = [$s, $s, $s, $s, $s];
}

$countSql = "SELECT COUNT(*) FROM companies $where";
$dataSql  = "SELECT * FROM companies $where ORDER BY name ASC";

$pager = paginate($countSql, $dataSql, $params, $page, $perPage);
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-building me-2 text-primary"></i><?= e(t('companies')) ?></h1>
    <a href="<?= APP_URL ?>/companies/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_company')) ?>
    </a>
</div>

<div class="search-bar">
    <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1"><?= e(t('search')) ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= e($search) ?>"
                       placeholder="<?= e(t('company_name')) ?>, <?= e(t('vat_number')) ?>, <?= e(t('city')) ?>...">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel me-1"></i><?= e(t('filter')) ?>
            </button>
            <a href="<?= APP_URL ?>/companies/index.php" class="btn btn-outline-secondary ms-1">
                <i class="bi bi-x me-1"></i><?= e(t('reset')) ?>
            </a>
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
                    <th><?= e(t('company_name')) ?></th>
                    <th><?= e(t('vat_number')) ?></th>
                    <th><?= e(t('city')) ?></th>
                    <th><?= e(t('phone')) ?></th>
                    <th><?= e(t('email')) ?></th>
                    <th class="action-col"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pager['data'])): ?>
            <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('no_results')) ?></td></tr>
            <?php else: ?>
            <?php foreach ($pager['data'] as $row): ?>
            <tr>
                <td>
                    <a href="<?= APP_URL ?>/companies/view.php?id=<?= $row['id'] ?>"
                       class="fw-semibold text-decoration-none">
                        <i class="bi bi-building me-1 text-muted"></i><?= e($row['name']) ?>
                    </a>
                </td>
                <td class="small text-muted"><?= e($row['vat_number'] ?: '—') ?></td>
                <td class="small"><?= e($row['city'] ?: '—') ?></td>
                <td class="small">
                    <?php if ($row['phone']): ?>
                    <a href="tel:<?= e($row['phone']) ?>" class="text-decoration-none"><?= e($row['phone']) ?></a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small">
                    <?php if ($row['email']): ?>
                    <a href="mailto:<?= e($row['email']) ?>" class="text-decoration-none"><?= e($row['email']) ?></a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/companies/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <a href="<?= APP_URL ?>/companies/edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= APP_URL ?>/companies/delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger"
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
        <?= renderPagination($pager, APP_URL . '/companies/index.php?' . http_build_query(['q' => $search])) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
