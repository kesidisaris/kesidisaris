<?php
$pageTitle = 'Send Email';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare(
    'SELECT o.*,
            c.first_name, c.last_name, c.email AS contact_email,
            co.name AS company_name, co.email AS company_email
     FROM offers o
     LEFT JOIN contacts c  ON o.contact_id = c.id
     LEFT JOIN companies co ON o.company_id = co.id
     WHERE o.id = ?'
);
$stmt->execute([$id]);
$offer = $stmt->fetch();
if (!$offer) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

// Line items for the email body
$itemStmt = $db->prepare('SELECT * FROM offer_items WHERE offer_id = ? ORDER BY sort_order');
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

// Default recipient
$defaultTo = $offer['contact_email'] ?: $offer['company_email'] ?: '';

// Build default subject and body
$companyName = getSetting('company_name', 'CRM');
$currSym     = getSetting('currency_symbol', '€');
$defaultSubject = t('offer') . ': ' . $offer['offer_number'] . ' - ' . $offer['title'];

// Build HTML email body
ob_start();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;font-size:14px;}
.offer-table{border-collapse:collapse;width:100%;margin-top:12px;}
.offer-table th{background:#0d6efd;color:#fff;padding:8px 10px;text-align:left;font-size:12px;}
.offer-table td{padding:7px 10px;border-bottom:1px solid #eee;font-size:13px;}
.totals{margin-top:16px;text-align:right;}
.totals table{margin-left:auto;}
.totals td{padding:4px 10px;}
.grand-total td{font-weight:bold;font-size:15px;border-top:2px solid #333;}
</style></head>
<body>
<p><?= t('contact') ?>,</p>
<p><?= t('current_lang') ?? 'Σας αποστέλλουμε την προσφορά μας' ?>:</p>
<p><strong><?= htmlspecialchars($offer['offer_number']) ?></strong> &mdash; <?= htmlspecialchars($offer['title']) ?></p>
<?php if ($offer['valid_until']): ?>
<p><?= t('valid_until') ?>: <strong><?= formatDate($offer['valid_until']) ?></strong></p>
<?php endif; ?>
<table class="offer-table">
    <thead>
        <tr>
            <th><?= t('description') ?></th>
            <th><?= t('qty') ?></th>
            <th><?= t('unit_price') ?></th>
            <th><?= t('discount_pct') ?></th>
            <th><?= t('vat_rate') ?></th>
            <th><?= t('line_total') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['description']) ?></td>
            <td><?= number_format($item['qty'], 2) ?></td>
            <td><?= number_format($item['unit_price'], 2, ',', '.') ?> <?= htmlspecialchars($currSym) ?></td>
            <td><?= $item['discount_pct'] > 0 ? '-' . number_format($item['discount_pct'], 2) . '%' : '—' ?></td>
            <td><?= number_format($item['vat_rate'], 0) ?>%</td>
            <td><?= number_format($item['line_total'], 2, ',', '.') ?> <?= htmlspecialchars($currSym) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div class="totals">
    <table>
        <tr><td><?= t('total_net') ?>:</td><td><?= number_format($offer['total_net'], 2, ',', '.') ?> <?= htmlspecialchars($currSym) ?></td></tr>
        <tr><td><?= t('total_vat') ?>:</td><td><?= number_format($offer['total_vat'], 2, ',', '.') ?> <?= htmlspecialchars($currSym) ?></td></tr>
        <tr class="grand-total"><td><?= t('total_gross') ?>:</td><td><?= number_format($offer['total_gross'], 2, ',', '.') ?> <?= htmlspecialchars($currSym) ?></td></tr>
    </table>
</div>
<br>
<p><?= t('notes') ?>: <?= htmlspecialchars($offer['notes'] ?? '') ?></p>
<hr>
<p><small><?= htmlspecialchars($companyName) ?> &mdash; <?= htmlspecialchars(getSetting('company_email', '')) ?> &mdash; <?= htmlspecialchars(getSetting('company_phone', '')) ?></small></p>
</body>
</html>
<?php
$defaultBody = ob_get_clean();

$success = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $toEmail   = trim($_POST['to_email']  ?? '');
    $subject   = trim($_POST['subject']   ?? '');
    $body      = $_POST['body']           ?? '';

    if ($toEmail === '' || $subject === '') {
        setFlash('error', t('error_required'));
    } else {
        // Load SMTP settings
        $smtpHost    = getSetting('smtp_host',       'localhost');
        $smtpPort    = (int)getSetting('smtp_port',  '587');
        $smtpEnc     = getSetting('smtp_encryption', 'tls');
        $smtpUser    = getSetting('smtp_user',       '');
        $smtpPass    = getSetting('smtp_pass',       '');
        $fromEmail   = getSetting('smtp_from_email', 'noreply@crm.local');
        $fromName    = getSetting('smtp_from_name',  $companyName);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = ($smtpUser !== '');
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpEnc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $success = true;

            // Update offer status to 'sent' if currently draft
            if ($offer['status'] === 'draft') {
                $db->prepare('UPDATE offers SET status = ? WHERE id = ?')->execute(['sent', $id]);
            }

            // Log success
            $db->prepare(
                'INSERT INTO email_log (offer_id, sent_to, sent_by, subject, body, success) VALUES (?,?,?,?,?,1)'
            )->execute([$id, $toEmail, $_SESSION['user_id'], $subject, $body]);

            setFlash('success', t('email_sent_ok'));
            header('Location: ' . APP_URL . '/offers/view.php?id=' . $id);
            exit;

        } catch (MailerException $e) {
            $success  = false;
            $errorMsg = $mail->ErrorInfo;

            // Log failure
            $db->prepare(
                'INSERT INTO email_log (offer_id, sent_to, sent_by, subject, body, success, error_msg) VALUES (?,?,?,?,?,0,?)'
            )->execute([$id, $toEmail, $_SESSION['user_id'], $subject, $body, $errorMsg]);

            setFlash('error', t('email_sent_fail') . $errorMsg);
        }
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-envelope me-2 text-success"></i><?= e(t('send_offer_email')) ?></h1>
        <p class="text-muted mb-0"><?= e($offer['offer_number']) ?> — <?= e($offer['title']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/offers/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="form-section">
            <div class="form-section-title"><?= e(t('send_offer_email')) ?></div>
            <form method="post" action="">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= e(t('email_to')) ?> <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="to_email"
                           value="<?= e($_POST['to_email'] ?? $defaultTo) ?>" required>
                    <div class="form-text">
                        <?php if ($offer['contact_email']): ?>
                        <a href="#" onclick="document.querySelector('[name=to_email]').value='<?= e($offer['contact_email']) ?>'; return false;">
                            <?= e($offer['first_name'] . ' ' . $offer['last_name']) ?>: <?= e($offer['contact_email']) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($offer['company_email'] && $offer['company_email'] !== $offer['contact_email']): ?>
                        &nbsp;|&nbsp;
                        <a href="#" onclick="document.querySelector('[name=to_email]').value='<?= e($offer['company_email']) ?>'; return false;">
                            <?= e($offer['company_name']) ?>: <?= e($offer['company_email']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= e(t('email_subject')) ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="subject"
                           value="<?= e($_POST['subject'] ?? $defaultSubject) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= e(t('email_body')) ?></label>
                    <textarea class="form-control font-monospace" name="body" rows="20" style="font-size:0.8rem;"><?= htmlspecialchars($_POST['body'] ?? $defaultBody) ?></textarea>
                    <div class="form-text">HTML is supported.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i><?= e(t('send_email')) ?>
                    </button>
                    <a href="<?= APP_URL ?>/offers/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                        <?= e(t('cancel')) ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-info-circle me-1"></i>SMTP
            </div>
            <div class="card-body small">
                <div class="mb-1"><strong>Host:</strong> <?= e(getSetting('smtp_host')) ?></div>
                <div class="mb-1"><strong>Port:</strong> <?= e(getSetting('smtp_port')) ?></div>
                <div class="mb-1"><strong>Enc:</strong> <?= e(getSetting('smtp_encryption')) ?></div>
                <div class="mb-1"><strong>From:</strong> <?= e(getSetting('smtp_from_email')) ?></div>
                <?php if (!getSetting('smtp_host')): ?>
                <div class="alert alert-warning mt-2 mb-0 small">
                    SMTP not configured. <a href="<?= APP_URL ?>/settings/index.php"><?= e(t('settings')) ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
