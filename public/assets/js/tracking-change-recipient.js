(function () {
    const tableContainer = document.getElementById(
        "tracking_change_recipient_table",
    );
    const selectAll = document.getElementById("selectAllColis");
    const countEl = document.getElementById("selectedCount");
    const bulkButtons = Array.from(
        document.querySelectorAll('[data-bulk-action-button="true"]'),
    );
    const bulkRecipientCitySelect =
        document.getElementById("bulkRecipientCity");
    const bulkRecipientForm = document.getElementById("bulkRecipientForm");
    const recipientInput = bulkRecipientForm?.querySelector(
        'input[name="recipient"]',
    );
    const phoneNumberInput = bulkRecipientForm?.querySelector(
        'input[name="phoneNumber"]',
    );
    const addressInput = bulkRecipientForm?.querySelector(
        'input[name="address"]',
    );
    const neighborhoodInput = bulkRecipientForm?.querySelector(
        'input[name="neighborhood"]',
    );
    const citySelect = bulkRecipientForm?.querySelector('select[name="city"]');

    function getRowCheckboxes() {
        if (!tableContainer) return [];
        return Array.from(tableContainer.querySelectorAll(".colis-checkbox"));
    }

    function getSelectedIds() {
        return getRowCheckboxes()
            .filter((cb) => cb.checked)
            .map((cb) => cb.value);
    }

    function syncSelectAll() {
        if (!selectAll) return;
        const checkboxes = getRowCheckboxes();
        const checkedCount = getSelectedIds().length;
        selectAll.checked =
            checkboxes.length > 0 && checkedCount === checkboxes.length;
        selectAll.indeterminate =
            checkedCount > 0 && checkedCount < checkboxes.length;
    }

    function syncBulkUI() {
        const selectedCount = getSelectedIds().length;
        if (countEl) countEl.textContent = String(selectedCount);
        bulkButtons.forEach((btn) => {
            btn.disabled = selectedCount === 0;
            btn.classList.toggle("opacity-60", selectedCount === 0);
            btn.classList.toggle("cursor-not-allowed", selectedCount === 0);
        });
    }

    function setCitySelectValue(rawValue) {
        if (!citySelect) return;
        const targetValue = (rawValue || "").trim();
        let resolvedValue = "";

        if (targetValue !== "") {
            const matchingOption = Array.from(citySelect.options).find(
                (option) =>
                    option.value.trim().toLowerCase() ===
                    targetValue.toLowerCase(),
            );
            resolvedValue = matchingOption ? matchingOption.value : "";
        }

        citySelect.value = resolvedValue;
        citySelect.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function syncFormPrefillFromSelection() {
        const selectedCheckboxes = getRowCheckboxes().filter(
            (cb) => cb.checked,
        );
        if (selectedCheckboxes.length !== 1) {
            if (recipientInput) recipientInput.value = "";
            if (phoneNumberInput) phoneNumberInput.value = "";
            if (addressInput) addressInput.value = "";
            if (neighborhoodInput) neighborhoodInput.value = "";
            setCitySelectValue("");
            return;
        }

        const selected = selectedCheckboxes[0];
        if (recipientInput)
            recipientInput.value = selected.dataset.recipient || "";
        if (phoneNumberInput)
            phoneNumberInput.value = selected.dataset.phoneNumber || "";
        if (addressInput) addressInput.value = selected.dataset.address || "";
        if (neighborhoodInput)
            neighborhoodInput.value = selected.dataset.neighborhood || "";
        setCitySelectValue(selected.dataset.city || "");
    }

    function ensureIdsOnSubmit(form) {
        if (!form) return;
        form.addEventListener("submit", function (e) {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                e.preventDefault();
                alert("Aucun colis sélectionné.");
                return;
            }

            form.querySelectorAll('input[name="colis_ids[]"]').forEach((n) =>
                n.remove(),
            );
            ids.forEach((id) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "colis_ids[]";
                input.value = id;
                form.appendChild(input);
            });
        });
    }

    function applyCityDropdownScroll() {
        const optionsList =
            document.querySelector(".kt-select-dropdown .kt-select-options") ||
            document.querySelector(
                ".select2-container--open .select2-results__options",
            ) ||
            document.querySelector(".choices.is-open .choices__list--dropdown");

        if (!optionsList) return;
        optionsList.style.maxHeight = "240px";
        optionsList.style.overflowY = "auto";
    }

    if (selectAll) {
        selectAll.addEventListener("change", function () {
            const checkboxes = getRowCheckboxes();
            checkboxes.forEach((cb) => (cb.checked = selectAll.checked));
            syncSelectAll();
            syncBulkUI();
            syncFormPrefillFromSelection();
        });
    }

    if (tableContainer) {
        tableContainer.addEventListener("change", function (event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (!target.classList.contains("colis-checkbox")) return;
            syncSelectAll();
            syncBulkUI();
            syncFormPrefillFromSelection();
        });
    }

    if (bulkRecipientCitySelect) {
        ["focus", "click", "keydown"].forEach((eventName) => {
            bulkRecipientCitySelect.addEventListener(eventName, function () {
                window.setTimeout(applyCityDropdownScroll, 0);
            });
        });
    }

    document.addEventListener("click", function () {
        window.setTimeout(applyCityDropdownScroll, 0);
    });

    syncSelectAll();
    syncBulkUI();
    syncFormPrefillFromSelection();
    ensureIdsOnSubmit(bulkRecipientForm);
})();
