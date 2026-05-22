<?php
$pageTitle = 'Contacts';
require_once __DIR__ . '/../includes/header.php';

$db      = getDB();
$perPage = (int)getSetting('items_per_page', '20');
$page    = max(1, intParam('page'));
$search  = strParam('q');
$compId  = intParam('company_id');

$where  = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (ct.first_name LIKE ? OR ct.last_name LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ? OR ct.position LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($compId > 0) {
    $where   .= ' AND ct.company_id = ?';
    $params[] = $compId;
}

$countSql = "SELECT COUNT(*) FROM contacts ct LEFT JOIN companies co ON ct.company_id = co.id $where";
$dataSql  = "SELECT ct.*, co.name AS company_name FROM contacts ct
             LEFT JOIN companies co ON ct.company_id = co.id
             $where ORDER BY ct.last_name ASC, ct.first_name ASC";

$pager = paginate($countSql, $dataSql, $params, $page, $perPage);

// For company filter dropdown
$companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll();
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-person-lines-fill me-2 text-primary"></i><?= e(t('contacts')) ?></h1>
    <a href="<?= APP_URL ?>/contacts/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_contact')) ?>
    </a>
</div>

<!-- Search / Filter -->
<div class="search-bar">
    <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1"><?= e(t('search')) ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= e($search) ?>"
                       placeholder="<?= e(t('first_name')) ?>, <?= e(t('last_name')) ?>, email...">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1"><?= e(t('company')) ?></label>
            <select class="form-select" name="company_id">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $compId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel me-1"></i><?= e(t('filter')) ?>
            </button>
            <a href="<?= APP_URL ?>/contacts/index.php" class="btn btn-outline-secondary ms-1">
                <i class="bi bi-x me-1"></i><?= e(t('reset')) ?>
            </a>
        </div>
    </form>
</div>

<!-- Results -->
<div class="card card-shadow">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <span class="small text-muted">
            <?= e(t('showing')) ?> <?= $pager['total'] ?> <?= e(t('results')) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-crm mb-0">
            <thead>
                <tr>
                    <th><?= e(t('full_name')) ?></th>
                    <th><?= e(t('company')) ?></th>
                    <th><?= e(t('email')) ?></th>
                    <th><?= e(t('phone')) ?></th>
                    <th><?= e(t('position')) ?></th>
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
                    <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $row['id'] ?>"
                       class="fw-semibold text-decoration-none">
                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                    </a>
                </td>
                <td>
                    <?php if ($row['company_name']): ?>
                    <a href="<?= APP_URL ?>/companies/view.php?id=<?= $row['company_id'] ?>"
                       class="text-decoration-none text-muted small">
                        <?= e($row['company_name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['email']): ?>
                    <a href="mailto:<?= e($row['email']) ?>" class="text-decoration-none small">
                        <?= e($row['email']) ?>
                    </a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="small"><?= e($row['phone'] ?: '—') ?></td>
                <td class="small text-muted"><?= e($row['position'] ?: '—') ?></td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $row['id'] ?>"
                       class="btn btn-sm btn-outline-secondary" title="<?= e(t('view')) ?>">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="<?= APP_URL ?>/contacts/edit.php?id=<?= $row['id'] ?>"
                       class="btn btn-sm btn-outline-primary" title="<?= e(t('edit')) ?>">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="<?= APP_URL ?>/contacts/delete.php?id=<?= $row['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       title="<?= e(t('delete')) ?>"
                       data-confirm="<?= e(t('confirm_delete')) ?>">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
    <div class="card-footer bg-white py-2">
        <?= renderPagination($pager, APP_URL . '/contacts/index.php?' . http_build_query(['q' => $search, 'company_id' => $compId])) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
