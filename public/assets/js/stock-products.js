(function () {
  const setValue = (selector, value) => {
    const el = document.querySelector(selector);
    if (!el) return;
    el.value = value ?? '';
  };

  const setAttr = (selector, attr, value) => {
    const el = document.querySelector(selector);
    if (!el) return;
    if (value === null || value === undefined || value === '') {
      el.removeAttribute(attr);
      return;
    }
    el.setAttribute(attr, value);
  };

  // Capture phase so it runs before KT modal toggle handlers.
  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest('[data-pickup-request-open="true"]');
    if (!trigger) return;

    setValue('#pickup_request_product_id', trigger.getAttribute('data-product-id'));
    setValue('#pickup_request_product', trigger.getAttribute('data-product-name'));
    setValue('#pickup_request_token', trigger.getAttribute('data-pickup-csrf'));
    setAttr('#pickup-request-form', 'action', trigger.getAttribute('data-pickup-action'));
  }, true);

  const deleteButtons = Array.from(document.querySelectorAll('[data-stock-product-delete-open="true"]'));
  const deleteForm = document.getElementById('stock-product-delete-form');
  const deleteTitle = document.querySelector('[data-stock-product-delete-title="true"]');
  const deleteToken = document.querySelector('[data-stock-product-delete-token="true"]');

  if (deleteButtons.length > 0 && deleteForm && deleteTitle && deleteToken) {
    deleteButtons.forEach((button) => {
      button.addEventListener('click', () => {
        deleteTitle.textContent = button.getAttribute('data-delete-title') || '';
        deleteToken.value = button.getAttribute('data-delete-token') || '';
        deleteForm.setAttribute('action', button.getAttribute('data-delete-action') || '');
      });
    });
  }

  // Server-render submit (approach A): no AJAX interception.
})();

