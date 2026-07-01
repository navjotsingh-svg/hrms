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

export const localDateInputValue = (date) => {
    const value = date instanceof Date ? new Date(date) : new Date(date);

    if (Number.isNaN(value.getTime())) {
        return '';
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export const DATE_RANGE_PRESET_CUSTOM = 'custom';

export const dateRangeForPreset = (preset) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    switch (preset) {
        case 'today':
            return { from: localDateInputValue(today), to: localDateInputValue(today) };
        case 'yesterday': {
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            return { from: localDateInputValue(yesterday), to: localDateInputValue(yesterday) };
        }
        case 'this_week': {
            const start = new Date(today);
            const weekday = start.getDay();
            const daysFromMonday = weekday === 0 ? 6 : weekday - 1;
            start.setDate(start.getDate() - daysFromMonday);

            return { from: localDateInputValue(start), to: localDateInputValue(today) };
        }
        case 'this_month': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);

            return { from: localDateInputValue(start), to: localDateInputValue(today) };
        }
        default:
            return { from: '', to: '' };
    }
};

export const detectDateRangePreset = (from, to) => {
    if (!from && !to) {
        return '';
    }

    const presets = ['today', 'yesterday', 'this_week', 'this_month'];

    for (const preset of presets) {
        const range = dateRangeForPreset(preset);

        if (range.from === from && range.to === to) {
            return preset;
        }
    }

    return DATE_RANGE_PRESET_CUSTOM;
};

export const resolveDateRange = ({ preset = '', from = '', to = '' } = {}) => {
    if (!preset) {
        return { preset: '', from: '', to: '' };
    }

    if (preset === DATE_RANGE_PRESET_CUSTOM) {
        return { preset, from, to };
    }

    const range = dateRangeForPreset(preset);

    return { preset, from: range.from, to: range.to };
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

export const REQUESTS_LIST_STATE_KEY = 'hrms_requests_list_state';

export const saveRequestsListState = (state) => {
    if (!state) {
        return;
    }

    sessionStorage.setItem(REQUESTS_LIST_STATE_KEY, JSON.stringify(state));
};

export const readRequestsListState = () => {
    try {
        const raw = sessionStorage.getItem(REQUESTS_LIST_STATE_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
};

export const buildRequestsReturnUrl = (basePath = '/requests', state = readRequestsListState()) => {
    if (!state) {
        return basePath;
    }

    const params = new URLSearchParams();

    ['tab', 'status', 'type', 'date_preset', 'date_from', 'date_to', 'employee_id', 'employee_label'].forEach((key) => {
        if (state[key]) {
            params.set(key, state[key]);
        }
    });

    const query = params.toString();

    return query ? `${basePath}?${query}` : basePath;
};

export const RETURN_URL_KEY = 'hrms_return_url';

export const REQUEST_CATEGORY_RETURN_FALLBACKS = {
    regularization: '/attendance/regularize',
    'regularization-batch': '/attendance/regularize',
    leave: '/leave',
    wfh: '/wfh',
    asset: '/asset-requests',
    expense: '/expenses',
    expense_group: '/expenses',
};

export const saveReturnUrl = (url = window.location.href) => {
    try {
        sessionStorage.setItem(RETURN_URL_KEY, url);
    } catch {
        // Ignore storage errors (private browsing, etc.).
    }
};

export const readReturnUrl = () => {
    try {
        const raw = sessionStorage.getItem(RETURN_URL_KEY);

        if (!raw) {
            return null;
        }

        const parsed = new URL(raw, window.location.origin);

        if (parsed.origin !== window.location.origin) {
            return null;
        }

        if (parsed.pathname === window.location.pathname && parsed.search === window.location.search) {
            return null;
        }

        return parsed.href;
    } catch {
        return null;
    }
};

export const readReturnUrlFromQuery = () => {
    try {
        const value = new URLSearchParams(window.location.search).get('return');

        if (!value) {
            return null;
        }

        const parsed = new URL(value, window.location.origin);

        if (parsed.origin !== window.location.origin) {
            return null;
        }

        return parsed.href;
    } catch {
        return null;
    }
};

export const buildCategoryReturnUrl = (category, basePath = '/requests') => {
    const path = REQUEST_CATEGORY_RETURN_FALLBACKS[category] || basePath;

    if (path === '/requests') {
        return buildRequestsReturnUrl(path);
    }

    return path;
};

export const resolveReturnUrl = (fallbackHref) => (
    readReturnUrlFromQuery()
    || readReturnUrl()
    || fallbackHref
);

export const navigateBack = (fallbackHref) => {
    window.location.href = resolveReturnUrl(fallbackHref);
};

export const bindBackButton = (buttonId, fallbackHref) => {
    const button = document.getElementById(buttonId);

    if (!button) {
        return;
    }

    button.addEventListener('click', () => navigateBack(fallbackHref));
};

export const initReturnUrlCapture = () => {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a.table-action-btn--view[data-save-return]');

        if (!link?.href) {
            return;
        }

        try {
            const target = new URL(link.href, window.location.origin);

            if (target.origin !== window.location.origin) {
                return;
            }

            if (target.pathname === window.location.pathname && target.search === window.location.search) {
                return;
            }

            saveReturnUrl();
        } catch {
            // Ignore invalid URLs.
        }
    }, true);
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

const alertDismissTimers = new WeakMap();

const shouldAutoDismissAlert = (type) => type !== 'danger';

const scheduleAlertDismiss = (element, timeoutMs) => {
    const existing = alertDismissTimers.get(element);

    if (existing) {
        window.clearTimeout(existing);
    }

    const timer = window.setTimeout(() => {
        element.classList.remove('show');

        window.setTimeout(() => {
            element.classList.add('d-none');
            element.innerHTML = '';
            alertDismissTimers.delete(element);
        }, 150);
    }, timeoutMs);

    alertDismissTimers.set(element, timer);
};

export const showAutoDismissAlert = (element, message, type = 'success', timeoutMs = 5000) => {
    if (!element) {
        return;
    }

    element.className = `alert alert-${type} alert-dismissible fade show`;
    element.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    element.classList.remove('d-none');

    if (shouldAutoDismissAlert(type)) {
        scheduleAlertDismiss(element, timeoutMs);
    }
};

export const prependAutoDismissAlert = (container, message, type = 'success', {
    timeoutMs = 5000,
    className = '',
} = {}) => {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-auto-dismiss-alert]').forEach((node) => node.remove());

    const alert = document.createElement('div');
    alert.dataset.autoDismissAlert = 'true';
    alert.className = `alert alert-${type} alert-dismissible fade show ${className}`.trim();
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    container.prepend(alert);

    if (shouldAutoDismissAlert(type)) {
        window.setTimeout(() => alert.remove(), timeoutMs);
    }
};
