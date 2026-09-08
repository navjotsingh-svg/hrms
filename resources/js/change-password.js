import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', () => {
    const passwordForm = document.getElementById('passwordForm');
    const alertBox = document.getElementById('changePasswordAlert');

    if (!passwordForm) {
        return;
    }

    const statusEl = document.getElementById('passwordSaveStatus');
    const submitBtn = passwordForm.querySelector('button[type="submit"]');
    let isSubmitting = false;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        isSubmitting = true;
        setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });

        if (statusEl) {
            statusEl.classList.add('d-none');
        }

        alertBox?.classList.add('d-none');

        try {
            const formData = new FormData(passwordForm);
            const { data } = await api.put('/profile/password', {
                current_password: formData.get('current_password'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
            });

            passwordForm.reset();
            showAlert(data.message || 'Password updated successfully.');

            if (statusEl) {
                statusEl.textContent = data.message || 'Password updated successfully.';
                statusEl.classList.remove('d-none');
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(submitBtn, false);
        }
    });
});
