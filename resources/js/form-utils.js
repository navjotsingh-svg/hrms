/**
 * Shared form helpers — use across all create/edit forms.
 */

export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/** Digits-only mobile: exactly 10 digits. */
export const MOBILE_PATTERN = /^[0-9]{10}$/;

export const isValidEmail = (value) => EMAIL_PATTERN.test(String(value || '').trim());

export const isValidMobile = (value) => MOBILE_PATTERN.test(String(value || '').trim().replace(/\s+/g, ''));

export const normalizeMobile = (value) => String(value || '').trim().replace(/\s+/g, '');

export const toDateInputValue = (date) => {
    const value = date instanceof Date ? date : new Date(date);

    if (Number.isNaN(value.getTime())) {
        return '';
    }

    return value.toISOString().split('T')[0];
};

export const addYears = (date, years) => {
    const value = new Date(date);
    value.setFullYear(value.getFullYear() + years);

    return value;
};

export const addMonths = (date, months) => {
    const value = new Date(date);
    value.setMonth(value.getMonth() + months);

    return value;
};

export const hasValidDateYear = (value) => {
    if (!value) {
        return true;
    }

    const match = String(value).match(/^(\d{4})-/);

    return Boolean(match && match[1].length === 4);
};

export const bindStatusToggle = (form, options = {}) => {
    const {
        toggleId = 'status_toggle',
        hiddenId = 'status',
    } = options;

    const toggle = form.querySelector(`#${toggleId}`);
    const hidden = form.querySelector(`#${hiddenId}`);

    if (!toggle) {
        return;
    }

    const sync = () => {
        if (hidden) {
            hidden.value = toggle.checked ? 'active' : 'inactive';
        }
    };

    toggle.addEventListener('change', sync);
    sync();
};

export const getStatusValue = (form, options = {}) => {
    const hiddenId = options.hiddenId || 'status';
    const toggleId = options.toggleId || 'status_toggle';
    const hidden = form.querySelector(`#${hiddenId}`);

    if (hidden?.value) {
        return hidden.value;
    }

    const toggle = form.querySelector(`#${toggleId}`);

    return toggle?.checked ? 'active' : 'inactive';
};

export const setStatusValue = (form, status, options = {}) => {
    const toggleId = options.toggleId || 'status_toggle';
    const hiddenId = options.hiddenId || 'status';
    const isActive = status !== 'inactive';
    const toggle = form.querySelector(`#${toggleId}`);
    const hidden = form.querySelector(`#${hiddenId}`);

    if (toggle) {
        toggle.checked = isActive;
    }

    if (hidden) {
        hidden.value = isActive ? 'active' : 'inactive';
    }
};

export const initFormStatusToggles = () => {
    document.querySelectorAll('form').forEach((form) => {
        if (form.querySelector('#status_toggle')) {
            bindStatusToggle(form);
        }
    });
};

export const setFieldError = (form, field, message) => {
    const input = form.querySelector(`#${field}`) || form.querySelector(`[name="${field}"]`);

    if (input) {
        input.classList.toggle('is-invalid', Boolean(message));
        input.classList.toggle('is-valid', !message && input.value.trim() !== '');
    }

    const feedback = form.querySelector(`[data-error="${field}"]`);

    if (feedback) {
        feedback.textContent = message || '';
    }
};

export const clearFormErrors = (form) => {
    form.querySelectorAll('.is-invalid, .is-valid').forEach((element) => {
        element.classList.remove('is-invalid', 'is-valid');
    });

    form.querySelectorAll('[data-error]').forEach((element) => {
        element.textContent = '';
    });
};

export const focusFirstInvalidField = (form) => {
    const firstInvalid = form.querySelector('.is-invalid');

    if (!firstInvalid) {
        return;
    }

    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    firstInvalid.focus({ preventScroll: true });
};

/**
 * Disable submit button and show Bootstrap spinner + status text.
 */
export const setSubmitLoading = (button, loading, options = {}) => {
    if (!button) {
        return;
    }

    const {
        isUpdate = false,
        submittingText = 'Submitting...',
        updatingText = 'Updating...',
    } = options;

    if (loading) {
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }

        button.disabled = true;
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            ${isUpdate ? updatingText : submittingText}
        `;
        return;
    }

    button.disabled = false;

    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
};

export const debounce = (callback, delay = 400) => {
    let timeoutId;

    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => callback(...args), delay);
    };
};

export const applyBackendErrors = (form, errors, setError = setFieldError) => {
    if (!errors) {
        return;
    }

    Object.entries(errors).forEach(([field, messages]) => {
        setError(form, field, Array.isArray(messages) ? messages[0] : messages);
    });
};

export const bindUploadProgress = ({
    wrap,
    bar,
    percentEl,
    labelEl,
} = {}) => {
    const update = (percent, label) => {
        const value = Math.max(0, Math.min(100, Math.round(percent)));

        wrap?.classList.remove('d-none');

        if (bar) {
            bar.style.width = `${value}%`;
            bar.setAttribute('aria-valuenow', String(value));
            bar.classList.toggle('progress-bar-animated', value < 100);
            bar.classList.toggle('progress-bar-striped', value < 100);
        }

        if (percentEl) {
            percentEl.textContent = `${value}%`;
        }

        if (labelEl) {
            labelEl.textContent = label || (value >= 100 ? 'Processing on server...' : 'Uploading...');
        }
    };

    const hide = () => {
        wrap?.classList.add('d-none');
        update(0, 'Uploading...');
    };

    const onUploadProgress = (event) => {
        if (!event.total) {
            return;
        }

        update((event.loaded / event.total) * 100);
    };

    return { update, hide, onUploadProgress };
};

const FLASH_STORAGE_KEY = 'hrms_flash_message';

export const flashMessageType = (message) => (
    /could not be sent|share credentials manually/i.test(String(message || '')) ? 'warning' : 'success'
);

export const setFlashMessage = (message, type = 'success') => {
    if (!message) {
        return;
    }

    sessionStorage.setItem(FLASH_STORAGE_KEY, JSON.stringify({ message, type }));
};

export const consumeFlashMessage = () => {
    const raw = sessionStorage.getItem(FLASH_STORAGE_KEY);

    if (!raw) {
        return null;
    }

    sessionStorage.removeItem(FLASH_STORAGE_KEY);

    try {
        const parsed = JSON.parse(raw);

        return parsed?.message ? parsed : null;
    } catch {
        return { message: raw, type: 'success' };
    }
};

/** Read a one-time flash message and strip legacy ?success= from the URL. */
export const consumePageFlashMessage = () => {
    const flash = consumeFlashMessage();

    if (flash?.message) {
        return flash;
    }

    const params = new URLSearchParams(window.location.search);
    const legacyMessage = params.get('success');

    if (!legacyMessage) {
        return null;
    }

    params.delete('success');
    const query = params.toString();
    const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);

    return {
        message: decodeURIComponent(legacyMessage),
        type: flashMessageType(legacyMessage),
    };
};
