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

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('departmentForm');
    const alertBox = document.getElementById('departmentFormAlert');
    const submitBtn = document.getElementById('departmentSubmitBtn');
    const routes = webRoutes();
    const departmentId = form?.dataset.departmentId;
    const isUpdate = Boolean(departmentId);

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
        code: form.querySelector('#code')?.value.trim() || null,
        description: form.querySelector('#description')?.value.trim() || null,
        status: form.querySelector('#status')?.value || 'active',
    });

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';

        if (!name) {
            setFieldError(form, 'name', 'Department name is required.');
            return false;
        }

        return true;
    };

    if (departmentId) {
        try {
            const { data } = await api.get(`/departments/${departmentId}`);
            const department = data.data.department;

            form.querySelector('#name').value = department.name || '';
            form.querySelector('#code').value = department.code || '';
            form.querySelector('#description').value = department.description || '';
            form.querySelector('#status').value = department.status || 'active';
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
        setSubmitLoading(submitBtn, true, { isUpdate });

        try {
            const payload = getPayload();
            const response = departmentId
                ? await api.put(`/departments/${departmentId}`, payload)
                : await api.post('/departments', payload);

            const message = response.data.message || 'Department saved successfully.';
            setFlashMessage(message);
            window.location.href = routes.departmentsIndex || '/masters/departments';
        } catch (error) {
            applyBackendErrors(form, error.response?.data?.errors, setFieldError);
            showAlert(getErrorMessage(error));
            isSubmitting = false;
            setSubmitLoading(submitBtn, false, { isUpdate });
            focusFirstInvalidField(form);
        }
    });
});
