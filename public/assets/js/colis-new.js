(function () {
    const getReplacePackageCheckbox = () => (
        document.getElementById('replace-package-toggle')
        || document.querySelector('input[type="checkbox"][name$="[replacePackage]"]')
    );

    const getOldColisHiddenInput = () => (
        document.getElementById('old-colis-input-hidden')
        || document.querySelector('[name$="[oldColis]"]')
    );

    const getCartonOptionInput = () => (
        document.getElementById('carton-option-input')
        || document.querySelector('[name$="[cartonOption]"]')
    );

    const getFragileInput = () => (
        document.getElementById('fragile-input')
        || document.querySelector('input[type="checkbox"][name$="[fragile]"]')
    );

    const getAllFragileInput = () => (
        document.getElementById('all-fragile-input')
        || document.querySelector('input[type="checkbox"][name$="[allFragile]"]')
    );

    const setDisabledStateForContainer = (container, disabled) => {
        if (!container) {
            return;
        }

        container.querySelectorAll('input, select, textarea, button').forEach((el) => {
            el.disabled = disabled;
            if (disabled) {
                el.setAttribute('tabindex', '-1');
            } else {
                el.removeAttribute('tabindex');
            }
        });
    };

    const toggleOldColisRow = (checked) => {
        const oldColisRow = document.getElementById('old-colis-row');
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisCell = document.getElementById('old-colis-cell');
        const oldColisHiddenInput = getOldColisHiddenInput();

        if (!oldColisRow || !oldColisSelect) {
            return;
        }

        oldColisRow.style.display = checked ? '' : 'none';
        oldColisRow.hidden = !checked;
        oldColisRow.setAttribute('aria-hidden', (!checked).toString());
        oldColisSelect.disabled = !checked;
        oldColisSelect.required = checked;
        oldColisSelect.setAttribute('aria-disabled', (!checked).toString());
        oldColisSelect.tabIndex = checked ? 0 : -1;

        if (oldColisHiddenInput) {
            oldColisHiddenInput.disabled = !checked;
            oldColisHiddenInput.required = checked;
        }

        if (oldColisCell) {
            oldColisCell.classList.toggle('old-colis-disabled', !checked);
            if (checked) {
                oldColisCell.removeAttribute('inert');
            } else {
                oldColisCell.setAttribute('inert', '');
            }
        }

        if (!checked) {
            oldColisSelect.value = '';
            if (oldColisHiddenInput) {
                oldColisHiddenInput.value = '';
            }
        }

        setDisabledStateForContainer(oldColisCell, !checked);
    };

    const preventOldColisInteractionWhenDisabled = () => {
        const oldColisCell = document.getElementById('old-colis-cell');
        const oldColisRow = document.getElementById('old-colis-row');
        const checkbox = getReplacePackageCheckbox();
        if (!oldColisCell || !oldColisRow || !checkbox || oldColisCell.dataset.interactionBound === '1') {
            return;
        }

        const blockEvent = (event) => {
            if (!checkbox.checked) {
                event.preventDefault();
                event.stopPropagation();
            }
        };

        ['click', 'mousedown', 'mouseup', 'keydown', 'focusin', 'touchstart'].forEach((eventName) => {
            oldColisCell.addEventListener(eventName, blockEvent, true);
        });

        document.addEventListener('click', (event) => {
            if (!checkbox.checked && oldColisRow.contains(event.target)) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);

        oldColisCell.dataset.interactionBound = '1';
    };

    const syncOldColisValue = () => {
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisHiddenInput = getOldColisHiddenInput();
        const checkbox = getReplacePackageCheckbox();
        if (!oldColisSelect || !oldColisHiddenInput) {
            return;
        }
        if (checkbox && !checkbox.checked) {
            oldColisHiddenInput.value = '';
            return;
        }
        oldColisHiddenInput.value = oldColisSelect.value || '';
    };

    const syncOldColisUiFromHidden = () => {
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisHiddenInput = getOldColisHiddenInput();
        const checkbox = getReplacePackageCheckbox();
        if (!oldColisSelect || !oldColisHiddenInput) {
            return;
        }

        if (checkbox && !checkbox.checked) {
            oldColisSelect.value = '';
            return;
        }

        const targetValue = oldColisHiddenInput.value || '';
        oldColisSelect.value = targetValue;
    };

    const capDropdownHeight = () => {
        const selectors = [
            '[role="listbox"]',
            '.ts-dropdown',
            '.ts-dropdown-content',
            '.select2-results__options',
            '.choices__list--dropdown .choices__list'
        ];

        selectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                el.style.maxHeight = '220px';
                el.style.overflowY = 'auto';
                el.style.overscrollBehavior = 'contain';

                if (el.dataset.scrollBound === '1') {
                    return;
                }

                el.addEventListener('wheel', (event) => {
                    event.stopPropagation();
                }, { passive: true });

                el.dataset.scrollBound = '1';
            });
        });
    };

    document.addEventListener('click', () => setTimeout(capDropdownHeight, 0));
    document.addEventListener('focusin', () => setTimeout(capDropdownHeight, 0));
    document.addEventListener('DOMContentLoaded', capDropdownHeight);

    const initFlashAutoDismiss = () => {
        document.querySelectorAll('[data-flash-auto-dismiss="true"]').forEach((flash) => {
            window.setTimeout(() => {
                flash.remove();
            }, 3000);
        });
    };

    const initReplacePackageToggle = () => {
        const checkbox = getReplacePackageCheckbox();
        if (!checkbox) {
            return;
        }

        const initialChecked = checkbox.getAttribute('data-initial-checked');
        if (initialChecked === '1' || initialChecked === '0') {
            checkbox.checked = initialChecked === '1';
        }

        preventOldColisInteractionWhenDisabled();

        const syncOldColisState = () => toggleOldColisRow(checkbox.checked);

        checkbox.addEventListener('change', syncOldColisState);
        checkbox.addEventListener('input', syncOldColisState);
        checkbox.addEventListener('click', () => setTimeout(() => {
            syncOldColisState();
            syncOldColisUiFromHidden();
        }, 0));
        document.addEventListener('change', (event) => {
            if (event.target && event.target.matches('#old-colis-select')) {
                syncOldColisValue();
            }
            if (event.target && event.target.matches('input[type="checkbox"][name$="[replacePackage]"], #replace-package-toggle')) {
                syncOldColisState();
                syncOldColisUiFromHidden();
                syncOldColisValue();
            }
        });
        syncOldColisState();
        syncOldColisUiFromHidden();
        syncOldColisValue();
        setTimeout(syncOldColisState, 0);
        setTimeout(syncOldColisUiFromHidden, 0);
        setTimeout(syncOldColisValue, 0);
    };

    const initBadgeChecks = () => {
        if (document.body.dataset.badgeChecksBound === '1') {
            return;
        }

            const fragileInput = getFragileInput();
            const allFragileInput = getAllFragileInput();
            const fragileToggle = document.querySelector('[data-fragile-toggle="true"]');
            const fragileAllToggle = document.querySelector('[data-fragile-all-toggle="true"]');
            if (fragileToggle && fragileInput && fragileInput.checked) {
                fragileToggle.classList.add('active');
                fragileToggle.setAttribute('aria-pressed', 'true');
            }
            if (fragileAllToggle && allFragileInput && allFragileInput.checked) {
                fragileAllToggle.classList.add('active');
                fragileAllToggle.setAttribute('aria-pressed', 'true');
            }

            const cartonOptionInput = getCartonOptionInput();
            const savedCartonOption = cartonOptionInput ? cartonOptionInput.value : '';
            if (savedCartonOption) {
                const cartonToggle = document.querySelector('[data-carton-toggle="true"]');
                const selectedCartonButton = document.querySelector(`[data-carton-choice="${savedCartonOption}"]`);
                if (cartonToggle) {
                    cartonToggle.classList.add('active');
                    cartonToggle.setAttribute('aria-pressed', 'true');
                }
                if (selectedCartonButton) {
                    selectedCartonButton.classList.add('active');
                    selectedCartonButton.setAttribute('aria-pressed', 'true');
                }
            }

        const resetCartonChoices = () => {
            const cartonOptionInput = getCartonOptionInput();
            document.querySelectorAll('[data-carton-choice]').forEach((btn) => {
                btn.classList.remove('active');
                btn.setAttribute('aria-pressed', 'false');
            });
            if (cartonOptionInput) {
                cartonOptionInput.value = '';
            }
        };

        const syncCartonCard = () => {
            const cartonToggle = document.querySelector('[data-carton-toggle="true"]');
            const cartonCard = document.getElementById('carton-options-card');
            if (!cartonToggle || !cartonCard) {
                return;
            }
            const enabled = cartonToggle.classList.contains('active');
            cartonCard.style.display = enabled ? '' : 'none';
            if (!enabled) {
                resetCartonChoices();
            }
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-badge-check="true"]');
            if (!button) {
                return;
            }

            if (button.hasAttribute('data-fragile-toggle')) {
                const fragileInput = getFragileInput();
                const isActive = button.classList.contains('active');
                button.classList.toggle('active', !isActive);
                button.setAttribute('aria-pressed', (!isActive).toString());
                if (fragileInput) {
                    fragileInput.checked = !isActive;
                }
                return;
            }

            if (button.hasAttribute('data-fragile-all-toggle')) {
                const allFragileInput = getAllFragileInput();
                const isActive = button.classList.contains('active');
                button.classList.toggle('active', !isActive);
                button.setAttribute('aria-pressed', (!isActive).toString());
                if (allFragileInput) {
                    allFragileInput.checked = !isActive;
                }
                return;
            }

            if (button.hasAttribute('data-carton-choice')) {
                const cartonButtons = document.querySelectorAll('[data-carton-choice]');
                const cartonOptionInput = getCartonOptionInput();
                cartonButtons.forEach((btn) => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-pressed', 'false');
                });
                button.classList.add('active');
                button.setAttribute('aria-pressed', 'true');
                if (cartonOptionInput) {
                    cartonOptionInput.value = button.getAttribute('data-carton-choice') || '';
                }
                return;
            }

            const isActive = button.classList.contains('active');
            button.classList.toggle('active', !isActive);
            button.setAttribute('aria-pressed', (!isActive).toString());
            syncCartonCard();
        });

        syncCartonCard();
        document.body.dataset.badgeChecksBound = '1';
    };

    const syncCartonOptionOnSubmit = () => {
        const form = document.getElementById('colis-new-form');
        if (!form || form.dataset.cartonSubmitBound === '1') {
            return;
        }

        form.addEventListener('submit', () => {
            const cartonOptionInput = getCartonOptionInput();
            if (!cartonOptionInput) {
                return;
            }

            const cartonToggle = document.querySelector('[data-carton-toggle="true"]');
            const selectedCartonButton = document.querySelector('[data-carton-choice].active');
            const cartonEnabled = !!(cartonToggle && cartonToggle.classList.contains('active'));

            if (!cartonEnabled) {
                cartonOptionInput.value = '';
                return;
            }

            cartonOptionInput.value = selectedCartonButton
                ? (selectedCartonButton.getAttribute('data-carton-choice') || '')
                : '';
        });

        form.dataset.cartonSubmitBound = '1';
    };

    document.addEventListener('DOMContentLoaded', initReplacePackageToggle);
    document.addEventListener('DOMContentLoaded', initBadgeChecks);
    document.addEventListener('DOMContentLoaded', syncCartonOptionOnSubmit);
    document.addEventListener('DOMContentLoaded', initFlashAutoDismiss);
    initReplacePackageToggle();
    initBadgeChecks();
    syncCartonOptionOnSubmit();
    initFlashAutoDismiss();

    const observer = new MutationObserver(() => {
        capDropdownHeight();
        const checkbox = getReplacePackageCheckbox();
        if (checkbox) {
            toggleOldColisRow(checkbox.checked);
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
