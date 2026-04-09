(function () {
    const toggleOldColisRow = (checked) => {
        const oldColisRow = document.getElementById('old-colis-row');
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisCell = document.getElementById('old-colis-cell');
        const oldColisHiddenInput = document.getElementById('old-colis-input-hidden');

        if (!oldColisRow || !oldColisSelect) {
            return;
        }

        oldColisRow.style.display = checked ? '' : 'none';
        oldColisSelect.disabled = !checked;
        oldColisSelect.required = checked;
        oldColisSelect.setAttribute('aria-disabled', (!checked).toString());

        if (oldColisCell) {
            oldColisCell.classList.toggle('old-colis-disabled', !checked);
        }

        if (!checked) {
            oldColisSelect.value = '';
            if (oldColisHiddenInput) {
                oldColisHiddenInput.value = '';
            }
        }
    };

    const syncOldColisValue = () => {
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisHiddenInput = document.getElementById('old-colis-input-hidden');
        if (!oldColisSelect || !oldColisHiddenInput) {
            return;
        }
        oldColisHiddenInput.value = oldColisSelect.value || '';
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
        const checkbox = document.getElementById('replace-package-toggle');
        if (!checkbox) {
            return;
        }

        const syncOldColisState = () => toggleOldColisRow(checkbox.checked);

        checkbox.addEventListener('change', syncOldColisState);
        checkbox.addEventListener('input', syncOldColisState);
        checkbox.addEventListener('click', () => setTimeout(syncOldColisState, 0));
        document.addEventListener('change', (event) => {
            if (event.target && event.target.matches('#old-colis-select')) {
                syncOldColisValue();
            }
        });
        syncOldColisState();
        syncOldColisValue();
        setTimeout(syncOldColisState, 0);
        setTimeout(syncOldColisValue, 0);
    };

    const initBadgeChecks = () => {
        if (document.body.dataset.badgeChecksBound === '1') {
            return;
        }

            const cartonOptionInput = document.getElementById('carton-option-input');
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
            const cartonOptionInput = document.getElementById('carton-option-input');
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

            if (button.hasAttribute('data-carton-choice')) {
                const cartonButtons = document.querySelectorAll('[data-carton-choice]');
                const cartonOptionInput = document.getElementById('carton-option-input');
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

    document.addEventListener('DOMContentLoaded', initReplacePackageToggle);
    document.addEventListener('DOMContentLoaded', initBadgeChecks);
    document.addEventListener('DOMContentLoaded', initFlashAutoDismiss);
    initReplacePackageToggle();
    initBadgeChecks();
    initFlashAutoDismiss();

    const observer = new MutationObserver(capDropdownHeight);
    observer.observe(document.body, { childList: true, subtree: true });
})();
