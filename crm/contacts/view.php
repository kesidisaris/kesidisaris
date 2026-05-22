<?php
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/contacts/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare(
    'SELECT ct.*, co.name AS company_name, co.id AS company_id_real,
            u.name AS created_by_name
     FROM contacts ct
     LEFT JOIN companies co ON ct.company_id = co.id
     LEFT JOIN users u ON ct.created_by = u.id
     WHERE ct.id = ?'
);
$stmt->execute([$id]);
$contact = $stmt->fetch();
if (!$contact) { redirectWithFlash(APP_URL . '/contacts/index.php', 'error', t('error_not_found')); }

$pageTitle = $contact['first_name'] . ' ' . $contact['last_name'];

// Offers for this contact
$offers = $db->prepare(
    'SELECT o.id, o.offer_number, o.title, o.status, o.total_gross, o.created_at
     FROM offers o WHERE o.contact_id = ? ORDER BY o.created_at DESC'
);
$offers->execute([$id]);
$contactOffers = $offers->fetchAll();

// Custom field values
$cfFields = getCustomFields('contacts');
$cfValues = getCustomFieldValues('contacts', $id);
$langKey  = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'label_en' : 'label_el';
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1>
        <i class="bi bi-person-circle me-2 text-primary"></i>
        <?= e($contact['first_name'] . ' ' . $contact['last_name']) ?>
    </h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/contacts/edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i><?= e(t('edit')) ?>
        </a>
        <a href="<?= APP_URL ?>/contacts/delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
           data-confirm="<?= e(t('confirm_delete')) ?>">
            <i class="bi bi-trash me-1"></i><?= e(t('delete')) ?>
        </a>
        <a href="<?= APP_URL ?>/contacts/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Contact Info -->
    <div class="col-lg-5">
        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-info-circle me-1 text-primary"></i><?= e(t('contact_details')) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('first_name')) ?></div>
                        <div class="detail-value"><?= e($contact['first_name']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('last_name')) ?></div>
                        <div class="detail-value"><?= e($contact['last_name']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('company')) ?></div>
                        <div class="detail-value">
                            <?php if ($contact['company_name']): ?>
                            <a href="<?= APP_URL ?>/companies/view.php?id=<?= $contact['company_id'] ?>">
                                <i class="bi bi-building me-1"></i><?= e($contact['company_name']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted"><?= e(t('no_company')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('email')) ?></div>
                        <div class="detail-value">
                            <?php if ($contact['email']): ?>
                            <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('phone')) ?></div>
                        <div class="detail-value">
                            <?php if ($contact['phone']): ?>
                            <a href="tel:<?= e($contact['phone']) ?>"><?= e($contact['phone']) ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('position')) ?></div>
                        <div class="detail-value"><?= e($contact['position'] ?: '—') ?></div>
                    </div>
                    <?php if ($contact['notes']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('notes')) ?></div>
                        <div class="detail-value" style="white-space:pre-wrap;"><?= e($contact['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('created_at')) ?></div>
                        <div class="detail-value small text-muted"><?= formatDateTime($contact['created_at']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('created_by')) ?></div>
                        <div class="detail-value small text-muted"><?= e($contact['created_by_name'] ?: '—') ?></div>
                    </div>
                </div>

                <?php if (!empty($cfFields)): ?>
                <hr>
                <div class="fw-semibold small text-muted mb-2"><?= e(t('custom_fields_info')) ?></div>
                <?php foreach ($cfFields as $cf): ?>
                <div class="mb-2">
                    <div class="detail-label"><?= e($cf[$langKey] ?: $cf['label_el']) ?></div>
                    <div class="detail-value">
                        <?php
                        $val = $cfValues[$cf['id']] ?? '';
                        if ($cf['field_type'] === 'checkbox') {
                            echo $val ? '<i class="bi bi-check-square-fill text-success"></i>' : '<i class="bi bi-square text-muted"></i>';
                        } elseif ($cf['field_type'] === 'date') {
                            echo e(formatDate($val));
                        } else {
                            echo e($val ?: '—');
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Offers for this contact -->
    <div class="col-lg-7">
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-file-earmark-text me-1 text-primary"></i><?= e(t('offers')) ?></span>
                <a href="<?= APP_URL ?>/offers/add.php?contact_id=<?= $id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> <?= e(t('add_offer')) ?>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-crm mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('offer_number')) ?></th>
                            <th><?= e(t('offer_title')) ?></th>
                            <th><?= e(t('offer_status')) ?></th>
                            <th class="text-end"><?= e(t('total_gross')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($contactOffers)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3"><?= e(t('no_results')) ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($contactOffers as $o): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/offers/view.php?id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none"><?= e($o['offer_number']) ?></a></td>
                        <td class="small"><?= e($o['title']) ?></td>
                        <td><?= offerStatusBadge($o['status']) ?></td>
                        <td class="text-end small"><?= formatMoney($o['total_gross']) ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/offers/view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
