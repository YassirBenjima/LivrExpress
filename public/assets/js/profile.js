document.addEventListener('DOMContentLoaded', function () {
    const cancelButton = document.getElementById('profile-cancel-btn');
    const saveButton = document.getElementById('profile-save-btn');
    const trackedFields = Array.from(document.querySelectorAll('form'))
        .filter(function (form) {
            return form.id !== 'profile-password-form';
        })
        .reduce(function (fields, form) {
            return fields.concat(Array.from(form.querySelectorAll(
                'input:not([type="hidden"]):not([type="file"]), select, textarea'
            )));
        }, []);

    function getDbValue(field) {
        return field.dataset.dbValue || '';
    }

    function getComparableDbValue(field) {
        const dbValue = getDbValue(field);
        if (field.id === 'return-reception-select' && dbValue === '' && field.value === 'En Agence') {
            return 'En Agence';
        }

        return dbValue;
    }

    function isElementVisible(element) {
        if (!element) return false;
        if (element.getClientRects().length === 0) return false;

        const style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function isFieldVisible(field) {
        const row = field.closest('tr');
        if (row) {
            return isElementVisible(row);
        }

        const form = field.closest('form');
        if (form) {
            return isElementVisible(form);
        }

        return isElementVisible(field);
    }

    function hasUnsavedChanges() {
        return trackedFields.some(function (field) {
            if (!isFieldVisible(field)) return false;
            const dbValue = getComparableDbValue(field);
            return field.value !== dbValue;
        });
    }

    function syncCancelButtonState() {
        if (!cancelButton) return;
        cancelButton.disabled = !hasUnsavedChanges();
    }

    function syncSaveButtonState() {
        if (!saveButton) return;
        saveButton.disabled = !hasUnsavedChanges();
    }

    function syncActionButtonsState() {
        syncCancelButtonState();
        syncSaveButtonState();
    }

    trackedFields.forEach(function (field) {
        field.addEventListener('input', syncCancelButtonState);
        field.addEventListener('change', syncCancelButtonState);
        field.addEventListener('input', syncSaveButtonState);
        field.addEventListener('change', syncSaveButtonState);
    });

    // Safety net for custom UI selects/components that may dispatch late changes.
    document.addEventListener('change', function () {
        syncActionButtonsState();
    }, true);

    const bankRibInputs = Array.from(document.querySelectorAll('.bank-rib-input'));
    bankRibInputs.forEach(function (input) {
        function sanitizeRibValue() {
            const digitsOnly = input.value.replace(/\D+/g, '').slice(0, 24);
            input.value = digitsOnly;
        }

        input.addEventListener('input', sanitizeRibValue);
        input.addEventListener('paste', function () {
            setTimeout(sanitizeRibValue, 0);
        });
    });

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            if (!hasUnsavedChanges()) return;

            trackedFields.forEach(function (field) {
                field.value = getComparableDbValue(field);
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });

            syncActionButtonsState();
        });
    }

    syncActionButtonsState();

    const passwordForm = document.getElementById('profile-password-form');
    const passwordSaveButton = document.getElementById('profile-password-save-btn');
    if (passwordForm && passwordSaveButton) {
        const passwordFields = ['current_password', 'new_password', 'confirm_password']
            .map(function (name) {
                return passwordForm.querySelector('[name="' + name + '"]');
            })
            .filter(Boolean);

        function syncPasswordSaveState() {
            const allFilled = passwordFields.length === 3 && passwordFields.every(function (field) {
                return field.value.trim() !== '';
            });
            passwordSaveButton.disabled = !allFilled;
        }

        passwordFields.forEach(function (field) {
            field.addEventListener('input', syncPasswordSaveState);
            field.addEventListener('change', syncPasswordSaveState);
        });

        syncPasswordSaveState();
    }

    if (saveButton) {
        saveButton.addEventListener('click', async function () {
            if (!hasUnsavedChanges()) return;

            saveButton.disabled = true;

            const modifiedForms = [];
            trackedFields.forEach(function (field) {
                const dbValue = getComparableDbValue(field);
                if (field.value === dbValue) return;
                if (!isFieldVisible(field)) return;

                const form = field.closest('form');
                if (form && !modifiedForms.includes(form)) {
                    modifiedForms.push(form);
                }
            });

            try {
                for (const form of modifiedForms) {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Save failed');
                    }
                }

                window.location.reload();
            } catch (e) {
                syncSaveButtonState();
            }
        });
    }

    const avatarInput = document.querySelector('input[name="avatar"]');
    const avatarForm = avatarInput ? avatarInput.closest('form') : null;

    if (avatarForm) {
        const removeInput = avatarForm.querySelector('input[name="avatar_remove"]');
        const removeButton = avatarForm.querySelector('[data-kt-image-input-remove="true"]');

        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                if (avatarInput.files && avatarInput.files.length > 0) {
                    if (removeInput) removeInput.value = '0';
                    avatarForm.submit();
                }
            });
        }

        if (removeButton && removeInput) {
            removeButton.addEventListener('click', function () {
                removeInput.value = '1';
                setTimeout(function () {
                    avatarForm.submit();
                }, 0);
            });
        }
    }

    const returnReceptionSelect = document.getElementById('return-reception-select');
    const agencyRow = document.getElementById('agency-row');
    const ramassageRows = document.querySelectorAll('.ramassage-row');

    function toggleReturnFields() {
        if (!returnReceptionSelect) return;
        const val = returnReceptionSelect.value;
        if (val === 'En Agence') {
            if (agencyRow) agencyRow.style.display = '';
            ramassageRows.forEach(function (row) { row.style.display = 'none'; });
        } else {
            if (agencyRow) agencyRow.style.display = 'none';
            ramassageRows.forEach(function (row) { row.style.display = ''; });
        }

        // Recompute immediately after visibility changes.
        syncActionButtonsState();
    }

    if (returnReceptionSelect) {
        returnReceptionSelect.addEventListener('change', function () {
            toggleReturnFields();
            setTimeout(syncActionButtonsState, 0);
        });
        if (typeof jQuery !== 'undefined') {
            jQuery(returnReceptionSelect).on('change', function () {
                toggleReturnFields();
                setTimeout(syncActionButtonsState, 0);
            });
        } else if (typeof $ !== 'undefined') {
            $(returnReceptionSelect).on('change', function () {
                toggleReturnFields();
                setTimeout(syncActionButtonsState, 0);
            });
        }
        toggleReturnFields();
    }
});
