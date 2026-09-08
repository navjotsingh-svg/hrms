import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('weeklyOffForm');
    const alertBox = document.getElementById('weeklyOffAlert');
    const submitBtn = document.getElementById('weeklyOffSubmitBtn');

    if (!form) {
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

    const getSelectedWeekdays = () => Array.from(form.querySelectorAll('input[name="weekdays[]"]:checked'))
        .map((input) => Number(input.value));

    try {
        const { data } = await api.get('/weekly-off');
        const weekdays = data.data.weekdays || [];

        form.querySelectorAll('input[name="weekdays[]"]').forEach((input) => {
            input.checked = weekdays.includes(Number(input.value));
        });
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        isSubmitting = true;

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });
            const { data } = await api.put('/weekly-off', { weekdays: getSelectedWeekdays() });
            showAlert(data.message || 'Weekly off days updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(submitBtn, false);
            submitBtn.textContent = 'Save Weekly Off';
            isSubmitting = false;
        }
    });
});
