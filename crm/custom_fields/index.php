<?php
$pageTitle = 'Custom Fields';
require_once __DIR__ . '/../includes/header.php';

$db     = getDB();
$module = strParam('module');

$where  = 'WHERE 1=1';
$params = [];
if ($module !== '') {
    $where   .= ' AND module = ?';
    $params[] = $module;
}

$fields = $db->prepare("SELECT * FROM custom_fields $where ORDER BY module, sort_order, id");
$fields->execute($params);
$fields = $fields->fetchAll();

$modules = ['contacts', 'companies', 'products', 'offers'];

$typeLabels = [
    'text'     => t('field_type_text'),
    'number'   => t('field_type_number'),
    'date'     => t('field_type_date'),
    'select'   => t('field_type_select'),
    'checkbox' => t('field_type_checkbox'),
    'memo'     => t('field_type_memo'),
];
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-sliders me-2 text-primary"></i><?= e(t('custom_fields')) ?></h1>
    <a href="<?= APP_URL ?>/custom_fields/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_custom_field')) ?>
    </a>
</div>

<div class="search-bar">
    <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1"><?= e(t('module')) ?></label>
            <select class="form-select" name="module">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= $m ?>" <?= $module === $m ? 'selected' : '' ?>><?= e(ucfirst(t($m))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i><?= e(t('filter')) ?></button>
            <a href="<?= APP_URL ?>/custom_fields/index.php" class="btn btn-outline-secondary ms-1"><i class="bi bi-x me-1"></i><?= e(t('reset')) ?></a>
        </div>
    </form>
</div>

<div class="card card-shadow">
    <div class="table-responsive">
        <table class="table table-hover table-crm mb-0">
            <thead>
                <tr>
                    <th><?= e(t('module')) ?></th>
                    <th><?= e(t('field_name')) ?></th>
                    <th><?= e(t('label_el')) ?></th>
                    <th><?= e(t('label_en')) ?></th>
                    <th><?= e(t('field_type')) ?></th>
                    <th class="text-center"><?= e(t('is_required')) ?></th>
                    <th class="text-center"><?= e(t('sort_order')) ?></th>
                    <th class="action-col"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($fields)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4"><?= e(t('no_results')) ?></td></tr>
            <?php else: ?>
            <?php foreach ($fields as $f): ?>
            <tr>
                <td>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e(ucfirst(t($f['module']))) ?></span>
                </td>
                <td class="font-monospace small"><?= e($f['field_name']) ?></td>
                <td><?= e($f['label_el']) ?></td>
                <td><?= e($f['label_en']) ?></td>
                <td>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                        <?= e($typeLabels[$f['field_type']] ?? $f['field_type']) ?>
                    </span>
                </td>
                <td class="text-center">
                    <?= $f['is_required'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?>
                </td>
                <td class="text-center small"><?= $f['sort_order'] ?></td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/custom_fields/edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= APP_URL ?>/custom_fields/delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger"
                       data-confirm="<?= e(t('confirm_delete')) ?>"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
