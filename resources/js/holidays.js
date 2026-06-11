import api, { getErrorMessage } from './api';
import {
    applyBackendErrors,
    clearFormErrors,
    focusFirstInvalidField,
    setFieldError,
    setFlashMessage,
    setSubmitLoading,
} from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

const typeLabels = {
    public: 'Public',
    company: 'Company',
    optional: 'Optional',
};

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('holidayForm');
    const alertBox = document.getElementById('holidayFormAlert');
    const submitBtn = document.getElementById('holidaySubmitBtn');
    const routes = webRoutes();
    const holidayId = form?.dataset.holidayId;
    const isUpdate = Boolean(holidayId);

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

    const getPayload = () => ({
        name: form.querySelector('#name')?.value.trim() || '',
        date: form.querySelector('#date')?.value || '',
        type: form.querySelector('#type')?.value || 'company',
        status: form.querySelector('#status')?.value || 'active',
        description: form.querySelector('#description')?.value.trim() || null,
    });

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';
        const date = form.querySelector('#date')?.value || '';

        if (!name) {
            setFieldError(form, 'name', 'Holiday name is required.');
            return false;
        }

        if (!date) {
            setFieldError(form, 'date', 'Holiday date is required.');
            return false;
        }

        return true;
    };

    if (holidayId) {
        try {
            const { data } = await api.get(`/holidays/${holidayId}`);
            const holiday = data.data.holiday;

            form.querySelector('#name').value = holiday.name || '';
            form.querySelector('#date').value = holiday.date || '';
            form.querySelector('#type').value = holiday.type || 'company';
            form.querySelector('#status').value = holiday.status || 'active';
            form.querySelector('#description').value = holiday.description || '';
        } catch (error) {
            showAlert(getErrorMessage(error));
        }
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

        try {
            setSubmitLoading(submitBtn, true, { submittingText: isUpdate ? 'Updating...' : 'Saving...' });
            const payload = getPayload();

            if (isUpdate) {
                await api.put(`/holidays/${holidayId}`, payload);
                setFlashMessage('Holiday updated successfully.');
            } else {
                await api.post('/holidays', payload);
                setFlashMessage('Holiday created successfully.');
            }

            window.location.href = routes.holidaysIndex || '/masters/attendance/holidays';
        } catch (error) {
            applyBackendErrors(form, error);
            showAlert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
            submitBtn.textContent = isUpdate ? 'Update Holiday' : 'Save Holiday';
            isSubmitting = false;
        }
    });
});

export { typeLabels };
