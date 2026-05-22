<?php
$pageTitle = 'Add Product';
require_once __DIR__ . '/../includes/header.php';

$db     = getDB();
$errors = [];
$values = ['code'=>'','name_el'=>'','name_en'=>'','description_el'=>'','description_en'=>'',
           'price'=>'0.00','unit'=>'τεμ.','vat_rate'=>'24','active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'code'           => trim($_POST['code']           ?? ''),
        'name_el'        => trim($_POST['name_el']        ?? ''),
        'name_en'        => trim($_POST['name_en']        ?? ''),
        'description_el' => trim($_POST['description_el'] ?? ''),
        'description_en' => trim($_POST['description_en'] ?? ''),
        'price'          => str_replace(',', '.', trim($_POST['price'] ?? '0')),
        'unit'           => trim($_POST['unit']           ?? 'τεμ.'),
        'vat_rate'       => str_replace(',', '.', trim($_POST['vat_rate'] ?? '24')),
        'active'         => isset($_POST['active']) ? 1 : 0,
    ];

    if ($values['name_el'] === '') $errors[] = t('name_el') . ': ' . t('required_field');
    if ($values['name_en'] === '') $errors[] = t('name_en') . ': ' . t('required_field');

    if (empty($errors)) {
        $stmt = $db->prepare(
            'INSERT INTO products (code, name_el, name_en, description_el, description_en, price, unit, vat_rate, active, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $values['code'] ?: null, $values['name_el'], $values['name_en'],
            $values['description_el'] ?: null, $values['description_en'] ?: null,
            (float)$values['price'], $values['unit'], (float)$values['vat_rate'],
            $values['active'], $_SESSION['user_id'],
        ]);
        $newId = (int)$db->lastInsertId();
        saveCustomFieldValues('products', $newId, $_POST);
        redirectWithFlash(APP_URL . '/products/view.php?id=' . $newId, 'success', t('success_add'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-box-seam me-2 text-primary"></i><?= e(t('add_product')) ?></h1>
    <a href="<?= APP_URL ?>/products/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="form-section">
        <div class="form-section-title"><?= e(t('product_details')) ?></div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?= e(t('product_code')) ?></label>
                <input type="text" class="form-control font-monospace" name="code" value="<?= e($values['code']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('price')) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><?= getSetting('currency_symbol', '€') ?></span>
                    <input type="number" class="form-control" name="price" value="<?= e($values['price']) ?>" step="0.01" min="0">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('unit')) ?></label>
                <input type="text" class="form-control" name="unit" value="<?= e($values['unit']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('vat_rate')) ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="vat_rate" value="<?= e($values['vat_rate']) ?>" step="0.01" min="0" max="100">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="active" id="active" <?= $values['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active"><?= e(t('active')) ?></label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('name_el')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name_el" value="<?= e($values['name_el']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('name_en')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name_en" value="<?= e($values['name_en']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('description_el')) ?></label>
                <textarea class="form-control" name="description_el" rows="3"><?= e($values['description_el']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('description_en')) ?></label>
                <textarea class="form-control" name="description_en" rows="3"><?= e($values['description_en']) ?></textarea>
            </div>
        </div>

        <?= renderCustomFieldForm('products') ?>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?></button>
        <a href="<?= APP_URL ?>/products/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
