import api, { getErrorMessage } from './api';
import {
    applyBackendErrors,
    clearFormErrors,
    focusFirstInvalidField,
    getStatusValue,
    setFieldError,
    setFlashMessage,
    setStatusValue,
    setSubmitLoading,
} from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('shiftForm');
    const alertBox = document.getElementById('shiftFormAlert');
    const submitBtn = document.getElementById('shiftSubmitBtn');
    const routes = webRoutes();
    const shiftId = form?.dataset.shiftId;
    const isUpdate = Boolean(shiftId);

    if (!form) {
        return;
    }

    let isSubmitting = false;

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const detectOvernight = () => {
        const start = form.querySelector('#start_time')?.value;
        const end = form.querySelector('#end_time')?.value;
        const overnightInput = form.querySelector('#is_overnight');

        if (!start || !end || !overnightInput || overnightInput.dataset.manualEdit) {
            return;
        }

        overnightInput.checked = end <= start;
    };

    const getPayload = () => ({
        name: form.querySelector('#name')?.value.trim() || '',
        code: form.querySelector('#code')?.value.trim() || null,
        start_time: form.querySelector('#start_time')?.value || '',
        end_time: form.querySelector('#end_time')?.value || '',
        break_duration_minutes: Number(form.querySelector('#break_duration_minutes')?.value || 0),
        is_overnight: form.querySelector('#is_overnight')?.checked ?? false,
        description: form.querySelector('#description')?.value.trim() || null,
        status: getStatusValue(form),
    });

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';
        const startTime = form.querySelector('#start_time')?.value || '';
        const endTime = form.querySelector('#end_time')?.value || '';
        const isOvernight = form.querySelector('#is_overnight')?.checked ?? false;

        if (!name) {
            setFieldError(form, 'name', 'Shift name is required.');
            return false;
        }

        if (!startTime) {
            setFieldError(form, 'start_time', 'Start time is required.');
            return false;
        }

        if (!endTime) {
            setFieldError(form, 'end_time', 'End time is required.');
            return false;
        }

        if (!isOvernight && endTime <= startTime) {
            setFieldError(form, 'end_time', 'End time must be after start time, or mark as overnight shift.');
            return false;
        }

        return true;
    };

    form.querySelector('#start_time')?.addEventListener('change', detectOvernight);
    form.querySelector('#end_time')?.addEventListener('change', detectOvernight);
    form.querySelector('#is_overnight')?.addEventListener('change', (event) => {
        event.target.dataset.manualEdit = '1';
    });

    if (shiftId) {
        try {
            const { data } = await api.get(`/shifts/${shiftId}`);
            const shift = data.data.shift;

            form.querySelector('#name').value = shift.name || '';
            form.querySelector('#code').value = shift.code || '';
            form.querySelector('#start_time').value = shift.start_time || '';
            form.querySelector('#end_time').value = shift.end_time || '';
            form.querySelector('#break_duration_minutes').value = shift.break_duration_minutes ?? 0;
            form.querySelector('#is_overnight').checked = Boolean(shift.is_overnight);
            form.querySelector('#description').value = shift.description || '';
            setStatusValue(form, shift.status || 'active');
        } catch (error) {
            showAlert(getErrorMessage(error));
        }
    } else {
        form.querySelector('#start_time').value = '09:00';
        form.querySelector('#end_time').value = '18:00';
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        if (!validateForm()) {
            showAlert('Please fix the highlighted errors before submitting.');
            focusFirstInvalidField(form);
            return;
        }

        isSubmitting = true;
        setSubmitLoading(submitBtn, true, { isUpdate });

        try {
            const payload = getPayload();
            const response = shiftId
                ? await api.put(`/shifts/${shiftId}`, payload)
                : await api.post('/shifts', payload);

            const message = response.data.message || 'Shift saved successfully.';
            setFlashMessage(message);
            window.location.href = `${routes.shiftsIndex || '/masters/shifts'}`;
        } catch (error) {
            applyBackendErrors(form, error.response?.data?.errors, setFieldError);
            showAlert(getErrorMessage(error));
            isSubmitting = false;
            setSubmitLoading(submitBtn, false, { isUpdate });
            focusFirstInvalidField(form);
        }
    });
});
