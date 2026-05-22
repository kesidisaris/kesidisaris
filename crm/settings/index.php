<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();

// Load all settings
$rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = $rows;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $keys = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass',
        'smtp_from_email', 'smtp_from_name',
        'company_name', 'company_phone', 'company_email', 'company_address',
        'currency_symbol', 'items_per_page',
    ];

    $upsert = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($keys as $key) {
        $val = trim($_POST[$key] ?? '');
        // Don't update password if blank
        if ($key === 'smtp_pass' && $val === '' && isset($settings['smtp_pass']) && $settings['smtp_pass'] !== '') {
            continue;
        }
        $upsert->execute([$key, $val]);
        $settings[$key] = $val;
    }

    setFlash('success', t('settings_saved'));
    header('Location: ' . APP_URL . '/settings/index.php');
    exit;
}

function sv(string $key, string $default = ''): string {
    global $settings;
    return $settings[$key] ?? $default;
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-gear me-2 text-primary"></i><?= e(t('settings')) ?></h1>
</div>

<form method="post" action="">
    <?= csrfField() ?>
    <div class="row g-3">
        <!-- SMTP Settings -->
        <div class="col-lg-6">
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-envelope-at me-1"></i><?= e(t('smtp_settings')) ?>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><?= e(t('smtp_host')) ?></label>
                        <input type="text" class="form-control" name="smtp_host"
                               value="<?= e(sv('smtp_host', 'smtp.gmail.com')) ?>"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('smtp_port')) ?></label>
                        <input type="number" class="form-control" name="smtp_port"
                               value="<?= e(sv('smtp_port', '587')) ?>" min="1" max="65535">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('smtp_encryption')) ?></label>
                        <select class="form-select" name="smtp_encryption">
                            <option value="tls" <?= sv('smtp_encryption')==='tls' ? 'selected':'' ?>>TLS (STARTTLS)</option>
                            <option value="ssl" <?= sv('smtp_encryption')==='ssl' ? 'selected':'' ?>>SSL</option>
                            <option value=""    <?= sv('smtp_encryption')===''    ? 'selected':'' ?>>None</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('smtp_user')) ?></label>
                        <input type="text" class="form-control" name="smtp_user"
                               value="<?= e(sv('smtp_user')) ?>"
                               placeholder="user@gmail.com" autocomplete="username">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('smtp_pass')) ?></label>
                        <input type="password" class="form-control" name="smtp_pass"
                               placeholder="<?= sv('smtp_pass') ? '(saved)' : 'Enter password' ?>"
                               autocomplete="current-password">
                        <div class="form-text">Leave blank to keep current password.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label"><?= e(t('smtp_from_email')) ?></label>
                        <input type="email" class="form-control" name="smtp_from_email"
                               value="<?= e(sv('smtp_from_email')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= e(t('smtp_from_name')) ?></label>
                        <input type="text" class="form-control" name="smtp_from_name"
                               value="<?= e(sv('smtp_from_name')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Settings -->
        <div class="col-lg-6">
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-building me-1"></i><?= e(t('company_settings')) ?>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><?= e(t('company_name')) ?></label>
                        <input type="text" class="form-control" name="company_name"
                               value="<?= e(sv('company_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('phone')) ?></label>
                        <input type="text" class="form-control" name="company_phone"
                               value="<?= e(sv('company_phone')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('email')) ?></label>
                        <input type="email" class="form-control" name="company_email"
                               value="<?= e(sv('company_email')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('address')) ?></label>
                        <input type="text" class="form-control" name="company_address"
                               value="<?= e(sv('company_address')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('currency_symbol')) ?? 'Currency Symbol' ?></label>
                        <input type="text" class="form-control" name="currency_symbol"
                               value="<?= e(sv('currency_symbol', '€')) ?>" maxlength="5">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('showing') . ' ' . t('results') ?> / page</label>
                        <select class="form-select" name="items_per_page">
                            <?php foreach ([10,15,20,25,50] as $n): ?>
                            <option value="<?= $n ?>" <?= sv('items_per_page','20') == $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?>
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
