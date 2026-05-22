/* CRM App JavaScript - Offer Line Items + General helpers */

// ============================================================
// PRODUCTS DATA (populated from PHP via inline script)
// ============================================================
let crmProducts = window.crmProducts || {};

// ============================================================
// OFFER LINE ITEMS
// ============================================================
let lineRowIndex = 0;

/**
 * Add a new line item row to the offer items table
 */
function addOfferLine(data) {
    const tbody = document.getElementById('offerItemsBody');
    if (!tbody) return;

    const idx = lineRowIndex++;
    const d = data || {};
    const row = document.createElement('tr');
    row.dataset.lineIdx = idx;
    row.innerHTML = buildLineRowHTML(idx, d);
    tbody.appendChild(row);
    attachLineEvents(row);
    updateTotals();
}

function buildLineRowHTML(idx, d) {
    const products = window.crmProducts || [];
    let productOptions = '<option value="">--</option>';
    products.forEach(function(p) {
        const sel = (d.product_id && parseInt(d.product_id) === parseInt(p.id)) ? ' selected' : '';
        productOptions += `<option value="${p.id}" data-price="${p.price}" data-vat="${p.vat_rate}"${sel}>${escHtml(p.code ? p.code + ' - ' : '')}${escHtml(p.name)}</option>`;
    });

    return `
    <td style="min-width:160px;">
        <select class="form-select form-select-sm line-product" name="items[${idx}][product_id]">
            ${productOptions}
        </select>
    </td>
    <td style="min-width:220px;">
        <input type="text" class="form-control form-control-sm line-desc" name="items[${idx}][description]"
               value="${escHtml(d.description || '')}" required>
    </td>
    <td style="min-width:90px;">
        <input type="number" class="form-control form-control-sm line-qty" name="items[${idx}][qty]"
               value="${escHtml(d.qty || '1')}" step="0.001" min="0" required>
    </td>
    <td style="min-width:110px;">
        <input type="number" class="form-control form-control-sm line-price" name="items[${idx}][unit_price]"
               value="${escHtml(d.unit_price || '0.00')}" step="0.01" min="0" required>
    </td>
    <td style="min-width:90px;">
        <input type="number" class="form-control form-control-sm line-discount" name="items[${idx}][discount_pct]"
               value="${escHtml(d.discount_pct || '0')}" step="0.01" min="0" max="100">
    </td>
    <td style="min-width:80px;">
        <input type="number" class="form-control form-control-sm line-vat" name="items[${idx}][vat_rate]"
               value="${escHtml(d.vat_rate || '24')}" step="0.01" min="0">
    </td>
    <td style="min-width:100px;" class="text-end">
        <span class="line-total fw-semibold">0.00</span>
        <input type="hidden" class="line-total-input" name="items[${idx}][line_total]" value="0">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" onclick="removeLine(this)">
            <i class="bi bi-trash"></i>
        </button>
    </td>`;
}

function attachLineEvents(row) {
    const inputs = row.querySelectorAll('.line-qty, .line-price, .line-discount, .line-vat');
    inputs.forEach(function(inp) {
        inp.addEventListener('input', function() { calcLineTotal(row); });
    });

    const productSel = row.querySelector('.line-product');
    if (productSel) {
        productSel.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const price = opt.getAttribute('data-price') || '';
            const vat   = opt.getAttribute('data-vat')   || '24';
            const desc  = opt.text && this.value ? opt.text : '';

            const priceInput = row.querySelector('.line-price');
            const vatInput   = row.querySelector('.line-vat');
            const descInput  = row.querySelector('.line-desc');

            if (priceInput && price) priceInput.value = parseFloat(price).toFixed(2);
            if (vatInput)           vatInput.value   = parseFloat(vat).toFixed(2);
            if (descInput && desc && !descInput.value) {
                // Only set desc if it's empty
                const cleanDesc = desc.replace(/^\d+\s*-\s*/, '').trim();
                descInput.value = cleanDesc;
            }
            calcLineTotal(row);
        });
    }
}

function calcLineTotal(row) {
    const qty      = parseFloat(row.querySelector('.line-qty')?.value)      || 0;
    const price    = parseFloat(row.querySelector('.line-price')?.value)    || 0;
    const discount = parseFloat(row.querySelector('.line-discount')?.value) || 0;
    const net      = qty * price * (1 - discount / 100);
    const span     = row.querySelector('.line-total');
    const inp      = row.querySelector('.line-total-input');
    if (span) span.textContent = net.toFixed(2);
    if (inp)  inp.value        = net.toFixed(2);
    updateTotals();
}

function removeLine(btn) {
    const row = btn.closest('tr');
    if (row) { row.remove(); updateTotals(); }
}

function updateTotals() {
    let netTotal = 0;
    let vatTotal = 0;

    document.querySelectorAll('#offerItemsBody tr').forEach(function(row) {
        const qty      = parseFloat(row.querySelector('.line-qty')?.value)      || 0;
        const price    = parseFloat(row.querySelector('.line-price')?.value)    || 0;
        const discount = parseFloat(row.querySelector('.line-discount')?.value) || 0;
        const vatRate  = parseFloat(row.querySelector('.line-vat')?.value)      || 0;

        const lineNet = qty * price * (1 - discount / 100);
        const lineVat = lineNet * (vatRate / 100);
        netTotal += lineNet;
        vatTotal += lineVat;
    });

    const grossTotal = netTotal + vatTotal;

    setElText('totalNet',   netTotal.toFixed(2));
    setElText('totalVat',   vatTotal.toFixed(2));
    setElText('totalGross', grossTotal.toFixed(2));

    setElVal('totalNetInput',   netTotal.toFixed(2));
    setElVal('totalVatInput',   vatTotal.toFixed(2));
    setElVal('totalGrossInput', grossTotal.toFixed(2));
}

function setElText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function setElVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

// ============================================================
// PRODUCT SEARCH / TYPEAHEAD for offer lines
// ============================================================
// (Handled by native select for simplicity)

// ============================================================
// HELPERS
// ============================================================
function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// CONFIRM DELETE
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Initialize existing rows (edit mode)
    document.querySelectorAll('#offerItemsBody tr').forEach(function(row) {
        calcLineTotal(row);
        attachLineEvents(row);
    });

    updateTotals();
});
