document.addEventListener("DOMContentLoaded", () => {
  const selectAll = document.getElementById("selectAllColis");
  const selectedCountEl = document.getElementById("selectedColisCount");
  const submitButton = document.querySelector('[data-bulk-action-button="true"]');
  const tableContainer = document.getElementById("retour_demande_colis_table");

  const getRowCheckboxes = () => {
    if (!tableContainer) {
      return [];
    }
    return Array.from(tableContainer.querySelectorAll(".colis-checkbox"));
  };

  const updateSelectedState = () => {
    const checkboxes = getRowCheckboxes();
    const checked = checkboxes.filter((cb) => cb.checked);
    const count = checked.length;

    if (selectedCountEl) {
      selectedCountEl.textContent = String(count);
    }
    if (submitButton) {
      submitButton.disabled = count === 0;
    }
    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    }
  };

  if (selectAll) {
    selectAll.addEventListener("change", () => {
      getRowCheckboxes().forEach((cb) => {
        cb.checked = selectAll.checked;
      });
      updateSelectedState();
    });
  }

  getRowCheckboxes().forEach((cb) => {
    cb.addEventListener("change", updateSelectedState);
  });

  updateSelectedState();
});
