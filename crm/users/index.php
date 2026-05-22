<?php
$pageTitle = 'Users';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db    = getDB();
$users = $db->query('SELECT * FROM users ORDER BY name ASC')->fetchAll();
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-people me-2 text-primary"></i><?= e(t('users')) ?></h1>
    <a href="<?= APP_URL ?>/users/add.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i><?= e(t('add_user')) ?>
    </a>
</div>

<div class="card card-shadow">
    <div class="table-responsive">
        <table class="table table-hover table-crm mb-0">
            <thead>
                <tr>
                    <th><?= e(t('user_name')) ?></th>
                    <th><?= e(t('user_email')) ?></th>
                    <th><?= e(t('user_role')) ?></th>
                    <th><?= e(t('user_language')) ?></th>
                    <th class="text-center"><?= e(t('user_active')) ?></th>
                    <th><?= e(t('created_at')) ?></th>
                    <th class="action-col"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('no_results')) ?></td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:34px;height:34px;font-size:0.75rem;font-weight:700;">
                            <?= e(mb_strtoupper(mb_substr($u['name'], 0, 2))) ?>
                        </div>
                        <span class="fw-semibold"><?= e($u['name']) ?></span>
                        <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">You</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="small"><?= e($u['email']) ?></td>
                <td>
                    <?php
                    $roleColors = ['admin'=>'danger','manager'=>'warning','sales'=>'info'];
                    $roleColor  = $roleColors[$u['role']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $roleColor ?>-subtle text-<?= $roleColor ?> border border-<?= $roleColor ?>-subtle">
                        <?= e(t('role_' . $u['role'])) ?>
                    </span>
                </td>
                <td class="small"><?= e(strtoupper($u['language'])) ?></td>
                <td class="text-center">
                    <?= $u['active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                </td>
                <td class="small text-muted"><?= formatDateTime($u['created_at']) ?></td>
                <td class="action-col">
                    <a href="<?= APP_URL ?>/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <a href="<?= APP_URL ?>/users/delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger"
                       data-confirm="<?= e(t('confirm_delete')) ?>"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
