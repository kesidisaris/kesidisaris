<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Stats
$stats = [];
$stats['contacts']  = (int)$db->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
$stats['companies'] = (int)$db->query('SELECT COUNT(*) FROM companies')->fetchColumn();
$stats['products']  = (int)$db->query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn();
$stats['offers']    = (int)$db->query('SELECT COUNT(*) FROM offers')->fetchColumn();

// Offer status breakdown
$offerStatuses = [];
$statusRows = $db->query("SELECT status, COUNT(*) AS cnt FROM offers GROUP BY status")->fetchAll();
foreach ($statusRows as $r) { $offerStatuses[$r['status']] = $r['cnt']; }

// Recent offers
$recentOffers = $db->query(
    "SELECT o.*, c.first_name, c.last_name, co.name AS company_name
     FROM offers o
     LEFT JOIN contacts c ON o.contact_id = c.id
     LEFT JOIN companies co ON o.company_id = co.id
     ORDER BY o.created_at DESC LIMIT 5"
)->fetchAll();

// Recent contacts
$recentContacts = $db->query(
    "SELECT ct.*, co.name AS company_name
     FROM contacts ct
     LEFT JOIN companies co ON ct.company_id = co.id
     ORDER BY ct.created_at DESC LIMIT 5"
)->fetchAll();

// Totals by status
$totalByStatus = [
    'draft'    => $offerStatuses['draft']    ?? 0,
    'sent'     => $offerStatuses['sent']     ?? 0,
    'accepted' => $offerStatuses['accepted'] ?? 0,
    'rejected' => $offerStatuses['rejected'] ?? 0,
];
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-speedometer2 me-2 text-primary"></i><?= e(t('dashboard')) ?></h1>
    <span class="text-muted small"><?= date('d/m/Y H:i') ?></span>
</div>

<!-- Main Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100" style="border-left: 4px solid #0d6efd;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-person-lines-fill"></i>
                </div>
                <div>
                    <div class="stat-number text-primary"><?= $stats['contacts'] ?></div>
                    <div class="stat-label"><?= e(t('total_contacts')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100" style="border-left: 4px solid #198754;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-building"></i>
                </div>
                <div>
                    <div class="stat-number text-success"><?= $stats['companies'] ?></div>
                    <div class="stat-label"><?= e(t('total_companies')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100" style="border-left: 4px solid #fd7e14;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div>
                    <div class="stat-number text-warning"><?= $stats['products'] ?></div>
                    <div class="stat-label"><?= e(t('total_products')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100" style="border-left: 4px solid #6f42c1;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-purple bg-opacity-10" style="background:rgba(111,66,193,0.1);color:#6f42c1;">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <div class="stat-number" style="color:#6f42c1;"><?= $stats['offers'] ?></div>
                    <div class="stat-label"><?= e(t('total_offers')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Offer Status Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card card-shadow text-center py-3">
            <div class="fw-bold fs-4 text-secondary"><?= $totalByStatus['draft'] ?></div>
            <div class="small text-muted"><?= e(t('offers_draft')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-shadow text-center py-3">
            <div class="fw-bold fs-4 text-primary"><?= $totalByStatus['sent'] ?></div>
            <div class="small text-muted"><?= e(t('offers_sent')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-shadow text-center py-3">
            <div class="fw-bold fs-4 text-success"><?= $totalByStatus['accepted'] ?></div>
            <div class="small text-muted"><?= e(t('offers_accepted')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-shadow text-center py-3">
            <div class="fw-bold fs-4 text-danger"><?= $totalByStatus['rejected'] ?></div>
            <div class="small text-muted"><?= e(t('offers_rejected')) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Offers -->
    <div class="col-lg-7">
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-file-earmark-text me-1 text-primary"></i><?= e(t('recent_offers')) ?></span>
                <a href="<?= APP_URL ?>/offers/index.php" class="btn btn-sm btn-outline-primary"><?= e(t('view')) ?> <?= e(t('all')) ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentOffers)): ?>
                <p class="text-muted p-3 mb-0"><?= e(t('no_results')) ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-crm mb-0">
                        <thead>
                            <tr>
                                <th><?= e(t('offer_number')) ?></th>
                                <th><?= e(t('offer_title')) ?></th>
                                <th><?= e(t('offer_status')) ?></th>
                                <th class="text-end"><?= e(t('total_gross')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentOffers as $o): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/offers/view.php?id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none">
                                    <?= e($o['offer_number']) ?>
                                </a>
                            </td>
                            <td class="text-truncate-custom"><?= e($o['title']) ?></td>
                            <td><?= offerStatusBadge($o['status']) ?></td>
                            <td class="text-end fw-semibold"><?= formatMoney($o['total_gross']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Contacts -->
    <div class="col-lg-5">
        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-person me-1 text-primary"></i><?= e(t('recent_contacts')) ?></span>
                <a href="<?= APP_URL ?>/contacts/index.php" class="btn btn-sm btn-outline-primary"><?= e(t('view')) ?> <?= e(t('all')) ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentContacts)): ?>
                <p class="text-muted p-3 mb-0"><?= e(t('no_results')) ?></p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                <?php foreach ($recentContacts as $c): ?>
                    <li class="list-group-item d-flex align-items-center gap-2 py-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:34px;height:34px;font-size:0.8rem;font-weight:700;">
                            <?= e(mb_strtoupper(mb_substr($c['first_name'], 0, 1) . mb_substr($c['last_name'], 0, 1))) ?>
                        </div>
                        <div class="overflow-hidden">
                            <a href="<?= APP_URL ?>/contacts/view.php?id=<?= $c['id'] ?>"
                               class="fw-semibold text-decoration-none text-dark d-block text-truncate">
                                <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                            </a>
                            <small class="text-muted"><?= e($c['company_name'] ?: t('no_company')) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
