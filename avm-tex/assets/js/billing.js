(() => {
  const products = window.AVM_INVENTORY_PRODUCTS || [];
  const tbody = document.getElementById('invoiceItemsBody');
  const addBtn = document.getElementById('addInvoiceRow');
  const discountInput = document.getElementById('discount');
  const tpl = document.getElementById('invoiceRowTemplate');

  if (!tbody || !tpl) return;

  const money = (n) => '₹ ' + Number(n || 0).toFixed(2);

  function productOptions(selectedId = '') {
    let html = '<option value="">Select product</option>';
    products.forEach((p) => {
      const sel = String(p.id) === String(selectedId) ? ' selected' : '';
      html += `<option value="${p.id}" data-price="${p.selling_price}" data-gst="${p.gst_percentage}" data-stock="${p.quantity}" data-unit="${p.unit}"${sel}>${p.product_name} (Stock: ${p.quantity} ${p.unit})</option>`;
    });
    return html;
  }

  function getNextRowIndex() {
    const rows = tbody.querySelectorAll('tr');
    let maxIdx = -1;
    rows.forEach((row) => {
      const selects = row.querySelectorAll('select, input');
      selects.forEach((el) => {
        const match = el.name.match(/items\[(\d+)\]/);
        if (match && parseInt(match[1]) > maxIdx) {
          maxIdx = parseInt(match[1]);
        }
      });
    });
    return maxIdx + 1;
  }

  function bindRow(row) {
    const productSelect = row.querySelector('.item-product');
    const qtyInput = row.querySelector('.item-qty');
    const priceInput = row.querySelector('.item-price');
    const gstInput = row.querySelector('.item-gst');
    const removeBtn = row.querySelector('.remove-row');

    const syncFromProduct = () => {
      const opt = productSelect.selectedOptions[0];
      if (!opt || !opt.value) return;
      priceInput.value = opt.dataset.price || '0';
      gstInput.value = opt.dataset.gst || '0';
      row.dataset.stock = opt.dataset.stock || '0';
      recalc();
    };

    productSelect.addEventListener('change', syncFromProduct);
    [qtyInput, priceInput, gstInput].forEach((el) => el.addEventListener('input', recalc));
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
    
    // Replace ROW_INDEX placeholder with actual index
    const inputs = row.querySelectorAll('select, input[type="number"]');
    inputs.forEach((el) => {
      el.name = el.name.replace('ROW_INDEX', rowIndex);
    });
    
    row.querySelector('.item-product').innerHTML = productOptions(data.product_id || '');
    if (data.product_id) {
      row.querySelector('.item-qty').value = data.quantity || '1';
      row.querySelector('.item-price').value = data.price || '0';
      row.querySelector('.item-gst').value = data.gst_percentage || '0';
    }
    tbody.appendChild(row);
    bindRow(row);
    recalc();
  }

  function recalc() {
    let subtotal = 0;
    let gstTotal = 0;

    tbody.querySelectorAll('tr').forEach((row) => {
      const qty = parseFloat(row.querySelector('.item-qty')?.value || '0');
      const price = parseFloat(row.querySelector('.item-price')?.value || '0');
      const gst = parseFloat(row.querySelector('.item-gst')?.value || '0');
      const stock = parseFloat(row.dataset.stock || row.querySelector('.item-product')?.selectedOptions[0]?.dataset.stock || '0');

      const lineSub = qty * price;
      const lineGst = lineSub * (gst / 100);
      const lineTotal = lineSub + lineGst;

      row.querySelector('.line-subtotal').textContent = money(lineSub);
      row.querySelector('.line-total').textContent = money(lineTotal);

      const stockWarn = row.querySelector('.stock-warn');
      if (stockWarn) {
        stockWarn.classList.toggle('d-none', !(qty > stock && qty > 0));
      }

      subtotal += lineSub;
      gstTotal += lineGst;
    });

    const discount = parseFloat(discountInput?.value || '0');
    const grand = Math.max(0, subtotal + gstTotal - discount);

    const elSub = document.getElementById('summarySubtotal');
    const elGst = document.getElementById('summaryGst');
    const elDisc = document.getElementById('summaryDiscount');
    const elGrand = document.getElementById('summaryGrand');

    if (elSub) elSub.textContent = money(subtotal);
    if (elGst) elGst.textContent = money(gstTotal);
    if (elDisc) elDisc.textContent = money(discount);
    if (elGrand) elGrand.textContent = money(grand);
  }

  addBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    addRow();
  });
  discountInput?.addEventListener('input', recalc);

  const oldRows = window.AVM_INVOICE_OLD_ITEMS || [];
  if (oldRows.length) {
    oldRows.forEach((r) => addRow(r));
  } else {
    addRow();
  }
})();
