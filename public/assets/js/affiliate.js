document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.affiliate-copy-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const targetId = button.getAttribute('data-copy-target');
            const input = targetId ? document.getElementById(targetId) : null;

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const value = input.value;
            const originalHtml = button.innerHTML;

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(value);
                } else {
                    input.select();
                    document.execCommand('copy');
                }

                button.innerHTML = '<i class="ki-filled ki-check"></i> Copié';
                button.classList.add('kt-btn-success');
                button.classList.remove('kt-btn-primary');

                window.setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('kt-btn-success');
                    button.classList.add('kt-btn-primary');
                }, 2000);
            } catch {
                button.innerHTML = '<i class="ki-filled ki-cross"></i> Erreur';
                window.setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 2000);
            }
        });
    });
});
