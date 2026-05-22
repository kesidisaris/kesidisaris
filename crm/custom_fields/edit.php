<?php
$pageTitle = 'Edit Custom Field';
require_once __DIR__ . '/../includes/header.php';

$db      = getDB();
$id      = intParam('id');
$modules = ['contacts', 'companies', 'products', 'offers'];
$types   = ['text','number','date','select','checkbox','memo'];

if (!$id) { redirectWithFlash(APP_URL . '/custom_fields/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare('SELECT * FROM custom_fields WHERE id = ?');
$stmt->execute([$id]);
$field = $stmt->fetch();
if (!$field) { redirectWithFlash(APP_URL . '/custom_fields/index.php', 'error', t('error_not_found')); }

$errors = [];
$values = $field;
$values['options_raw'] = '';
if ($field['options_json']) {
    $opts = json_decode($field['options_json'], true) ?: [];
    $values['options_raw'] = implode("\n", $opts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'module'      => in_array($_POST['module'] ?? '', $modules) ? $_POST['module'] : $field['module'],
        'field_name'  => preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['field_name'] ?? ''))),
        'label_el'    => trim($_POST['label_el']   ?? ''),
        'label_en'    => trim($_POST['label_en']   ?? ''),
        'field_type'  => in_array($_POST['field_type'] ?? '', $types) ? $_POST['field_type'] : 'text',
        'options_raw' => trim($_POST['options_raw'] ?? ''),
        'is_required' => isset($_POST['is_required']) ? 1 : 0,
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
    ];

    if ($values['field_name'] === '') $errors[] = t('field_name') . ': ' . t('required_field');
    if ($values['label_el']   === '') $errors[] = t('label_el')   . ': ' . t('required_field');
    if ($values['label_en']   === '') $errors[] = t('label_en')   . ': ' . t('required_field');

    $optionsJson = null;
    if ($values['field_type'] === 'select' && $values['options_raw'] !== '') {
        $opts = array_filter(array_map('trim', explode("\n", $values['options_raw'])));
        $optionsJson = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
    }

    if (empty($errors)) {
        try {
            $db->prepare(
                'UPDATE custom_fields SET module=?, field_name=?, label_el=?, label_en=?, field_type=?,
                 options_json=?, is_required=?, sort_order=? WHERE id=?'
            )->execute([
                $values['module'], $values['field_name'], $values['label_el'], $values['label_en'],
                $values['field_type'], $optionsJson, $values['is_required'], $values['sort_order'], $id,
            ]);
            redirectWithFlash(APP_URL . '/custom_fields/index.php', 'success', t('success_edit'));
        } catch (\PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ?
                'Field name "' . $values['field_name'] . '" already exists for this module.' :
                t('error_general');
        }
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square me-2 text-primary"></i><?= e(t('edit_custom_field')) ?></h1>
    <a href="<?= APP_URL ?>/custom_fields/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="form-section" style="max-width:700px;">
        <div class="form-section-title"><?= e(t('custom_field')) ?></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= e(t('module')) ?> <span class="text-danger">*</span></label>
                <select class="form-select" name="module" required>
                    <?php foreach ($modules as $m): ?>
                    <option value="<?= $m ?>" <?= $values['module'] === $m ? 'selected' : '' ?>><?= e(ucfirst(t($m))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('field_name')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control font-monospace" name="field_name"
                       value="<?= e($values['field_name']) ?>" required
                       pattern="[a-z0-9_]+" title="Lowercase letters, numbers and underscores only">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('label_el')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="label_el" value="<?= e($values['label_el']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('label_en')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="label_en" value="<?= e($values['label_en']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= e(t('field_type')) ?></label>
                <select class="form-select" name="field_type" id="fieldTypeSelect">
                    <option value="text"     <?= $values['field_type']==='text'     ? 'selected':'' ?>><?= e(t('field_type_text')) ?></option>
                    <option value="number"   <?= $values['field_type']==='number'   ? 'selected':'' ?>><?= e(t('field_type_number')) ?></option>
                    <option value="date"     <?= $values['field_type']==='date'     ? 'selected':'' ?>><?= e(t('field_type_date')) ?></option>
                    <option value="select"   <?= $values['field_type']==='select'   ? 'selected':'' ?>><?= e(t('field_type_select')) ?></option>
                    <option value="checkbox" <?= $values['field_type']==='checkbox' ? 'selected':'' ?>><?= e(t('field_type_checkbox')) ?></option>
                    <option value="memo"     <?= $values['field_type']==='memo'     ? 'selected':'' ?>><?= e(t('field_type_memo')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('sort_order')) ?></label>
                <input type="number" class="form-control" name="sort_order" value="<?= e($values['sort_order']) ?>" min="0">
            </div>
            <div class="col-md-3 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_required" id="is_required"
                           <?= $values['is_required'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_required"><?= e(t('is_required')) ?></label>
                </div>
            </div>
            <div class="col-12" id="optionsRow" style="<?= $values['field_type'] === 'select' ? '' : 'display:none;' ?>">
                <label class="form-label"><?= e(t('options')) ?></label>
                <textarea class="form-control font-monospace" name="options_raw" rows="5"><?= e($values['options_raw']) ?></textarea>
                <div class="form-text"><?= e(t('options')) ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?></button>
        <a href="<?= APP_URL ?>/custom_fields/index.php" class="btn btn-outline-secondary"><?= e(t('cancel')) ?></a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('fieldTypeSelect');
    var optRow = document.getElementById('optionsRow');
    function toggleOptions() { optRow.style.display = sel.value === 'select' ? '' : 'none'; }
    sel.addEventListener('change', toggleOptions);
    toggleOptions();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
