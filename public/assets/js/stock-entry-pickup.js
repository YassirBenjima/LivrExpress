(function () {
  const bulkBtn = document.getElementById('stock-entry-bulk-pickup-btn');
  const hiddenInputs = document.getElementById('stock-entry-pickup-hidden-inputs');
  const modalProducts = document.getElementById('stock-entry-pickup-products');

  if (!bulkBtn || !hiddenInputs) return;

  const syncSelection = () => {
    const checkboxes = Array.from(document.querySelectorAll('.stock-entry-row-checkbox'));
    const selected = checkboxes.filter((c) => c.checked).map((c) => String(c.value)).filter(Boolean);

    bulkBtn.disabled = selected.length === 0;

    hiddenInputs.innerHTML = selected
      .map((id) => `<input type="hidden" name="movementIds[]" value="${id}">`)
      .join('');

    return selected;
  };

  // Same tactic as colis.js (Metronic toggles can be async-ish)
  document.addEventListener('change', (event) => {
    if (event.target && event.target.type === 'checkbox') {
      setTimeout(syncSelection, 50);
    }
  });
  document.addEventListener('click', (event) => {
    if (event.target && event.target.type === 'checkbox') {
      setTimeout(syncSelection, 50);
    }
  });

  const prefillModal = async () => {
    const selected = syncSelection();
    if (!selected || selected.length === 0) return;

    if (modalProducts) modalProducts.value = 'Chargement...';

    try {
      const url = `/stock/entree/pickup-request/modal-data?ids=${encodeURIComponent(selected.join(','))}`;
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!res.ok || !data) {
        throw new Error((data && data.error) ? data.error : 'Erreur lors du chargement.');
      }
      if (modalProducts) {
        // Convert multiline summary into a single line for input display
        modalProducts.value = String(data.summary || '').replace(/\s*\n\s*/g, ' | ').trim();
      }
    } catch (e) {
      if (modalProducts) {
        modalProducts.value = '';
      }
      console.error(e);
    }
  };

  // Capture phase so it runs before KT modal toggle handlers.
  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest('[data-stock-entry-pickup-open="true"]');
    if (!trigger) return;
    prefillModal();
  }, true);

  syncSelection();
})();

