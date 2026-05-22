<?php
$pageTitle = 'Edit Company';
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/companies/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$id]);
$company = $stmt->fetch();
if (!$company) { redirectWithFlash(APP_URL . '/companies/index.php', 'error', t('error_not_found')); }

$errors = [];
$values = $company;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'name'       => trim($_POST['name']       ?? ''),
        'vat_number' => trim($_POST['vat_number'] ?? ''),
        'phone'      => trim($_POST['phone']       ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'address'    => trim($_POST['address']     ?? ''),
        'city'       => trim($_POST['city']        ?? ''),
        'country'    => trim($_POST['country']     ?? 'Greece'),
        'notes'      => trim($_POST['notes']       ?? ''),
    ];

    if ($values['name'] === '') $errors[] = t('company_name') . ': ' . t('required_field');

    if (empty($errors)) {
        $db->prepare(
            'UPDATE companies SET name=?, vat_number=?, phone=?, email=?, address=?, city=?, country=?, notes=?, updated_at=NOW()
             WHERE id=?'
        )->execute([
            $values['name'], $values['vat_number'] ?: null, $values['phone'] ?: null,
            $values['email'] ?: null, $values['address'] ?: null, $values['city'] ?: null,
            $values['country'] ?: 'Greece', $values['notes'] ?: null, $id,
        ]);
        saveCustomFieldValues('companies', $id, $_POST);
        redirectWithFlash(APP_URL . '/companies/view.php?id=' . $id, 'success', t('success_edit'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square me-2 text-primary"></i><?= e(t('edit_company')) ?></h1>
    <a href="<?= APP_URL ?>/companies/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="form-section">
        <div class="form-section-title"><?= e(t('company_details')) ?></div>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label"><?= e(t('company_name')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="<?= e($values['name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('vat_number')) ?></label>
                <input type="text" class="form-control" name="vat_number" value="<?= e($values['vat_number']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('phone')) ?></label>
                <input type="text" class="form-control" name="phone" value="<?= e($values['phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('email')) ?></label>
                <input type="email" class="form-control" name="email" value="<?= e($values['email']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('address')) ?></label>
                <input type="text" class="form-control" name="address" value="<?= e($values['address']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('city')) ?></label>
                <input type="text" class="form-control" name="city" value="<?= e($values['city']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('country')) ?></label>
                <input type="text" class="form-control" name="country" value="<?= e($values['country']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"><?= e($values['notes']) ?></textarea>
            </div>
        </div>

        <?= renderCustomFieldForm('companies', $id) ?>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?></button>
        <a href="<?= APP_URL ?>/companies/view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
