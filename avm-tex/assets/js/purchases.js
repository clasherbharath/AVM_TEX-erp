(() => {
  const products = window.AVM_PURCHASE_PRODUCTS || [];
  const tbody = document.getElementById('purchaseItemsBody');
  const addBtn = document.getElementById('addPurchaseRow');
  const discountInput = document.getElementById('discount');
  const tpl = document.getElementById('purchaseRowTemplate');

  if (!tbody || !tpl) return;

  const money = (n) => '₹ ' + Number(n || 0).toFixed(2);

  function productOptions(selectedId = '') {
    let html = '<option value="">Select product</option>';
    products.forEach((p) => {
      const sel = String(p.id) === String(selectedId) ? ' selected' : '';
      html += `<option value="${p.id}" data-purchase-price="${p.purchase_price}" data-selling-price="${p.selling_price}" data-gst="${p.gst_percentage}" data-unit="${p.unit}"${sel}>${p.product_name} (${p.quantity} ${p.unit})</option>`;
    });
    return html;
  }

  function getNextRowIndex() {
    const rows = tbody.querySelectorAll('tr');
    let maxIdx = -1;
    rows.forEach((row) => {
      row.querySelectorAll('select, input').forEach((el) => {
        const match = el.name.match(/items\[(\d+)\]/);
        if (match && parseInt(match[1], 10) > maxIdx) maxIdx = parseInt(match[1], 10);
      });
    });
    return maxIdx + 1;
  }

  function bindRow(row) {
    const productSelect = row.querySelector('.purchase-product');
    const qtyInput = row.querySelector('.purchase-qty');
    const priceInput = row.querySelector('.purchase-price');
    const gstInput = row.querySelector('.purchase-gst');
    const sellingInput = row.querySelector('.purchase-selling');
    const removeBtn = row.querySelector('.remove-row');

    const syncFromProduct = () => {
      const opt = productSelect?.selectedOptions?.[0];
      if (!opt || !opt.value) return;
      priceInput.value = opt.dataset.purchasePrice || '0';
      gstInput.value = opt.dataset.gst || '0';
      sellingInput.value = opt.dataset.sellingPrice || '0';
      recalc();
    };

    productSelect?.addEventListener('change', syncFromProduct);
    [qtyInput, priceInput, gstInput].forEach((el) => el?.addEventListener('input', recalc));
    removeBtn?.addEventListener('click', () => {
      row.remove();
      if (tbody.querySelectorAll('tr').length === 0) addRow();
      recalc();
    });
  }

  function addRow(data = {}) {
    const rowIndex = getNextRowIndex();
    const clone = tpl.content.cloneNode(true);
    const row = clone.querySelector('tr');

    row.querySelectorAll('select, input').forEach((el) => {
      el.name = el.name.replace('ROW_INDEX', rowIndex);
    });

    row.querySelector('.purchase-product').innerHTML = productOptions(data.product_id || '');
    if (data.product_id) {
      row.querySelector('.purchase-qty').value = data.quantity || '1';
      row.querySelector('.purchase-price').value = data.purchase_price || '0';
      row.querySelector('.purchase-gst').value = data.gst_percentage || '0';
      row.querySelector('.purchase-selling').value = data.selling_price_snapshot || '0';
    }

    tbody.appendChild(row);
    bindRow(row);
    recalc();
  }

  function recalc() {
    let subtotal = 0;
    let gstTotal = 0;
    let grossMargin = 0;

    tbody.querySelectorAll('tr').forEach((row) => {
      const qty = parseFloat(row.querySelector('.purchase-qty')?.value || '0');
      const price = parseFloat(row.querySelector('.purchase-price')?.value || '0');
      const gst = parseFloat(row.querySelector('.purchase-gst')?.value || '0');
      const sell = parseFloat(row.querySelector('.purchase-selling')?.value || '0');

      const lineSub = qty * price;
      const lineGst = lineSub * (gst / 100);
      const lineTotal = lineSub + lineGst;
      const lineMargin = (sell - price) * qty;

      row.querySelector('.line-subtotal').textContent = money(lineSub);
      row.querySelector('.line-total').textContent = money(lineTotal);
      row.querySelector('.line-margin').textContent = money(lineMargin);

      subtotal += lineSub;
      gstTotal += lineGst;
      grossMargin += lineMargin;
    });

    const discount = parseFloat(discountInput?.value || '0');
    const grand = Math.max(0, subtotal + gstTotal - discount);

    const elSub = document.getElementById('summarySubtotal');
    const elGst = document.getElementById('summaryGst');
    const elDisc = document.getElementById('summaryDiscount');
    const elGrand = document.getElementById('summaryGrand');
    const elMargin = document.getElementById('summaryMargin');

    if (elSub) elSub.textContent = money(subtotal);
    if (elGst) elGst.textContent = money(gstTotal);
    if (elDisc) elDisc.textContent = money(discount);
    if (elGrand) elGrand.textContent = money(grand);
    if (elMargin) elMargin.textContent = money(grossMargin);
  }

  addBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    addRow();
  });
  discountInput?.addEventListener('input', recalc);

  const oldRows = window.AVM_PURCHASE_OLD_ITEMS || [];
  if (oldRows.length) {
    oldRows.forEach((r) => addRow(r));
  } else {
    addRow();
  }
})();
