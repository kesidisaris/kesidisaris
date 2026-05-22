<?php
$pageTitle = 'Edit User';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$id = intParam('id');

// Users can edit their own profile; admins can edit anyone
if (!isAdmin() && (int)$id !== (int)$_SESSION['user_id']) {
    redirectWithFlash(APP_URL . '/index.php', 'error', t('error_permission'));
}

if (!$id) { redirectWithFlash(APP_URL . '/users/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { redirectWithFlash(APP_URL . '/users/index.php', 'error', t('error_not_found')); }

$errors = [];
$values = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'name'     => trim($_POST['name']     ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'role'     => in_array($_POST['role'] ?? '', ['admin','manager','sales']) ? $_POST['role'] : $user['role'],
        'language' => in_array($_POST['language'] ?? '', ['el','en']) ? $_POST['language'] : 'el',
        'active'   => isset($_POST['active']) ? 1 : 0,
        'password' => $_POST['password'] ?? '',
    ];

    // Non-admins can't change their role
    if (!isAdmin()) {
        $values['role']   = $user['role'];
        $values['active'] = $user['active'];
    }

    if ($values['name']  === '') $errors[] = t('user_name')  . ': ' . t('required_field');
    if ($values['email'] === '') $errors[] = t('user_email') . ': ' . t('required_field');
    if ($values['password'] !== '' && strlen($values['password']) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check email uniqueness (excluding self)
    if ($values['email'] !== '') {
        $check = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$values['email'], $id]);
        if ($check->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        if ($values['password'] !== '') {
            $hash = password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare(
                'UPDATE users SET name=?, email=?, password_hash=?, role=?, language=?, active=? WHERE id=?'
            )->execute([$values['name'], $values['email'], $hash, $values['role'], $values['language'], $values['active'], $id]);
        } else {
            $db->prepare(
                'UPDATE users SET name=?, email=?, role=?, language=?, active=? WHERE id=?'
            )->execute([$values['name'], $values['email'], $values['role'], $values['language'], $values['active'], $id]);
        }

        // Update session if editing self
        if ((int)$id === (int)$_SESSION['user_id']) {
            $_SESSION['user_name']  = $values['name'];
            $_SESSION['user_email'] = $values['email'];
            $_SESSION['user_lang']  = $values['language'];
        }

        redirectWithFlash(APP_URL . '/users/index.php', 'success', t('success_edit'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-person-gear me-2 text-primary"></i><?= e(t('edit_user')) ?></h1>
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
                <label class="form-label"><?= e(t('new_password')) ?></label>
                <input type="password" class="form-control" name="password" minlength="6">
                <div class="form-text"><?= e(t('leave_blank_pass')) ?></div>
            </div>
            <?php if (isAdmin()): ?>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('user_role')) ?></label>
                <select class="form-select" name="role">
                    <option value="admin"   <?= $values['role']==='admin'   ? 'selected':'' ?>><?= e(t('role_admin')) ?></option>
                    <option value="manager" <?= $values['role']==='manager' ? 'selected':'' ?>><?= e(t('role_manager')) ?></option>
                    <option value="sales"   <?= $values['role']==='sales'   ? 'selected':'' ?>><?= e(t('role_sales')) ?></option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('user_language')) ?></label>
                <select class="form-select" name="language">
                    <option value="el" <?= $values['language']==='el' ? 'selected':'' ?>>Ελληνικά</option>
                    <option value="en" <?= $values['language']==='en' ? 'selected':'' ?>>English</option>
                </select>
            </div>
            <?php if (isAdmin()): ?>
            <div class="col-md-4 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="active" id="active"
                           <?= $values['active'] ? 'checked' : '' ?>
                           <?= (int)$id === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="active"><?= e(t('user_active')) ?></label>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?></button>
        <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
