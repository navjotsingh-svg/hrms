import api, { getErrorMessage } from './api';
import { applyBackendErrors, clearFormErrors, setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('resignationForm');
    const alertBox = document.getElementById('offboardingApplyAlert');
    const submitBtn = document.getElementById('resignationSubmitBtn');

    if (!form) return;

    const showAlert = (message, type = 'danger') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFormErrors(form);
        alertBox.classList.add('d-none');

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });
            await api.post('/resignation-requests', {
                proposed_last_working_date: form.querySelector('#proposed_last_working_date').value,
                notice_period_days: form.querySelector('#notice_period_days').value || null,
                reason: form.querySelector('#reason').value.trim(),
            });

            const indexUrl = window.HRMS_WEB_ROUTES?.offboardingIndex || '/offboarding';
            window.location.href = `${indexUrl}?status=pending`;
        } catch (error) {
            applyBackendErrors(form, error);
            showAlert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });
});
