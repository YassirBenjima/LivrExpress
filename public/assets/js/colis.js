(function () {
  const form = document.getElementById('bulk-request-pickup-form');
  const submitBtn = document.getElementById('bulk-request-pickup-btn');
  const hiddenInputsContainer = document.getElementById('bulk-request-pickup-hidden-inputs');

  if (!form || !submitBtn || !hiddenInputsContainer) {
    return;
  }

  const syncBulkSelection = () => {
    const checkboxes = Array.from(document.querySelectorAll('.pickup-row-checkbox'));
    const selected = checkboxes.filter((checkbox) => checkbox.checked);
    submitBtn.disabled = selected.length === 0;
    hiddenInputsContainer.innerHTML = selected
      .map((checkbox) => `<input type="hidden" name="colis_ids[]" value="${checkbox.value}">`)
      .join('');
  };

  /**
   * Metronic's DataTables component intercepts the header checkbox and updates the 
   * row checkboxes dynamically. Native events like 'change' might not be triggered 
   * on the individual rows, or our code might have conflicted with it. 
   * By combining delegated 'change' and 'click' events with a small delay, 
   * we can let Metronic finish its toggling before we perform our evaluation.
   */
  document.addEventListener('change', (event) => {
    if (event.target && event.target.type === 'checkbox') {
      setTimeout(syncBulkSelection, 50);
    }
  });

  document.addEventListener('click', (event) => {
    if (event.target && event.target.type === 'checkbox') {
      setTimeout(syncBulkSelection, 50);
    }
  });

  const deleteButtons = Array.from(document.querySelectorAll('[data-colis-delete-open="true"]'));
  const deleteForm = document.getElementById('colis-delete-form');
  const deleteTitle = document.querySelector('[data-colis-delete-title="true"]');
  const deleteToken = document.querySelector('[data-colis-delete-token="true"]');

  if (deleteButtons.length > 0 && deleteForm && deleteTitle && deleteToken) {
    deleteButtons.forEach((button) => {
      button.addEventListener('click', () => {
        deleteTitle.textContent = button.getAttribute('data-delete-title') || '';
        deleteToken.value = button.getAttribute('data-delete-token') || '';
        deleteForm.setAttribute('action', button.getAttribute('data-delete-action') || '');
      });
    });
  }

  // Initial sync in case some checkboxes are pre-checked (unlikely but safe)
  syncBulkSelection();
})();
