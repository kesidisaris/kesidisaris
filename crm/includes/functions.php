<?php
// Helper functions: custom fields, formatting, pagination, etc.

require_once __DIR__ . '/db.php';

/**
 * Load language array based on session/preference
 */
function getLang(): array {
    static $lang = null;
    if ($lang !== null) return $lang;
    $code = $_SESSION['user_lang'] ?? DEFAULT_LANG;
    $file = __DIR__ . '/../lang/' . $code . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../lang/el.php';
    }
    $lang = require $file;
    return $lang;
}

/**
 * Translate a key
 */
function t(string $key, string $fallback = ''): string {
    $lang = getLang();
    return $lang[$key] ?? ($fallback ?: $key);
}

/**
 * Escape HTML output
 */
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format currency
 */
function formatMoney(mixed $amount): string {
    return number_format((float)$amount, 2, ',', '.') . ' ' . getSetting('currency_symbol', '€');
}

/**
 * Format date for display
 */
function formatDate(?string $date): string {
    if (!$date || $date === '0000-00-00') return '';
    try {
        $d = new DateTime($date);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format datetime for display
 */
function formatDateTime(?string $dt): string {
    if (!$dt) return '';
    try {
        $d = new DateTime($dt);
        return $d->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $dt;
    }
}

/**
 * Render badge for offer status
 */
function offerStatusBadge(string $status): string {
    $map = [
        'draft'    => ['secondary', t('status_draft')],
        'sent'     => ['primary',   t('status_sent')],
        'accepted' => ['success',   t('status_accepted')],
        'rejected' => ['danger',    t('status_rejected')],
    ];
    [$cls, $label] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $cls . '">' . e($label) . '</span>';
}

/**
 * Paginate a query result
 * Returns ['data'=>[], 'total'=>int, 'pages'=>int, 'current'=>int, 'per_page'=>int]
 */
function paginate(string $countSql, string $dataSql, array $params, int $page = 1, int $perPage = 20): array {
    $db = getDB();
    $page    = max(1, $page);
    $perPage = max(1, $perPage);

    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $pages  = $total > 0 ? (int)ceil($total / $perPage) : 1;
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmtData = $db->prepare($dataSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $stmtData->execute($params);
    $data = $stmtData->fetchAll();

    return [
        'data'     => $data,
        'total'    => $total,
        'pages'    => $pages,
        'current'  => $page,
        'per_page' => $perPage,
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination(array $pager, string $baseUrl): string {
    if ($pager['pages'] <= 1) return '';
    $html  = '<nav aria-label="pagination"><ul class="pagination pagination-sm justify-content-center mb-0">';
    $cur   = $pager['current'];
    $pages = $pager['pages'];
    $sep   = strpos($baseUrl, '?') !== false ? '&' : '?';

    $html .= '<li class="page-item' . ($cur <= 1 ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($cur - 1) . '">&laquo;</a></li>';

    for ($i = max(1, $cur - 3); $i <= min($pages, $cur + 3); $i++) {
        $html .= '<li class="page-item' . ($i === $cur ? ' active' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($cur >= $pages ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($cur + 1) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get all custom fields for a module
 */
function getCustomFields(string $module): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM custom_fields WHERE module = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$module]);
    return $stmt->fetchAll();
}

/**
 * Get custom field values for a record
 * Returns [field_id => value]
 */
function getCustomFieldValues(string $module, int $recordId): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT field_id, value FROM custom_field_values WHERE module = ? AND record_id = ?');
    $stmt->execute([$module, $recordId]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['field_id']] = $row['value'];
    }
    return $result;
}

/**
 * Render custom field form inputs for a module
 */
function renderCustomFieldForm(string $module, int $recordId = 0): string {
    $fields = getCustomFields($module);
    if (empty($fields)) return '';

    $values  = $recordId > 0 ? getCustomFieldValues($module, $recordId) : [];
    $lang    = getLang();
    $langKey = ($_SESSION['user_lang'] ?? DEFAULT_LANG) === 'en' ? 'label_en' : 'label_el';

    $html = '<hr><h6 class="text-muted mb-3"><i class="bi bi-sliders"></i> ' . e(t('custom_fields_info')) . '</h6>';

    foreach ($fields as $field) {
        $fieldId  = $field['id'];
        $label    = $field[$langKey] ?: $field['label_el'];
        $value    = $values[$fieldId] ?? '';
        $required = $field['is_required'] ? 'required' : '';
        $name     = 'cf_' . $fieldId;
        $reqStar  = $field['is_required'] ? ' <span class="text-danger">*</span>' : '';

        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">' . e($label) . $reqStar . '</label>';

        switch ($field['field_type']) {
            case 'text':
                $html .= '<input type="text" class="form-control" name="' . $name . '" value="' . e($value) . '" ' . $required . '>';
                break;
            case 'number':
                $html .= '<input type="number" step="any" class="form-control" name="' . $name . '" value="' . e($value) . '" ' . $required . '>';
                break;
            case 'date':
                $html .= '<input type="date" class="form-control" name="' . $name . '" value="' . e($value) . '" ' . $required . '>';
                break;
            case 'select':
                $options = [];
                if ($field['options_json']) {
                    $options = json_decode($field['options_json'], true) ?: [];
                }
                $html .= '<select class="form-select" name="' . $name . '" ' . $required . '>';
                $html .= '<option value="">' . e(t('select_option')) . '</option>';
                foreach ($options as $opt) {
                    $sel   = ($value === $opt) ? ' selected' : '';
                    $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
                }
                $html .= '</select>';
                break;
            case 'checkbox':
                $checked = $value ? ' checked' : '';
                $html   .= '<div class="form-check">';
                $html   .= '<input type="checkbox" class="form-check-input" name="' . $name . '" value="1"' . $checked . '>';
                $html   .= '<input type="hidden" name="' . $name . '_exists" value="1">';
                $html   .= '<label class="form-check-label">' . e($label) . '</label>';
                $html   .= '</div>';
                break;
            case 'memo':
                $html .= '<textarea class="form-control" name="' . $name . '" rows="3" ' . $required . '>' . e($value) . '</textarea>';
                break;
        }
        $html .= '</div>';
    }
    return $html;
}

/**
 * Save custom field values after saving a record
 */
function saveCustomFieldValues(string $module, int $recordId, array $postData): void {
    $fields = getCustomFields($module);
    if (empty($fields)) return;

    $db = getDB();
    $upsert = $db->prepare(
        'INSERT INTO custom_field_values (module, record_id, field_id, value) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($fields as $field) {
        $name = 'cf_' . $field['id'];
        if ($field['field_type'] === 'checkbox') {
            // Only process if the hidden _exists field is present (form was submitted)
            if (!isset($postData[$name . '_exists'])) continue;
            $val = isset($postData[$name]) ? '1' : '0';
        } else {
            if (!array_key_exists($name, $postData)) continue;
            $val = trim((string)$postData[$name]);
        }
        $upsert->execute([$module, $recordId, $field['id'], $val]);
    }
}

/**
 * Generate next offer number: OFF-YYYY-NNN
 */
function generateOfferNumber(): string {
    $db   = getDB();
    $year = date('Y');
    $stmt = $db->prepare(
        "SELECT offer_number FROM offers WHERE offer_number LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(['OFF-' . $year . '-%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $parts = explode('-', $last);
        $seq   = (int)($parts[2] ?? 0) + 1;
    } else {
        $seq = 1;
    }
    return 'OFF-' . $year . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

/**
 * Get product list for select dropdowns
 */
function getProductsForSelect(): array {
    $db   = getDB();
    $lang = ($_SESSION['user_lang'] ?? DEFAULT_LANG);
    $col  = $lang === 'en' ? 'name_en' : 'name_el';
    $stmt = $db->prepare("SELECT id, code, $col AS name, price, unit, vat_rate FROM products WHERE active = 1 ORDER BY $col");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Sanitize and get integer from request
 */
function intParam(string $key, int $default = 0): int {
    $val = $_GET[$key] ?? $_POST[$key] ?? $default;
    return (int)$val;
}

/**
 * Get string param safely
 */
function strParam(string $key, string $source = 'get', string $default = ''): string {
    $arr = $source === 'post' ? $_POST : $_GET;
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

/**
 * Redirect with flash message
 */
function redirectWithFlash(string $url, string $type, string $message): never {
    setFlash($type, $message);
    header('Location: ' . $url);
    exit;
}
