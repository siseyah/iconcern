// iconcern - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // -----------------------------
    // Global UI: toast notifications
    // -----------------------------
    const toastContainerId = 'toast-container';
    let toastContainer = document.getElementById(toastContainerId);
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = toastContainerId;
        document.body.appendChild(toastContainer);
    }

    function pushToast({ title, message, type }) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        const icon = type === 'error' ? '!' : '✓';
        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div>
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
        `;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('fade-out');
        }, 4200);

        setTimeout(() => {
            toast.remove();
        }, 4700);
    }

    // Convert server-rendered alerts into toasts (no backend changes).
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const className = alert.className || '';
        const isError = className.includes('alert-error');
        const title = isError ? 'Action Failed' : 'Success';
        const message = alert.textContent.trim();
        if (message) {
            pushToast({ title, message, type: isError ? 'error' : 'success' });
        }
        // Avoid duplicate UI: remove the original alert block.
        alert.remove();
    });

    // -----------------------------
    // Global UI: loading overlay
    // -----------------------------
    function ensureLoadingOverlay() {
        let overlay = document.getElementById('global-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'global-loading-overlay';
            overlay.className = 'global-loading';
            overlay.innerHTML = `<div class="spinner" aria-label="Loading"></div>`;
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    const overlayEl = ensureLoadingOverlay();

    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;

        // Allow pages to opt out if needed.
        if (form.closest('[data-no-loading="true"]')) return;

        // Show loading state immediately on submit.
        overlayEl.classList.add('active');
    });

    // -----------------------------
    // Form validation (lightweight)
    // -----------------------------
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                // Some form controls (checkbox/radio) might not have .value meaningful.
                const value = (field.value || '').trim();
                if (!value) {
                    isValid = false;
                    field.style.borderColor = '#22c55e';
                } else {
                    field.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                overlayEl.classList.remove('active');
                pushToast({ title: 'Missing Info', message: 'Please fill in all required fields.', type: 'error' });
            }
        });
    });

    // -----------------------------
    // File upload size guard
    // -----------------------------
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                pushToast({ title: 'File Too Large', message: 'File size exceeds 5MB limit.', type: 'error' });
                e.target.value = '';
            }
        });
    });

    // -----------------------------
    // Character counter for textarea[maxlength]
    // -----------------------------
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (!maxLength) return;

        const counter = document.createElement('small');
        counter.style.color = 'var(--text-secondary)';
        counter.style.display = 'block';
        counter.style.marginTop = '0.25rem';
        textarea.parentNode.appendChild(counter);

        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length} / ${maxLength} characters`;
            counter.style.color = remaining < 50 ? '#15803d' : 'var(--text-secondary)';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });

    // -----------------------------
    // Profile dropdown: click to open (mobile-friendly)
    // -----------------------------
    function closeAllDropdowns() {
        document.querySelectorAll('.user-menu.open').forEach(menu => {
            menu.classList.remove('open');
        });
    }

    document.addEventListener('click', function(e) {
        const target = e.target;
        if (!target) return;

        const isDropdownToggle = target.closest && target.closest('.user-menu .user-name');
        if (isDropdownToggle) {
            const menu = target.closest('.user-menu');
            if (!menu) return;
            const isOpen = menu.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) menu.classList.add('open');
            e.preventDefault();
            return;
        }

        // Click outside closes it.
        if (!target.closest || !target.closest('.user-menu')) {
            closeAllDropdowns();
        }
    });
});

