<?php
$pageTitle = 'Add Offer';
require_once __DIR__ . '/../includes/header.php';

$db       = getDB();
$errors   = [];
$contacts  = $db->query('SELECT id, first_name, last_name FROM contacts ORDER BY last_name, first_name')->fetchAll();
$companies = $db->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
$products  = getProductsForSelect();

$preContact = intParam('contact_id');
$preCompany = intParam('company_id');

$values = [
    'offer_number' => generateOfferNumber(),
    'title'        => '',
    'contact_id'   => $preContact,
    'company_id'   => $preCompany,
    'status'       => 'draft',
    'valid_until'  => date('Y-m-d', strtotime('+30 days')),
    'notes'        => '',
    'total_net'    => 0,
    'total_vat'    => 0,
    'total_gross'  => 0,
];
$items = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = [
        'offer_number' => trim($_POST['offer_number'] ?? ''),
        'title'        => trim($_POST['title']        ?? ''),
        'contact_id'   => intParam('contact_id', 0),
        'company_id'   => intParam('company_id', 0),
        'status'       => $_POST['status']  ?? 'draft',
        'valid_until'  => trim($_POST['valid_until'] ?? ''),
        'notes'        => trim($_POST['notes']       ?? ''),
        'total_net'    => (float)str_replace(',', '.', $_POST['total_net_input']   ?? 0),
        'total_vat'    => (float)str_replace(',', '.', $_POST['total_vat_input']   ?? 0),
        'total_gross'  => (float)str_replace(',', '.', $_POST['total_gross_input'] ?? 0),
    ];

    if (!in_array($values['status'], ['draft','sent','accepted','rejected'])) $values['status'] = 'draft';

    if ($values['title'] === '') $errors[] = t('offer_title') . ': ' . t('required_field');
    if ($values['offer_number'] === '') $errors[] = t('offer_number') . ': ' . t('required_field');

    // Parse line items
    $rawItems = $_POST['items'] ?? [];
    $items    = [];
    foreach ($rawItems as $idx => $item) {
        $desc = trim($item['description'] ?? '');
        if ($desc === '') continue;
        $qty        = (float)str_replace(',', '.', $item['qty']        ?? 1);
        $unitPrice  = (float)str_replace(',', '.', $item['unit_price'] ?? 0);
        $discount   = (float)str_replace(',', '.', $item['discount_pct'] ?? 0);
        $vatRate    = (float)str_replace(',', '.', $item['vat_rate']   ?? 24);
        $lineTotal  = $qty * $unitPrice * (1 - $discount / 100);
        $items[]    = [
            'product_id'   => intParam('product_id', 0) ?: null, // handled below
            'product_id_v' => (int)($item['product_id'] ?? 0),
            'description'  => $desc,
            'qty'          => $qty,
            'unit_price'   => $unitPrice,
            'discount_pct' => $discount,
            'vat_rate'     => $vatRate,
            'line_total'   => $lineTotal,
            'sort_order'   => (int)$idx,
        ];
    }

    if (empty($errors)) {
        // Recalculate totals server-side
        $totalNet = 0;
        $totalVat = 0;
        foreach ($items as $item) {
            $totalNet += $item['line_total'];
            $totalVat += $item['line_total'] * ($item['vat_rate'] / 100);
        }
        $totalGross = $totalNet + $totalVat;

        $stmt = $db->prepare(
            'INSERT INTO offers (offer_number, contact_id, company_id, title, status, valid_until, notes,
             total_net, total_vat, total_gross, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $values['offer_number'],
            $values['contact_id'] ?: null,
            $values['company_id'] ?: null,
            $values['title'],
            $values['status'],
            $values['valid_until'] ?: null,
            $values['notes'] ?: null,
            round($totalNet, 2), round($totalVat, 2), round($totalGross, 2),
            $_SESSION['user_id'],
        ]);
        $offerId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare(
            'INSERT INTO offer_items (offer_id, product_id, description, qty, unit_price, discount_pct, vat_rate, line_total, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                $offerId, $item['product_id_v'] ?: null, $item['description'],
                $item['qty'], $item['unit_price'], $item['discount_pct'],
                $item['vat_rate'], $item['line_total'], $item['sort_order'],
            ]);
        }

        saveCustomFieldValues('offers', $offerId, $_POST);
        redirectWithFlash(APP_URL . '/offers/view.php?id=' . $offerId, 'success', t('success_add'));
    }
}
?>

<?= renderFlash() ?>

<div class="page-header">
    <h1><i class="bi bi-file-earmark-plus me-2 text-primary"></i><?= e(t('add_offer')) ?></h1>
    <a href="<?= APP_URL ?>/offers/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(t('back')) ?>
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Products data for JS -->
<script>
window.crmProducts = <?= json_encode(array_map(function($p) {
    return ['id'=>$p['id'], 'code'=>$p['code'], 'name'=>$p['name'], 'price'=>$p['price'], 'unit'=>$p['unit'], 'vat_rate'=>$p['vat_rate']];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
</script>

<form method="post" action="" id="offerForm">
    <?= csrfField() ?>
    <div class="row g-3">
        <!-- Left: Offer details -->
        <div class="col-lg-8">
            <div class="form-section mb-3">
                <div class="form-section-title"><?= e(t('offer_details')) ?></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= e(t('offer_number')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" name="offer_number"
                               value="<?= e($values['offer_number']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= e(t('offer_status')) ?></label>
                        <select class="form-select" name="status">
                            <option value="draft"    <?= $values['status']==='draft'    ? 'selected':'' ?>><?= e(t('status_draft')) ?></option>
                            <option value="sent"     <?= $values['status']==='sent'     ? 'selected':'' ?>><?= e(t('status_sent')) ?></option>
                            <option value="accepted" <?= $values['status']==='accepted' ? 'selected':'' ?>><?= e(t('status_accepted')) ?></option>
                            <option value="rejected" <?= $values['status']==='rejected' ? 'selected':'' ?>><?= e(t('status_rejected')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= e(t('valid_until')) ?></label>
                        <input type="date" class="form-control" name="valid_until" value="<?= e($values['valid_until']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('offer_title')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" value="<?= e($values['title']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('contact')) ?></label>
                        <select class="form-select" name="contact_id">
                            <option value=""><?= e(t('select_option')) ?></option>
                            <?php foreach ($contacts as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$values['contact_id']===(int)$c['id'] ? 'selected':'' ?>>
                                <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('company')) ?></label>
                        <select class="form-select" name="company_id">
                            <option value=""><?= e(t('select_option')) ?></option>
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$values['company_id']===(int)$c['id'] ? 'selected':'' ?>>
                                <?= e($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('notes')) ?></label>
                        <textarea class="form-control" name="notes" rows="2"><?= e($values['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="form-section">
                <div class="form-section-title d-flex justify-content-between align-items-center">
                    <span><?= e(t('offer_items')) ?></span>
                    <button type="button" class="btn btn-sm btn-success" onclick="addOfferLine()">
                        <i class="bi bi-plus-circle me-1"></i><?= e(t('add_line')) ?>
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered offer-items-table mb-2">
                        <thead class="table-light">
                            <tr>
                                <th><?= e(t('product')) ?></th>
                                <th><?= e(t('description')) ?></th>
                                <th><?= e(t('qty')) ?></th>
                                <th><?= e(t('unit_price')) ?></th>
                                <th><?= e(t('discount_pct')) ?></th>
                                <th><?= e(t('vat_rate')) ?> %</th>
                                <th class="text-end"><?= e(t('line_total')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="offerItemsBody">
                        <?php foreach ($items as $idx => $item): ?>
                        <!-- Re-rendered items on validation fail -->
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="addOfferLine()">
                    <i class="bi bi-plus me-1"></i><?= e(t('add_line')) ?>
                </button>
            </div>

            <?= renderCustomFieldForm('offers') ?>
        </div>

        <!-- Right: Totals -->
        <div class="col-lg-4">
            <div class="card card-shadow sticky-lg-top" style="top:70px;">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><?= e(t('total_net')) ?> / <?= e(t('total_gross')) ?></h6>
                    <div class="totals-box">
                        <div class="total-row">
                            <span><?= e(t('total_net')) ?></span>
                            <span id="totalNet">0.00</span>
                        </div>
                        <div class="total-row">
                            <span><?= e(t('total_vat')) ?></span>
                            <span id="totalVat">0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span><?= e(t('total_gross')) ?></span>
                            <span id="totalGross">0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="total_net_input"   id="totalNetInput"   value="0">
                    <input type="hidden" name="total_vat_input"   id="totalVatInput"   value="0">
                    <input type="hidden" name="total_gross_input" id="totalGrossInput" value="0">

                    <hr>
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle me-1"></i><?= e(t('save')) ?>
                    </button>
                    <a href="<?= APP_URL ?>/offers/index.php" class="btn btn-outline-secondary w-100">
                        <?= e(t('cancel')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add one empty line on new offer
    if (document.querySelectorAll('#offerItemsBody tr').length === 0) {
        addOfferLine();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
