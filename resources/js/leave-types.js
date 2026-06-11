import api, { getErrorMessage } from './api';
import { applyBackendErrors, clearFormErrors, setFlashMessage, setSubmitLoading } from './form-utils';

const routes = () => window.HRMS_WEB_ROUTES || {};

const parseDurationInput = (value) => {
    if (!value?.trim()) {
        return [60, 120];
    }

    return value
        .split(',')
        .map((item) => Number(item.trim()))
        .filter((item) => Number.isFinite(item) && item > 0);
};

const formatDurationInput = (durations) => (durations || [60, 120]).join(', ');

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('leaveTypeForm');
    const alertBox = document.getElementById('leaveTypeFormAlert');
    const submitBtn = document.getElementById('leaveTypeSubmitBtn');
    const leaveTypeId = form?.dataset.leaveTypeId;
    const isUpdate = Boolean(leaveTypeId);
    const isHourlySelect = form?.querySelector('#is_hourly_leave');
    const hourlyFields = form ? Array.from(form.querySelectorAll('.hourly-leave-fields')) : [];
    const dayFields = form ? Array.from(form.querySelectorAll('.day-leave-fields')) : [];
    const annualQuotaInput = form?.querySelector('#annual_quota');
    const annualQuotaLabel = form?.querySelector('#annualQuotaLabel');

    if (!form) return;

    const showAlert = (message, type = 'danger') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const toggleHourlyFields = () => {
        const enabled = isHourlySelect?.value === '1';
        hourlyFields.forEach((field) => field.classList.toggle('d-none', !enabled));
        dayFields.forEach((field) => field.classList.toggle('d-none', enabled));

        if (annualQuotaLabel) {
            annualQuotaLabel.textContent = enabled ? 'Annual Quota (hours)' : 'Annual Quota (days)';
        }

        if (annualQuotaInput) {
            annualQuotaInput.max = enabled ? '8760' : '365';
            annualQuotaInput.step = enabled ? '1' : '0.5';
        }
    };

    isHourlySelect?.addEventListener('change', toggleHourlyFields);

    const payload = () => {
        const isHourly = isHourlySelect?.value === '1';

        return {
            name: form.querySelector('#name').value.trim(),
            code: form.querySelector('#code').value.trim().toUpperCase(),
            annual_quota: form.querySelector('#annual_quota').value === '' ? null : Number(form.querySelector('#annual_quota').value),
            max_days_per_request: isHourly
                ? (form.querySelector('#hourly_max_days_per_request').value === '' ? null : Number(form.querySelector('#hourly_max_days_per_request').value))
                : (form.querySelector('#max_days_per_request').value === '' ? null : Number(form.querySelector('#max_days_per_request').value)),
            max_days_per_month: isHourly ? null : (form.querySelector('#max_days_per_month').value === '' ? null : Number(form.querySelector('#max_days_per_month').value)),
            is_hourly_leave: isHourly,
            max_hours_per_month: form.querySelector('#max_hours_per_month').value === '' ? null : Number(form.querySelector('#max_hours_per_month').value),
            allowed_hourly_durations: parseDurationInput(form.querySelector('#allowed_hourly_durations').value),
            is_paid: form.querySelector('#is_paid').value === '1',
            requires_proof: form.querySelector('#requires_proof').value === '1',
            color: form.querySelector('#color').value,
            sort_order: Number(form.querySelector('#sort_order').value || 0),
            status: form.querySelector('#status').value,
        };
    };

    if (isUpdate) {
        try {
            const { data } = await api.get(`/leave-types/${leaveTypeId}`);
            const type = data.data.leave_type;
            form.querySelector('#name').value = type.name;
            form.querySelector('#code').value = type.code;
            form.querySelector('#annual_quota').value = type.annual_quota ?? '';
            form.querySelector('#max_days_per_request').value = type.is_hourly_leave ? '' : (type.max_days_per_request ?? '');
            form.querySelector('#max_days_per_month').value = type.max_days_per_month ?? '';
            form.querySelector('#is_paid').value = type.is_paid ? '1' : '0';
            form.querySelector('#requires_proof').value = type.requires_proof ? '1' : '0';
            isHourlySelect.value = type.is_hourly_leave ? '1' : '0';
            form.querySelector('#hourly_max_days_per_request').value = type.is_hourly_leave ? (type.max_days_per_request ?? '') : '';
            form.querySelector('#max_hours_per_month').value = type.max_hours_per_month ?? '';
            form.querySelector('#allowed_hourly_durations').value = formatDurationInput(type.allowed_hourly_durations);
            form.querySelector('#color').value = type.color || '#3b82f6';
            form.querySelector('#sort_order').value = type.sort_order || 0;
            form.querySelector('#status').value = type.status;
            toggleHourlyFields();
        } catch (error) {
            showAlert(getErrorMessage(error));
        }
    } else {
        toggleHourlyFields();
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFormErrors(form);
        try {
            setSubmitLoading(submitBtn, true);
            if (isUpdate) {
                await api.put(`/leave-types/${leaveTypeId}`, payload());
                setFlashMessage('Leave type updated successfully.');
            } else {
                await api.post('/leave-types', payload());
                setFlashMessage('Leave type created successfully.');
            }
            window.location.href = routes().leaveTypesIndex || '/masters/leave-types';
        } catch (error) {
            applyBackendErrors(form, error);
            showAlert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });
});
