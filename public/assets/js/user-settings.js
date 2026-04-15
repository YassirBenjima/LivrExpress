(function () {
  const API_URL = '/api/settings';

  const createToast = ({ message, type }) => {
    const isError = type === 'error';
    const wrapperStyle = isError
      ? 'background-color: #fff5f8; border: 1px solid #f1416c; color: #f1416c;'
      : 'background-color: #e8fff3; border: 1px solid #50cd89; color: #50cd89;';

    let container = document.getElementById('global-toasts');
    if (!container) {
      container = document.createElement('div');
      container.id = 'global-toasts';
      container.className = 'fixed flex flex-col gap-3';
      container.style.cssText = 'top: 20px; right: 20px; z-index: 2147483647;';
      document.body.appendChild(container);
    } else if (container.parentElement !== document.body) {
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'flex items-center gap-3 p-4 rounded-xl shadow-lg transition-opacity duration-300';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('style', wrapperStyle);
    toast.innerHTML = `
      <i class="ki-filled ${isError ? 'ki-information-2' : 'ki-check-circle'} text-xl"></i>
      <div class="font-medium flex-1">${message}</div>
      <button type="button" class="ms-auto opacity-80 hover:opacity-100 transition-opacity">
        <i class="ki-filled ki-cross"></i>
      </button>
    `;

    toast.querySelector('button')?.addEventListener('click', () => toast.remove());
    container.appendChild(toast);

    window.setTimeout(() => {
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 300);
    }, 5000);
  };

  const safeJson = async (response) => {
    try {
      return await response.json();
    } catch {
      return null;
    }
  };

  const fetchSettings = async () => {
    const res = await fetch(API_URL, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
      throw new Error('Impossible de charger les paramètres.');
    }
    const data = await safeJson(res);
    if (!data || typeof data !== 'object') {
      throw new Error('Réponse invalide du serveur.');
    }
    return data;
  };

  const putSettings = async (payload) => {
    const res = await fetch(API_URL, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await safeJson(res);
    if (!res.ok) {
      const message =
        (data && (data.detail || data.message)) ||
        'Une erreur est survenue lors de l’enregistrement.';
      throw new Error(message);
    }
    return data;
  };

  const setButtonActive = (btn, active) => {
    btn.classList.toggle('active', !!active);
    btn.setAttribute('aria-pressed', (!!active).toString());
  };

  const initParcelSettingsPage = (root) => {
    const map = [
      { selector: '[data-fragile-toggle="true"]', key: 'fragile' },
      { selector: '[data-open-colis-toggle="true"]', key: 'open_colis' },
      // Legacy markup uses data-carton-toggle for the 3rd option on this page.
      { selector: '[data-carton-toggle="true"]', key: 'unique_order_number' },
    ];

    let state = null;
    let saving = false;
    let pending = null;
    let saveTimer = null;

    const queueSave = (nextParcelSettings) => {
      pending = nextParcelSettings;
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(async () => {
        if (!pending) return;
        if (saving) return;
        saving = true;
        const toSave = pending;
        pending = null;
        try {
          const res = await putSettings({ parcel_settings: toSave });
          state.parcel_settings = res.parcel_settings || toSave;
          createToast({ message: 'Paramètres enregistrés.', type: 'success' });
        } catch (e) {
          createToast({ message: e.message || 'Erreur lors de l’enregistrement.', type: 'error' });
        } finally {
          saving = false;
          if (pending) {
            queueSave(pending);
          }
        }
      }, 350);
    };

    fetchSettings()
      .then((data) => {
        state = data;
        const parcel = (data.parcel_settings && typeof data.parcel_settings === 'object')
          ? data.parcel_settings
          : {};

        map.forEach(({ selector, key }) => {
          const btn = root.querySelector(selector);
          if (!btn) return;
          const enabled = !!(parcel[key] && parcel[key].enabled);
          setButtonActive(btn, enabled);
        });
      })
      .catch(() => {
        createToast({ message: 'Impossible de charger vos paramètres.', type: 'error' });
      });

    root.addEventListener('click', (event) => {
      const button = event.target.closest('[data-badge-check="true"]');
      if (!button || !root.contains(button)) return;

      const match = map.find(({ selector }) => button.matches(selector));
      if (!match) return;
      if (!state || !state.parcel_settings) return;

      const current = !!(state.parcel_settings[match.key] && state.parcel_settings[match.key].enabled);
      const next = { ...state.parcel_settings };
      next[match.key] = {
        ...(next[match.key] || {}),
        enabled: !current,
        fee: (next[match.key] && typeof next[match.key].fee === 'number') ? next[match.key].fee : 0,
      };

      // Optimistic UI
      setButtonActive(button, !current);
      state.parcel_settings = next;
      queueSave(next);
    });
  };

  const initPackagingPage = (root) => {
    const optionButtons = Array.from(root.querySelectorAll('[data-packaging-category][data-packaging-choice]'));
    if (optionButtons.length === 0) return;

    let state = null;
    let saving = false;
    let pending = null;
    let saveTimer = null;

    const renderCategory = (category, selected) => {
      optionButtons
        .filter((b) => b.getAttribute('data-packaging-category') === category)
        .forEach((btn) => {
          setButtonActive(btn, (btn.getAttribute('data-packaging-choice') || '') === selected);
        });
    };

    const queueSave = (nextPackagingSettings) => {
      pending = nextPackagingSettings;
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(async () => {
        if (!pending) return;
        if (saving) return;
        saving = true;
        const toSave = pending;
        pending = null;
        try {
          const res = await putSettings({ packaging_settings: toSave });
          state.packaging_settings = res.packaging_settings || toSave;
          createToast({ message: 'Paramètres enregistrés.', type: 'success' });
        } catch (e) {
          createToast({ message: e.message || 'Erreur lors de l’enregistrement.', type: 'error' });
        } finally {
          saving = false;
          if (pending) queueSave(pending);
        }
      }, 350);
    };

    fetchSettings()
      .then((data) => {
        state = data;
        const packaging = (data.packaging_settings && typeof data.packaging_settings === 'object')
          ? data.packaging_settings
          : {};

        ['cartons', 'sachets', 'bubble_wrap'].forEach((cat) => {
          const selected = (packaging[cat] && packaging[cat].selected) ? packaging[cat].selected : 'none';
          renderCategory(cat, selected);
        });
      })
      .catch(() => {
        createToast({ message: 'Impossible de charger vos paramètres.', type: 'error' });
      });

    root.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-packaging-category][data-packaging-choice]');
      if (!btn || !root.contains(btn)) return;
      if (!state || !state.packaging_settings) return;

      const category = btn.getAttribute('data-packaging-category');
      const choice = btn.getAttribute('data-packaging-choice');
      if (!category || !choice) return;

      const current = (state.packaging_settings[category] && state.packaging_settings[category].selected)
        ? state.packaging_settings[category].selected
        : 'none';
      const nextSelected = choice;
      if (current === nextSelected) return;

      const next = { ...state.packaging_settings };
      next[category] = {
        ...(next[category] || {}),
        selected: nextSelected,
        enabled: nextSelected !== 'none',
        fees: (next[category] && next[category].fees) ? next[category].fees : undefined,
      };

      // Optimistic UI
      renderCategory(category, nextSelected);
      state.packaging_settings = next;
      queueSave(next);
    });

    root.addEventListener('click', (event) => {
      const samples = event.target.closest('[data-samples="true"]');
      if (!samples || !root.contains(samples)) return;
      event.preventDefault();
      createToast({ message: 'Échantillons (placeholder).', type: 'success' });
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-user-settings-root="true"]');
    if (!root) return;

    const page = root.getAttribute('data-user-settings-page') || '';
    if (page === 'parcel') {
      initParcelSettingsPage(root);
    }
    if (page === 'packaging') {
      initPackagingPage(root);
    }
  });
})();

