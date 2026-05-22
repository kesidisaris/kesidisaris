<?php
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare(
    'SELECT o.*,
            c.first_name, c.last_name, c.email AS contact_email, c.phone AS contact_phone,
            co.name AS company_name, co.email AS company_email,
            u.name AS created_by_name
     FROM offers o
     LEFT JOIN contacts c  ON o.contact_id = c.id
     LEFT JOIN companies co ON o.company_id = co.id
     LEFT JOIN users u      ON o.created_by = u.id
     WHERE o.id = ?'
);
$stmt->execute([$id]);
$offer = $stmt->fetch();
if (!$offer) { redirectWithFlash(APP_URL . '/offers/index.php', 'error', t('error_not_found')); }

$pageTitle = $offer['offer_number'];

// Line items
$itemStmt = $db->prepare(
    'SELECT oi.*, p.code AS product_code
     FROM offer_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.offer_id = ? ORDER BY oi.sort_order ASC'
);
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

// Email log
$emailLog = $db->prepare(
    'SELECT el.*, u.name AS sent_by_name
     FROM email_log el LEFT JOIN users u ON el.sent_by = u.id
     WHERE el.offer_id = ? ORDER BY el.sent_at DESC'
);
$emailLog->execute([$id]);
$emails = $emailLog->fetchAll();

$cfFields = getCustomFields('offers');
$cfValues = getCustomFieldValues('offers', $id);
$langKey  = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'label_en' : 'label_el';
$currSym  = getSetting('currency_symbol', '€');
?>

<?= renderFlash() ?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e($offer['offer_number']) ?></h1>
        <p class="text-muted mb-0"><?= e($offer['title']) ?> &nbsp;<?= offerStatusBadge($offer['status']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/offers/send_email.php?id=<?= $id ?>" class="btn btn-success">
            <i class="bi bi-envelope me-1"></i><?= e(t('send_email')) ?>
        </a>
        <a href="<?= APP_URL ?>/offers/edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i><?= e(t('edit')) ?>
        </a>
        <a href="<?= APP_URL ?>/offers/delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
           data-confirm="<?= e(t('confirm_delete')) ?>">
            <i class="bi bi-trash me-1"></i><?= e(t('delete')) ?>
        </a>
        <a href="<?= APP_URL ?>/offers/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Offer Info -->
    <div class="col-lg-4">
        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-info-circle me-1 text-primary"></i><?= e(t('offer_details')) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('offer_number')) ?></div>
                        <div class="detail-value font-monospace"><?= e($offer['offer_number']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('offer_status')) ?></div>
                        <div class="detail-value"><?= offerStatusBadge($offer['status']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('offer_title')) ?></div>
                        <div class="detail-value fw-semibold"><?= e($offer['title']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('valid_until')) ?></div>
                        <div class="detail-value"><?= $offer['valid_until'] ? formatDate($offer['valid_until']) : '—' ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('created_at')) ?></div>
                        <div class="detail-value small text-muted"><?= formatDateTime($offer['created_at']) ?></div>
                    </div>
                    <?php if ($offer['first_name']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('contact')) ?></div>
                        <div class="detail-value">
                            <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $offer['contact_id'] ?>">
                                <i class="bi bi-person me-1"></i><?= e($offer['first_name'] . ' ' . $offer['last_name']) ?>
                            </a>
                            <?php if ($offer['contact_email']): ?>
                            <small class="text-muted d-block"><?= e($offer['contact_email']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($offer['company_name']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('company')) ?></div>
                        <div class="detail-value">
                            <a href="<?= APP_URL ?>/companies/view.php?id=<?= $offer['company_id'] ?>">
                                <i class="bi bi-building me-1"></i><?= e($offer['company_name']) ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($offer['notes']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('notes')) ?></div>
                        <div class="detail-value small" style="white-space:pre-wrap;"><?= e($offer['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($cfFields)): ?>
                <hr>
                <?php foreach ($cfFields as $cf): ?>
                <div class="mb-2">
                    <div class="detail-label"><?= e($cf[$langKey] ?: $cf['label_el']) ?></div>
                    <div class="detail-value">
                        <?php
                        $val = $cfValues[$cf['id']] ?? '';
                        if ($cf['field_type'] === 'checkbox') echo $val ? '<i class="bi bi-check-square-fill text-success"></i>' : '<i class="bi bi-square text-muted"></i>';
                        elseif ($cf['field_type'] === 'date') echo e(formatDate($val));
                        else echo e($val ?: '—');
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Totals -->
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-calculator me-1 text-primary"></i><?= e(t('total_gross')) ?>
            </div>
            <div class="card-body">
                <div class="totals-box">
                    <div class="total-row">
                        <span><?= e(t('total_net')) ?></span>
                        <span class="fw-semibold"><?= formatMoney($offer['total_net']) ?></span>
                    </div>
                    <div class="total-row">
                        <span><?= e(t('total_vat')) ?></span>
                        <span class="fw-semibold"><?= formatMoney($offer['total_vat']) ?></span>
                    </div>
                    <div class="total-row grand-total">
                        <span><?= e(t('total_gross')) ?></span>
                        <span class="text-primary"><?= formatMoney($offer['total_gross']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items + Email log -->
    <div class="col-lg-8">
        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-list-ul me-1 text-primary"></i><?= e(t('offer_items')) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered offer-items-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('description')) ?></th>
                            <th class="text-center"><?= e(t('qty')) ?></th>
                            <th class="text-end"><?= e(t('unit_price')) ?></th>
                            <th class="text-center"><?= e(t('discount_pct')) ?></th>
                            <th class="text-center"><?= e(t('vat_rate')) ?> %</th>
                            <th class="text-end"><?= e(t('line_total')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3"><?= e(t('no_results')) ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?= e($item['description']) ?>
                            <?php if ($item['product_code']): ?>
                            <small class="text-muted d-block font-monospace"><?= e($item['product_code']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format($item['qty'], 3) ?></td>
                        <td class="text-end"><?= number_format($item['unit_price'], 2, ',', '.') ?> <?= e($currSym) ?></td>
                        <td class="text-center">
                            <?php if ($item['discount_pct'] > 0): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                -<?= number_format($item['discount_pct'], 2) ?>%
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format($item['vat_rate'], 0) ?>%</td>
                        <td class="text-end fw-semibold"><?= number_format($item['line_total'], 2, ',', '.') ?> <?= e($currSym) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Email Log -->
        <?php if (!empty($emails)): ?>
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-envelope-check me-1 text-primary"></i><?= e(t('email_log')) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-crm mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('email_to')) ?></th>
                            <th><?= e(t('email_subject')) ?></th>
                            <th><?= e(t('created_by')) ?></th>
                            <th><?= e(t('created_at')) ?></th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($emails as $em): ?>
                    <tr>
                        <td class="small"><?= e($em['sent_to']) ?></td>
                        <td class="small"><?= e($em['subject']) ?></td>
                        <td class="small"><?= e($em['sent_by_name']) ?></td>
                        <td class="small"><?= formatDateTime($em['sent_at']) ?></td>
                        <td class="text-center">
                            <?php if ($em['success']): ?>
                            <span class="badge bg-success"><i class="bi bi-check2"></i></span>
                            <?php else: ?>
                            <span class="badge bg-danger" title="<?= e($em['error_msg']) ?>"><i class="bi bi-x"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
