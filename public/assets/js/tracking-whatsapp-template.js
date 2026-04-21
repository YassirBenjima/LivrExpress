(function () {
    const searchInput = document.querySelector('[data-whatsapp-live-search="true"]');
    const statusFilter = document.querySelector('[data-whatsapp-status-filter="true"]');
    const table = document.getElementById('whatsapp_templates_table');
    const rows = Array.from(document.querySelectorAll('[data-whatsapp-template-row="true"]'));
    const countLabel = document.querySelector('[data-whatsapp-count-label="true"]');

    function updateVisibleCount(count) {
        if (!countLabel) {
            return;
        }

        countLabel.textContent = 'Affichage de ' + count + ' modèle(s)';
    }

    function applyLiveFilters() {
        if (!table || rows.length === 0) {
            return;
        }

        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const selectedStatus = (statusFilter ? statusFilter.value : '').trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const rowText = (row.textContent || '').toLowerCase();
            const rowStatus = (row.getAttribute('data-whatsapp-template-status') || '').toLowerCase();
            const matchesQuery = query === '' || rowText.indexOf(query) !== -1;
            const matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;
            const isVisible = matchesQuery && matchesStatus;

            row.classList.toggle('hidden', !isVisible);
            if (isVisible) {
                visibleCount += 1;
            }
        });

        updateVisibleCount(visibleCount);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyLiveFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyLiveFilters);
    }

    applyLiveFilters();

    const messageInput = document.querySelector('[data-whatsapp-message-input="true"]');
    const counter = document.querySelector('[data-whatsapp-message-counter="true"]');
    const warning = document.querySelector('[data-whatsapp-message-warning="true"]');

    function updateCounter() {
        if (!messageInput || !counter) {
            return;
        }

        const softLimit = parseInt(counter.getAttribute('data-soft-limit') || '400', 10);
        const hardLimit = parseInt(counter.getAttribute('data-hard-limit') || '2000', 10);
        const length = messageInput.value.length;

        counter.textContent = length + '/' + hardLimit;
        counter.classList.toggle('text-warning', length > softLimit && length <= hardLimit);
        counter.classList.toggle('text-destructive', length > hardLimit);

        if (warning) {
            warning.classList.toggle('hidden', length <= softLimit);
        }
    }

    if (messageInput) {
        messageInput.addEventListener('input', updateCounter);
        updateCounter();
    }

    const deleteButtons = Array.from(document.querySelectorAll('[data-whatsapp-delete-open="true"]'));
    const deleteForm = document.getElementById('whatsapp-template-delete-form');
    const deleteTitle = document.querySelector('[data-whatsapp-delete-title="true"]');
    const deleteToken = document.querySelector('[data-whatsapp-delete-token="true"]');
    if (deleteButtons.length > 0 && deleteForm && deleteTitle && deleteToken) {
        deleteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-template-id');
                const title = button.getAttribute('data-template-title') || '';
                const token = button.getAttribute('data-template-token') || '';

                deleteTitle.textContent = title;
                deleteToken.value = token;
                deleteForm.setAttribute('action', '/suivi/modele-whatsapp/' + id + '/delete');

            });
        });
    }
})();
