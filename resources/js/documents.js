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
    const form = document.getElementById('documentForm');
    const alertBox = document.getElementById('documentFormAlert');
    const submitBtn = document.getElementById('documentSubmitBtn');
    const routes = webRoutes();
    const documentTypeId = form?.dataset.documentTypeId;
    const isUpdate = Boolean(documentTypeId);

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
        is_required: form.querySelector('#is_required')?.checked ?? false,
        allow_multiple: form.querySelector('#allow_multiple')?.value === '1',
        status: form.querySelector('#status')?.value || 'active',
    });

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';

        if (!name) {
            setFieldError(form, 'name', 'Document name is required.');
            return false;
        }

        return true;
    };

    if (documentTypeId) {
        try {
            const { data } = await api.get(`/document-types/${documentTypeId}`);
            const documentType = data.data.document_type;

            form.querySelector('#name').value = documentType.name || '';
            form.querySelector('#code').value = documentType.code || '';
            form.querySelector('#description').value = documentType.description || '';
            form.querySelector('#status').value = documentType.status || 'active';
            form.querySelector('#is_required').checked = Boolean(documentType.is_required);
            form.querySelector('#allow_multiple').value = documentType.allow_multiple ? '1' : '0';
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
            const response = documentTypeId
                ? await api.put(`/document-types/${documentTypeId}`, payload)
                : await api.post('/document-types', payload);

            const message = response.data.message || 'Document type saved successfully.';
            setFlashMessage(message);
            window.location.href = routes.documentsIndex || '/masters/documents';
        } catch (error) {
            applyBackendErrors(form, error.response?.data?.errors, setFieldError);
            showAlert(getErrorMessage(error));
            isSubmitting = false;
            setSubmitLoading(submitBtn, false, { isUpdate });
            focusFirstInvalidField(form);
        }
    });
});
