<?php
$pageTitle = 'Add User';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db     = getDB();
$errors = [];
$values = ['name'=>'','email'=>'','role'=>'sales','language'=>'el','active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'name'     => trim($_POST['name']     ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'role'     => in_array($_POST['role'] ?? '', ['admin','manager','sales']) ? $_POST['role'] : 'sales',
        'language' => in_array($_POST['language'] ?? '', ['el','en']) ? $_POST['language'] : 'el',
        'active'   => isset($_POST['active']) ? 1 : 0,
        'password' => $_POST['password'] ?? '',
    ];

    if ($values['name']     === '') $errors[] = t('user_name')  . ': ' . t('required_field');
    if ($values['email']    === '') $errors[] = t('user_email') . ': ' . t('required_field');
    if ($values['password'] === '') $errors[] = t('password')   . ': ' . t('required_field');
    if (strlen($values['password']) < 6 && $values['password'] !== '') $errors[] = 'Password must be at least 6 characters.';

    // Check email uniqueness
    if ($values['email'] !== '') {
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$values['email']]);
        if ($check->fetch()) $errors[] = 'Email already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare(
            'INSERT INTO users (name, email, password_hash, role, language, active) VALUES (?,?,?,?,?,?)'
        )->execute([$values['name'], $values['email'], $hash, $values['role'], $values['language'], $values['active']]);
        redirectWithFlash(APP_URL . '/users/index.php', 'success', t('success_add'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-person-plus me-2 text-primary"></i><?= e(t('add_user')) ?></h1>
    <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="form-section" style="max-width:600px;">
        <div class="form-section-title"><?= e(t('user')) ?></div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label"><?= e(t('user_name')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="<?= e($values['name']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('user_email')) ?> <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" value="<?= e($values['email']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('password')) ?> <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" minlength="6" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('user_role')) ?></label>
                <select class="form-select" name="role">
                    <option value="admin"   <?= $values['role']==='admin'   ? 'selected':'' ?>><?= e(t('role_admin')) ?></option>
                    <option value="manager" <?= $values['role']==='manager' ? 'selected':'' ?>><?= e(t('role_manager')) ?></option>
                    <option value="sales"   <?= $values['role']==='sales'   ? 'selected':'' ?>><?= e(t('role_sales')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('user_language')) ?></label>
                <select class="form-select" name="language">
                    <option value="el" <?= $values['language']==='el' ? 'selected':'' ?>>Ελληνικά</option>
                    <option value="en" <?= $values['language']==='en' ? 'selected':'' ?>>English</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="active" id="active" <?= $values['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active"><?= e(t('user_active')) ?></label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?></button>
        <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
