<?php
$pageTitle = 'Edit Contact';
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/contacts/index.php', 'error', t('error_not_found')); }

$contact = $db->prepare('SELECT * FROM contacts WHERE id = ?');
$contact->execute([$id]);
$contact = $contact->fetch();
if (!$contact) { redirectWithFlash(APP_URL . '/contacts/index.php', 'error', t('error_not_found')); }

$companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll();
$errors    = [];
$values    = $contact;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $values = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'phone'      => trim($_POST['phone']       ?? ''),
        'position'   => trim($_POST['position']    ?? ''),
        'company_id' => intParam('company_id', 0),
        'notes'      => trim($_POST['notes']       ?? ''),
    ];

    if ($values['first_name'] === '') $errors[] = t('first_name') . ': ' . t('required_field');
    if ($values['last_name']  === '') $errors[] = t('last_name')  . ': ' . t('required_field');

    if (empty($errors)) {
        $stmt = $db->prepare(
            'UPDATE contacts SET company_id=?, first_name=?, last_name=?, email=?, phone=?, position=?, notes=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([
            $values['company_id'] ?: null,
            $values['first_name'],
            $values['last_name'],
            $values['email']    ?: null,
            $values['phone']    ?: null,
            $values['position'] ?: null,
            $values['notes']    ?: null,
            $id,
        ]);
        saveCustomFieldValues('contacts', $id, $_POST);
        redirectWithFlash(APP_URL . '/contacts/view.php?id=' . $id, 'success', t('success_edit'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square me-2 text-primary"></i><?= e(t('edit_contact')) ?></h1>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
        </a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="form-section">
        <div class="form-section-title"><?= e(t('contact_details')) ?></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= e(t('first_name')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" value="<?= e($values['first_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('last_name')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" value="<?= e($values['last_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('email')) ?></label>
                <input type="email" class="form-control" name="email" value="<?= e($values['email']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('phone')) ?></label>
                <input type="text" class="form-control" name="phone" value="<?= e($values['phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('position')) ?></label>
                <input type="text" class="form-control" name="position" value="<?= e($values['position']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('company')) ?></label>
                <select class="form-select" name="company_id">
                    <option value=""><?= e(t('no_company')) ?></option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$values['company_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"><?= e($values['notes']) ?></textarea>
            </div>
        </div>

        <?= renderCustomFieldForm('contacts', $id) ?>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?>
        </button>
        <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <?= e(t('cancel')) ?>
        </a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
