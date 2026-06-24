import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('portalStartForm');
    const alertBox = document.getElementById('portalStartAlert');
    const dateInput = document.getElementById('attendance_portal_start_date');
    const statusText = document.getElementById('portalStartStatus');
    const submitBtn = document.getElementById('portalStartSubmitBtn');

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

    const lockForm = () => {
        dateInput.readOnly = true;
        dateInput.classList.add('bg-light');
        submitBtn?.classList.add('d-none');
    };

    const updateStatusText = (payload) => {
        if (!statusText) {
            return;
        }

        if (!payload.is_configured) {
            statusText.textContent = 'Not configured yet. Set the portal start date once — it cannot be changed afterward.';
            return;
        }

        statusText.textContent = `Attendance is tracked from ${payload.attendance_portal_start_date_label} onward. This date is locked and cannot be modified. After that, each employee is tracked from the latest of this date and their joining date. Unmarked working days show as absent.`;
    };

    const applyPayload = (payload) => {
        dateInput.value = payload.attendance_portal_start_date || '';
        updateStatusText(payload);

        if (payload.is_locked || payload.is_configured) {
            lockForm();
        }
    };

    const validateYear = () => {
        const value = dateInput.value;

        if (!value) {
            dateInput.setCustomValidity('Portal start date is required.');
            return false;
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
        applyPayload(data.data);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting || dateInput.readOnly) {
            return;
        }

        if (!validateYear()) {
            dateInput.reportValidity();
            return;
        }

        if (!window.confirm('Save portal start day? This date cannot be changed once saved.')) {
            return;
        }

        isSubmitting = true;

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });
            const { data } = await api.put('/portal-start', {
                attendance_portal_start_date: dateInput.value,
            });

            applyPayload(data.data);
            showAlert(data.message || 'Portal start day saved successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(submitBtn, false);
            if (submitBtn) {
                submitBtn.textContent = 'Save Portal Start Day';
            }
            isSubmitting = false;
        }
    });
});
