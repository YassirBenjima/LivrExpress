(() => {
  const form = document.getElementById('product-new-form');
  const toggle = document.getElementById('variants-toggle');
  const barcodeRow = document.getElementById('barcode-row');
  const quantityRow = document.getElementById('quantity-row');
  const variantsSection = document.getElementById('variants-section');
  const variantsEmpty = document.getElementById('variants-empty');
  const variantsTbody = document.getElementById('variants-tbody');
  const addVariantBtn = document.getElementById('add-variant-btn');

  if (
    !form ||
    !toggle ||
    !barcodeRow ||
    !quantityRow ||
    !variantsSection ||
    !variantsEmpty ||
    !variantsTbody ||
    !addVariantBtn
  ) {
    return;
  }

  const renumberVariantRows = () => {
    const rows = Array.from(variantsTbody.querySelectorAll('tr.variant-row'));
    rows.forEach((row, idx) => {
      const barcode = row.querySelector('input[name*="[barcode]"]');
      const name = row.querySelector('input[name*="[name]"]');
      const qty = row.querySelector('input[name*="[quantity]"]');
      if (barcode) barcode.name = `variants[${idx}][barcode]`;
      if (name) name.name = `variants[${idx}][name]`;
      if (qty) qty.name = `variants[${idx}][quantity]`;
    });
  };

  const attachNumericOnly = (input) => {
    if (!input) return;
    input.addEventListener('input', () => {
      input.value = input.value.replace(/[^0-9]/g, '');
    });
  };

  const addVariantRow = () => {
    const idx = variantsTbody.querySelectorAll('tr.variant-row').length;
    const tr = document.createElement('tr');
    tr.className = 'variant-row';
    tr.innerHTML = `
      <td class="py-2">
        <input class="kt-input h-8 text-sm w-full" name="variants[${idx}][barcode]" placeholder="Code barre" type="text"/>
      </td>
      <td class="py-2">
        <input class="kt-input h-8 text-sm w-full" name="variants[${idx}][name]" placeholder="Nom de la variante" type="text"/>
      </td>
      <td class="py-2">
        <input class="kt-input h-8 text-sm w-full" inputmode="numeric" name="variants[${idx}][quantity]" pattern="[0-9]*" placeholder="0" type="text"/>
      </td>
      <td class="py-2 text-end">
        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost kt-btn-destructive" data-variant-remove type="button" title="Supprimer">
          <i class="ki-filled ki-trash"></i>
        </button>
      </td>
    `;

    attachNumericOnly(tr.querySelector('input[name*="[quantity]"]'));
    variantsTbody.appendChild(tr);
  };

  const sync = () => {
    const variantsEnabled = !!toggle.checked;
    barcodeRow.style.display = variantsEnabled ? 'none' : '';
    quantityRow.style.display = variantsEnabled ? 'none' : '';
    variantsSection.style.display = variantsEnabled ? '' : 'none';
    variantsEmpty.style.display = variantsEnabled ? 'none' : '';

    const mainBarcode = form.querySelector('input[name="barcode"]');
    const mainQty = form.querySelector('input[name="quantity"]');
    if (mainBarcode) mainBarcode.required = !variantsEnabled;
    if (mainQty) mainQty.required = !variantsEnabled;
  };

  // existing first row numeric guard (in case oninput removed later)
  attachNumericOnly(variantsTbody.querySelector('tr.variant-row input[name*="[quantity]"]'));

  addVariantBtn.addEventListener('click', addVariantRow);

  variantsTbody.addEventListener('click', (e) => {
    const btn = e.target?.closest?.('[data-variant-remove]');
    if (!btn) return;

    const row = btn.closest('tr.variant-row');
    if (!row) return;

    const rows = Array.from(variantsTbody.querySelectorAll('tr.variant-row'));
    const rowIndex = rows.indexOf(row);
    if (rowIndex === 0) return; // First row cannot be removed

    row.remove();
    renumberVariantRows();
  });

  toggle.addEventListener('change', sync);
  sync();
})();

