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

  // Server-render submit (approach A): no AJAX interception.
})();

