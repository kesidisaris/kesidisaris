<?php
$pageTitle = 'Offers';
require_once __DIR__ . '/../includes/header.php';

$db      = getDB();
$perPage = (int)getSetting('items_per_page', '20');
$page    = max(1, intParam('page'));
$search  = strParam('q');
$status  = strParam('status');

$where  = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (o.offer_number LIKE ? OR o.title LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR co.name LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = [$s, $s, $s, $s, $s];
}
if ($status !== '') {
    $where   .= ' AND o.status = ?';
    $params[] = $status;
}

$countSql = "SELECT COUNT(*) FROM offers o
             LEFT JOIN contacts c ON o.contact_id = c.id
             LEFT JOIN companies co ON o.company_id = co.id
             $where";

$dataSql = "SELECT o.*, c.first_name, c.last_name, co.name AS company_name
            FROM offers o
            LEFT JOIN contacts c ON o.contact_id = c.id
            LEFT JOIN companies co ON o.company_id = co.id
            $where ORDER BY o.created_at DESC";

$pager = paginate($countSql, $dataSql, $params, $page, $perPage);
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e(t('offers')) ?></h1>
    <a href="<?= APP_URL ?>/offers/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_offer')) ?>
    </a>
</div>

<div class="search-bar">
    <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1"><?= e(t('search')) ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= e($search) ?>"
                       placeholder="<?= e(t('offer_number')) ?>, <?= e(t('offer_title')) ?>, <?= e(t('company')) ?>...">
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1"><?= e(t('offer_status')) ?></label>
            <select class="form-select" name="status">
                <option value=""><?= e(t('all')) ?></option>
                <option value="draft"    <?= $status === 'draft'    ? 'selected' : '' ?>><?= e(t('status_draft')) ?></option>
                <option value="sent"     <?= $status === 'sent'     ? 'selected' : '' ?>><?= e(t('status_sent')) ?></option>
                <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>><?= e(t('status_accepted')) ?></option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>><?= e(t('status_rejected')) ?></option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i><?= e(t('filter')) ?></button>
            <a href="<?= APP_URL ?>/offers/index.php" class="btn btn-outline-secondary ms-1"><i class="bi bi-x me-1"></i><?= e(t('reset')) ?></a>
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
                    <th><?= e(t('offer_number')) ?></th>
                    <th><?= e(t('offer_title')) ?></th>
                    <th><?= e(t('contact')) ?></th>
                    <th><?= e(t('company')) ?></th>
                    <th><?= e(t('offer_status')) ?></th>
                    <th><?= e(t('valid_until')) ?></th>
                    <th class="text-end"><?= e(t('total_gross')) ?></th>
                    <th class="action-col"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pager['data'])): ?>
            <tr><td colspan="8" class="text-center text-muted py-4"><?= e(t('no_results')) ?></td></tr>
            <?php else: ?>
            <?php foreach ($pager['data'] as $row): ?>
            <tr>
                <td>
                    <a href="<?= APP_URL ?>/offers/view.php?id=<?= $row['id'] ?>" class="fw-semibold text-decoration-none font-monospace">
                        <?= e($row['offer_number']) ?>
                    </a>
                </td>
                <td class="text-truncate-custom"><?= e($row['title']) ?></td>
                <td class="small">
                    <?php if ($row['first_name']): ?>
                    <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $row['contact_id'] ?>" class="text-decoration-none">
                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small">
                    <?php if ($row['company_name']): ?>
                    <a href="<?= APP_URL ?>/companies/view.php?id=<?= $row['company_id'] ?>" class="text-decoration-none text-muted">
                        <?= e($row['company_name']) ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= offerStatusBadge($row['status']) ?></td>
                <td class="small">
                    <?php if ($row['valid_until']): ?>
                    <?php
                        $validDate = new DateTime($row['valid_until']);
                        $today     = new DateTime();
                        $expired   = $validDate < $today && !in_array($row['status'], ['accepted']);
                    ?>
                    <span class="<?= $expired ? 'text-danger' : '' ?>"><?= formatDate($row['valid_until']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-end fw-semibold"><?= formatMoney($row['total_gross']) ?></td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/offers/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view')) ?>"><i class="bi bi-eye"></i></a>
                    <a href="<?= APP_URL ?>/offers/edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= e(t('edit')) ?>"><i class="bi bi-pencil"></i></a>
                    <a href="<?= APP_URL ?>/offers/delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger"
                       data-confirm="<?= e(t('confirm_delete')) ?>" title="<?= e(t('delete')) ?>"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
    <div class="card-footer bg-white py-2">
        <?= renderPagination($pager, APP_URL . '/offers/index.php?' . http_build_query(['q' => $search, 'status' => $status])) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
