<?php
require_once __DIR__ . '/../includes/header.php';

$db  = getDB();
$id  = intParam('id');
if (!$id) { redirectWithFlash(APP_URL . '/companies/index.php', 'error', t('error_not_found')); }

$stmt = $db->prepare(
    'SELECT co.*, u.name AS created_by_name FROM companies co
     LEFT JOIN users u ON co.created_by = u.id WHERE co.id = ?'
);
$stmt->execute([$id]);
$company = $stmt->fetch();
if (!$company) { redirectWithFlash(APP_URL . '/companies/index.php', 'error', t('error_not_found')); }

$pageTitle = $company['name'];

// Contacts for this company
$contacts = $db->prepare(
    'SELECT id, first_name, last_name, email, phone, position FROM contacts WHERE company_id = ? ORDER BY last_name ASC'
);
$contacts->execute([$id]);
$companyContacts = $contacts->fetchAll();

// Offers
$offers = $db->prepare(
    'SELECT o.id, o.offer_number, o.title, o.status, o.total_gross, o.created_at
     FROM offers o WHERE o.company_id = ? ORDER BY o.created_at DESC'
);
$offers->execute([$id]);
$companyOffers = $offers->fetchAll();

$cfFields = getCustomFields('companies');
$cfValues = getCustomFieldValues('companies', $id);
$langKey  = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'label_en' : 'label_el';
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-building me-2 text-primary"></i><?= e($company['name']) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/companies/edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i><?= e(t('edit')) ?>
        </a>
        <a href="<?= APP_URL ?>/companies/delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
           data-confirm="<?= e(t('confirm_delete')) ?>">
            <i class="bi bi-trash me-1"></i><?= e(t('delete')) ?>
        </a>
        <a href="<?= APP_URL ?>/companies/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Company Info -->
    <div class="col-lg-4">
        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-info-circle me-1 text-primary"></i><?= e(t('company_details')) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('company_name')) ?></div>
                        <div class="detail-value fw-bold"><?= e($company['name']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('vat_number')) ?></div>
                        <div class="detail-value"><?= e($company['vat_number'] ?: '—') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('phone')) ?></div>
                        <div class="detail-value">
                            <?= $company['phone'] ? '<a href="tel:' . e($company['phone']) . '">' . e($company['phone']) . '</a>' : '—' ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('email')) ?></div>
                        <div class="detail-value">
                            <?= $company['email'] ? '<a href="mailto:' . e($company['email']) . '">' . e($company['email']) . '</a>' : '—' ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('address')) ?></div>
                        <div class="detail-value"><?= e($company['address'] ?: '—') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('city')) ?></div>
                        <div class="detail-value"><?= e($company['city'] ?: '—') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label"><?= e(t('country')) ?></div>
                        <div class="detail-value"><?= e($company['country'] ?: '—') ?></div>
                    </div>
                    <?php if ($company['notes']): ?>
                    <div class="col-12">
                        <div class="detail-label"><?= e(t('notes')) ?></div>
                        <div class="detail-value small" style="white-space:pre-wrap;"><?= e($company['notes']) ?></div>
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
    </div>

    <div class="col-lg-8">
        <!-- Contacts -->
        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-people me-1 text-primary"></i><?= e(t('company_contacts')) ?></span>
                <a href="<?= APP_URL ?>/contacts/add.php?company_id=<?= $id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> <?= e(t('add_contact')) ?>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-crm mb-0">
                    <thead><tr>
                        <th><?= e(t('full_name')) ?></th>
                        <th><?= e(t('position')) ?></th>
                        <th><?= e(t('email')) ?></th>
                        <th><?= e(t('phone')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($companyContacts)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3"><?= e(t('no_results')) ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($companyContacts as $c): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/contacts/view.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a></td>
                        <td class="small text-muted"><?= e($c['position'] ?: '—') ?></td>
                        <td class="small"><?= e($c['email'] ?: '—') ?></td>
                        <td class="small"><?= e($c['phone'] ?: '—') ?></td>
                        <td><a href="<?= APP_URL ?>/contacts/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Offers -->
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-file-earmark-text me-1 text-primary"></i><?= e(t('offers')) ?></span>
                <a href="<?= APP_URL ?>/offers/add.php?company_id=<?= $id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> <?= e(t('add_offer')) ?>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-crm mb-0">
                    <thead><tr>
                        <th><?= e(t('offer_number')) ?></th>
                        <th><?= e(t('offer_title')) ?></th>
                        <th><?= e(t('offer_status')) ?></th>
                        <th class="text-end"><?= e(t('total_gross')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($companyOffers)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3"><?= e(t('no_results')) ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($companyOffers as $o): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/offers/view.php?id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none"><?= e($o['offer_number']) ?></a></td>
                        <td class="small"><?= e($o['title']) ?></td>
                        <td><?= offerStatusBadge($o['status']) ?></td>
                        <td class="text-end small"><?= formatMoney($o['total_gross']) ?></td>
                        <td><a href="<?= APP_URL ?>/offers/view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
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
