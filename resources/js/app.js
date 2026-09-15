import './bootstrap';
import '../css/app.css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const hasSelectedFile = [...form.querySelectorAll('input[type="file"]')]
        .some((input) => input.files && input.files.length > 0);
    if (!hasSelectedFile || form.dataset.fileUploadSubmitting === 'true') return;

    form.dataset.fileUploadSubmitting = 'true';
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
    });

    const status = document.createElement('div');
    status.className = 'mt-3 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800';
    status.setAttribute('role', 'status');
    status.textContent = 'Cargando archivo… no cierres esta página.';
    form.append(status);

    const beforeUnload = (beforeUnloadEvent) => {
        beforeUnloadEvent.preventDefault();
        beforeUnloadEvent.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);
}, true);
