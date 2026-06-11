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
    const form = document.getElementById('assetForm');
    const alertBox = document.getElementById('assetFormAlert');
    const submitBtn = document.getElementById('assetSubmitBtn');
    const routes = webRoutes();
    const assetTypeId = form?.dataset.assetTypeId;
    const isUpdate = Boolean(assetTypeId);

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
        status: form.querySelector('#status')?.value || 'active',
    });

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';

        if (!name) {
            setFieldError(form, 'name', 'Asset name is required.');
            return false;
        }

        return true;
    };

    if (assetTypeId) {
        try {
            const { data } = await api.get(`/asset-types/${assetTypeId}`);
            const assetType = data.data.asset_type;

            form.querySelector('#name').value = assetType.name || '';
            form.querySelector('#status').value = assetType.status || 'active';
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
            const response = assetTypeId
                ? await api.put(`/asset-types/${assetTypeId}`, payload)
                : await api.post('/asset-types', payload);

            const message = response.data.message || 'Asset saved successfully.';
            setFlashMessage(message);
            window.location.href = routes.assetsIndex || '/masters/assets';
        } catch (error) {
            applyBackendErrors(form, error.response?.data?.errors, setFieldError);
            showAlert(getErrorMessage(error));
            isSubmitting = false;
            setSubmitLoading(submitBtn, false, { isUpdate });
            focusFirstInvalidField(form);
        }
    });
});
