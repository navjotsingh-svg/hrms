import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('portalStartForm');
    const alertBox = document.getElementById('portalStartAlert');
    const dateInput = document.getElementById('attendance_portal_start_date');
    const statusText = document.getElementById('portalStartStatus');
    const submitBtn = document.getElementById('portalStartSubmitBtn');
    const clearBtn = document.getElementById('portalStartClearBtn');

    if (!form || !dateInput) {
        return;
    }

    let isSubmitting = false;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const updateStatusText = (payload) => {
        if (!statusText) {
            return;
        }

        if (!payload.is_configured) {
            statusText.textContent = 'Not configured yet. Attendance is tracked without a portal start restriction.';
            return;
        }

        statusText.textContent = `Attendance is tracked from ${payload.attendance_portal_start_date_label} onward. Earlier dates stay blank. After that, each employee is tracked from their portal access date (or joining date, whichever is later). Unmarked working days show as absent.`;
    };

    const validateYear = () => {
        const value = dateInput.value;

        if (!value) {
            dateInput.setCustomValidity('');
            return true;
        }

        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            dateInput.setCustomValidity('Date must use a 4-digit year (YYYY-MM-DD).');
            return false;
        }

        dateInput.setCustomValidity('');
        return true;
    };

    dateInput.addEventListener('input', validateYear);

    try {
        const { data } = await api.get('/portal-start');
        const payload = data.data;

        dateInput.value = payload.attendance_portal_start_date || '';
        updateStatusText(payload);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    clearBtn?.addEventListener('click', async () => {
        if (isSubmitting) {
            return;
        }

        if (!window.confirm('Clear portal start day? Attendance will no longer hide dates before a start day.')) {
            return;
        }

        isSubmitting = true;

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Clearing...' });
            const { data } = await api.put('/portal-start', { attendance_portal_start_date: null });
            dateInput.value = '';
            updateStatusText(data.data);
            showAlert(data.message || 'Portal start day cleared.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(submitBtn, false);
            submitBtn.textContent = 'Save Portal Start Day';
            isSubmitting = false;
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        if (!validateYear()) {
            dateInput.reportValidity();
            return;
        }

        isSubmitting = true;

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });
            const { data } = await api.put('/portal-start', {
                attendance_portal_start_date: dateInput.value || null,
            });

            updateStatusText(data.data);
            showAlert(data.message || 'Portal start day updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(submitBtn, false);
            submitBtn.textContent = 'Save Portal Start Day';
            isSubmitting = false;
        }
    });
});
