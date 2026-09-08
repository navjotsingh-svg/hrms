import api, { getErrorMessage } from './api';
import {
    applyBackendErrors,
    clearFormErrors,
    focusFirstInvalidField,
    getStatusValue,
    hasValidDateYear,
    setFieldError,
    setFlashMessage,
    setStatusValue,
    setSubmitLoading,
} from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

export const typeLabels = {
    public: 'Public',
    company: 'Company',
    optional: 'Optional',
    other: 'Other',
};

export const frequencyLabels = {
    fixed: 'Fixed',
    variable: 'Variable',
};

const FOUR_DIGIT_YEAR = /^\d{4}-\d{2}-\d{2}$/;

const daysInMonth = (month) => new Date(2000, month, 0).getDate();

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

    const frequencyInput = form.querySelector('#frequency');
    const durationInput = form.querySelector('#duration');

    const fieldGroups = {
        fixed: form.querySelectorAll('.holiday-field--fixed'),
        fixedRange: form.querySelectorAll('.holiday-field--fixed-range'),
        variableSingle: form.querySelectorAll('.holiday-field--variable-single'),
        variableRange: form.querySelectorAll('.holiday-field--variable-range'),
    };

    const startLabels = form.querySelectorAll('.holiday-start-label');
    const fixedHint = form.querySelector('.holiday-fixed-hint');

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const setGroupVisible = (nodes, visible) => {
        nodes.forEach((node) => {
            node.classList.toggle('d-none', !visible);
        });
    };

    const updateFieldVisibility = () => {
        const frequency = frequencyInput?.value || 'fixed';
        const duration = durationInput?.value || 'single';
        const isFixed = frequency === 'fixed';
        const isRange = duration === 'range';

        setGroupVisible(fieldGroups.fixed, isFixed);
        setGroupVisible(fieldGroups.fixedRange, isFixed && isRange);
        setGroupVisible(fieldGroups.variableSingle, !isFixed && !isRange);
        setGroupVisible(fieldGroups.variableRange, !isFixed && isRange);

        startLabels.forEach((label) => {
            label.textContent = isFixed && isRange ? 'Start Day' : 'Day';
        });

        if (fixedHint) {
            fixedHint.textContent = isFixed && isRange
                ? 'Start date repeats every year — no year needed.'
                : 'Repeats every year — no year needed.';
        }
    };

    const validateFourDigitDate = (value, fieldName, label) => {
        if (!value) {
            setFieldError(form, fieldName, `${label} is required.`);
            return false;
        }

        if (!FOUR_DIGIT_YEAR.test(value) || !hasValidDateYear(value)) {
            setFieldError(form, fieldName, DATE_INVALID_MESSAGE);
            return false;
        }

        return true;
    };

    const validateDayMonth = (dayValue, monthValue, dayField, monthField, prefix = '') => {
        const day = Number(dayValue);
        const month = Number(monthValue);
        let valid = true;

        if (!month || month < 1 || month > 12) {
            setFieldError(form, monthField, `${prefix}Month is required.`);
            valid = false;
        }

        if (!day || day < 1) {
            setFieldError(form, dayField, `${prefix}Day is required.`);
            valid = false;
        } else if (month >= 1 && month <= 12 && day > daysInMonth(month)) {
            setFieldError(form, dayField, `Invalid day for the selected month. Maximum is ${daysInMonth(month)}.`);
            valid = false;
        }

        return valid;
    };

    const getPayload = () => {
        const frequency = frequencyInput?.value || 'fixed';
        const duration = durationInput?.value || 'single';

        const payload = {
            name: form.querySelector('#name')?.value.trim() || '',
            frequency,
            duration,
            type: form.querySelector('#type')?.value || 'company',
            status: getStatusValue(form),
            description: form.querySelector('#description')?.value.trim() || null,
        };

        if (frequency === 'fixed') {
            payload.start_day = Number(form.querySelector('#start_day')?.value);
            payload.start_month = Number(form.querySelector('#start_month')?.value);

            if (duration === 'range') {
                payload.end_day = Number(form.querySelector('#end_day')?.value);
                payload.end_month = Number(form.querySelector('#end_month')?.value);
            }
        } else if (duration === 'single') {
            payload.holiday_date = form.querySelector('#holiday_date')?.value || '';
        } else {
            payload.from_date = form.querySelector('#from_date')?.value || '';
            payload.to_date = form.querySelector('#to_date')?.value || '';
        }

        return payload;
    };

    const validateForm = () => {
        clearFormErrors(form);

        const name = form.querySelector('#name')?.value.trim() || '';
        const frequency = frequencyInput?.value || 'fixed';
        const duration = durationInput?.value || 'single';

        if (!name) {
            setFieldError(form, 'name', 'Holiday name is required.');
            return false;
        }

        if (frequency === 'fixed') {
            const startValid = validateDayMonth(
                form.querySelector('#start_day')?.value,
                form.querySelector('#start_month')?.value,
                'start_day',
                'start_month',
                duration === 'range' ? 'Start ' : '',
            );

            if (!startValid) {
                return false;
            }

            if (duration === 'range') {
                if (!validateDayMonth(
                    form.querySelector('#end_day')?.value,
                    form.querySelector('#end_month')?.value,
                    'end_day',
                    'end_month',
                    'End ',
                )) {
                    return false;
                }

                const startMonth = Number(form.querySelector('#start_month')?.value);
                const startDay = Number(form.querySelector('#start_day')?.value);
                const endMonth = Number(form.querySelector('#end_month')?.value);
                const endDay = Number(form.querySelector('#end_day')?.value);

                const rangeValid = endMonth > startMonth
                    || endMonth < startMonth
                    || (endMonth === startMonth && endDay >= startDay);

                if (!rangeValid) {
                    setFieldError(form, 'end_day', 'End date must be after start date within the holiday period.');
                    return false;
                }
            }

            return true;
        }

        if (duration === 'single') {
            return validateFourDigitDate(form.querySelector('#holiday_date')?.value, 'holiday_date', 'Date');
        }

        const fromDate = form.querySelector('#from_date')?.value || '';
        const toDate = form.querySelector('#to_date')?.value || '';

        if (!validateFourDigitDate(fromDate, 'from_date', 'From date')) {
            return false;
        }

        if (!validateFourDigitDate(toDate, 'to_date', 'To date')) {
            return false;
        }

        if (toDate < fromDate) {
            setFieldError(form, 'to_date', 'To date must be on or after from date.');
            return false;
        }

        return true;
    };

    const populateForm = (holiday) => {
        form.querySelector('#name').value = holiday.name || '';
        form.querySelector('#frequency').value = holiday.frequency || 'fixed';
        form.querySelector('#duration').value = holiday.duration || 'single';
        form.querySelector('#type').value = holiday.type || 'company';
        setStatusValue(form, holiday.status || 'active');
        form.querySelector('#description').value = holiday.description || '';

        if (holiday.frequency === 'fixed') {
            form.querySelector('#start_day').value = holiday.start_day || '';
            form.querySelector('#start_month').value = holiday.start_month || '';

            if (holiday.duration === 'range') {
                form.querySelector('#end_day').value = holiday.end_day || '';
                form.querySelector('#end_month').value = holiday.end_month || '';
            }
        } else if (holiday.duration === 'single') {
            form.querySelector('#holiday_date').value = holiday.holiday_date || holiday.from_date || '';
        } else {
            form.querySelector('#from_date').value = holiday.from_date || '';
            form.querySelector('#to_date').value = holiday.to_date || '';
        }

        updateFieldVisibility();
    };

    frequencyInput?.addEventListener('change', updateFieldVisibility);
    durationInput?.addEventListener('change', updateFieldVisibility);

    form.querySelector('#from_date')?.addEventListener('change', (event) => {
        const toInput = form.querySelector('#to_date');
        if (toInput && !toInput.value) {
            toInput.value = event.target.value;
        }
    });

    updateFieldVisibility();

    if (holidayId) {
        try {
            const { data } = await api.get(`/holidays/${holidayId}`);
            populateForm(data.data.holiday);
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
