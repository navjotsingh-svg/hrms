import api, { getErrorMessage } from './api';
import { applyBackendErrors, clearFormErrors, getStatusValue, setFlashMessage, setStatusValue, setSubmitLoading } from './form-utils';

const routes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('leaveTypeForm');
    const alertBox = document.getElementById('leaveTypeFormAlert');
    const submitBtn = document.getElementById('leaveTypeSubmitBtn');
    const leaveTypeId = form?.dataset.leaveTypeId;
    const isUpdate = Boolean(leaveTypeId);

    let preserveHourlyConfig = null;

    if (!form) {
        return;
    }

    const showAlert = (message, type = 'danger') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const payload = () => {
        const data = {
            name: form.querySelector('#name').value.trim(),
            code: form.querySelector('#code').value.trim().toUpperCase(),
            annual_quota: form.querySelector('#annual_quota').value === '' ? null : Number(form.querySelector('#annual_quota').value),
            max_days_per_request: form.querySelector('#max_days_per_request').value === '' ? null : Number(form.querySelector('#max_days_per_request').value),
            max_days_per_month: form.querySelector('#max_days_per_month').value === '' ? null : Number(form.querySelector('#max_days_per_month').value),
            is_hourly_leave: false,
            max_hours_per_month: null,
            allowed_hourly_durations: null,
            is_paid: form.querySelector('#is_paid').value === '1',
            requires_proof: form.querySelector('#requires_proof').value === '1',
            color: form.querySelector('#color').value,
            sort_order: Number(form.querySelector('#sort_order').value || 0),
            status: getStatusValue(form),
        };

        if (preserveHourlyConfig) {
            return {
                ...data,
                is_hourly_leave: true,
                annual_quota: preserveHourlyConfig.annual_quota,
                max_days_per_request: preserveHourlyConfig.max_days_per_request,
                max_days_per_month: null,
                max_hours_per_month: preserveHourlyConfig.max_hours_per_month,
                allowed_hourly_durations: preserveHourlyConfig.allowed_hourly_durations,
            };
        }

        return data;
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
            form.querySelector('#color').value = type.color || '#3b82f6';
            form.querySelector('#sort_order').value = type.sort_order || 0;
            setStatusValue(form, type.status || 'active');

            if (type.is_hourly_leave) {
                preserveHourlyConfig = {
                    annual_quota: type.annual_quota,
                    max_days_per_request: type.max_days_per_request,
                    max_hours_per_month: type.max_hours_per_month,
                    allowed_hourly_durations: type.allowed_hourly_durations,
                };
            }
        } catch (error) {
            showAlert(getErrorMessage(error));
        }
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
