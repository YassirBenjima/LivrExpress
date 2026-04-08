(function () {
    const toggleOldColisRow = (checked) => {
        const oldColisRow = document.getElementById('old-colis-row');
        const oldColisSelect = document.getElementById('old-colis-select');
        const oldColisCell = document.getElementById('old-colis-cell');

        if (!oldColisRow || !oldColisSelect) {
            return;
        }

        oldColisRow.style.display = '';
        oldColisSelect.disabled = !checked;
        oldColisSelect.required = checked;
        oldColisSelect.setAttribute('aria-disabled', (!checked).toString());

        if (oldColisCell) {
            oldColisCell.classList.toggle('old-colis-disabled', !checked);
        }

        if (!checked) {
            oldColisSelect.value = '';
        }
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

    const initReplacePackageToggle = () => {
        const checkbox = document.getElementById('replace-package-toggle');
        if (!checkbox) {
            return;
        }

        const syncOldColisState = () => toggleOldColisRow(checkbox.checked);

        checkbox.addEventListener('change', syncOldColisState);
        checkbox.addEventListener('input', syncOldColisState);
        checkbox.addEventListener('click', () => setTimeout(syncOldColisState, 0));
        syncOldColisState();
        setTimeout(syncOldColisState, 0);
    };

    const initBadgeChecks = () => {
        if (document.body.dataset.badgeChecksBound === '1') {
            return;
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-badge-check="true"]');
            if (!button) {
                return;
            }

            const isActive = button.classList.contains('active');
            button.classList.toggle('active', !isActive);
            button.setAttribute('aria-pressed', (!isActive).toString());
        });

        document.body.dataset.badgeChecksBound = '1';
    };

    document.addEventListener('DOMContentLoaded', initReplacePackageToggle);
    document.addEventListener('DOMContentLoaded', initBadgeChecks);
    initReplacePackageToggle();
    initBadgeChecks();

    const observer = new MutationObserver(capDropdownHeight);
    observer.observe(document.body, { childList: true, subtree: true });
})();
