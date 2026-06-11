import api, { getErrorMessage } from './api';
import { applyBackendErrors, clearFormErrors, setSubmitLoading } from './form-utils';
import { compressImageFiles } from './image-compress';

const formatDurationLabel = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    if (hours === 0) {
        return `${remaining} min`;
    }

    if (remaining === 0) {
        return hours === 1 ? '1 hour' : `${hours} hours`;
    }

    return `${hours}h ${remaining}m`;
};

const renderBalances = (container, balances) => {
    if (!balances.length) {
        container.innerHTML = '<div class="text-muted">No balances available.</div>';
        return;
    }

    container.innerHTML = balances.map((item) => {
        const unit = item.balance_unit === 'hours' ? 'hour(s)' : 'day(s)';
        const quotaUnit = item.leave_type?.quota_unit === 'hours' ? 'hours' : 'days';

        return `
        <div class="border rounded p-3 mb-3">
            <div class="fw-semibold">${item.leave_type.name} (${item.leave_type.code})</div>
            <div class="small text-muted mt-1">
                ${item.is_comp_off || item.leave_type?.is_comp_off
        ? `Comp off credited: <strong>${item.adjusted}</strong> · Available: <strong>${item.available ?? 0}</strong>`
        : `Quota: <strong>${item.leave_type.annual_quota ?? 'Unlimited'}</strong> ${quotaUnit} · Allocated: <strong>${item.allocated}</strong> ${unit} · Available: <strong>${item.available ?? 'Unlimited'}</strong> ${item.available != null ? unit : ''}`}
                · Used: <strong>${item.used}</strong> ${unit} · Pending: <strong>${item.pending}</strong> ${unit}
            </div>
        </div>
    `;
    }).join('');
};

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('leaveForm');
    const alertBox = document.getElementById('leaveFormAlert');
    const submitBtn = document.getElementById('leaveSubmitBtn');
    const typeSelect = document.getElementById('leave_type_id');
    const balanceCards = document.getElementById('leaveBalanceCards');
    const proofInput = document.getElementById('proofs');
    const proofPreview = document.getElementById('proofPreview');
    const policyHint = document.getElementById('leaveTypePolicyHint');
    const sessionSelect = document.getElementById('session');
    const sessionWrap = document.getElementById('sessionWrap');
    const sessionHelpText = document.getElementById('sessionHelpText');
    const hourlyDurationWrap = document.getElementById('hourlyDurationWrap');
    const durationSelect = document.getElementById('duration_minutes');
    const hourlyDeductionHint = document.getElementById('hourlyDeductionHint');
    const fromDateInput = document.getElementById('from_date');
    const toDateInput = document.getElementById('to_date');
    const daysPreview = document.getElementById('leaveDaysPreview');
    let leaveTypes = [];
    let previewTimer = null;
    let lastPreview = null;

    if (!form) return;

    const showAlert = (message, type = 'danger') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const selectedLeaveType = () => leaveTypes.find((item) => String(item.id) === typeSelect.value);

    const isHourlyType = () => Boolean(selectedLeaveType()?.is_hourly_leave);

    const syncDateFields = () => {
        if (!isHourlyType()) {
            toDateInput.readOnly = false;
            return;
        }

        toDateInput.value = fromDateInput.value;
        toDateInput.readOnly = true;
    };

    const updateDurationOptions = () => {
        const type = selectedLeaveType();
        const durations = type?.allowed_hourly_durations || [60, 120];

        durationSelect.innerHTML = durations.map((minutes) => `
            <option value="${minutes}">${formatDurationLabel(minutes)}</option>
        `).join('');

        updateHourlyHint();
    };

    const updateHourlyHint = () => {
        const type = selectedLeaveType();
        const minutes = Number(durationSelect.value || 0);

        if (!type || !minutes) {
            hourlyDeductionHint.textContent = '';
            return;
        }

        const hours = (minutes / 60).toFixed(2).replace(/\.?0+$/, '');
        hourlyDeductionHint.textContent = `Deducts ${hours} hour(s) from your ${type.name} balance.`;
    };

    const updateSessionUi = () => {
        const hourly = isHourlyType();

        sessionWrap?.classList.toggle('d-none', hourly);
        hourlyDurationWrap.classList.toggle('d-none', !hourly);
        sessionHelpText.textContent = hourly
            ? 'Short leave applies to a single date only.'
            : 'Half-day applies only when from and to date are the same.';

        if (hourly) {
            sessionSelect.value = 'hourly';
            syncDateFields();
            updateDurationOptions();
        } else {
            if (sessionSelect.value === 'hourly') {
                sessionSelect.value = 'full_day';
            }
            toDateInput.readOnly = false;
        }
    };

    const loadBalances = async () => {
        try {
            const { data } = await api.get('/leave-balances/me');
            renderBalances(balanceCards, data.data.balances || []);
        } catch {
            balanceCards.innerHTML = '<div class="text-muted">Unable to load balances.</div>';
        }
    };

    const updatePolicyHint = () => {
        const type = selectedLeaveType();

        if (!policyHint) return;

        if (!type) {
            policyHint.classList.add('d-none');
            policyHint.textContent = '';
            updateSessionUi();
            return;
        }

        let hint = type.application_policy_label || '';

        if (type.monthly_remaining !== null && type.monthly_remaining !== undefined) {
            const unit = type.monthly_limit_unit === 'hours' ? 'hour(s)' : 'day(s)';
            const monthLabel = fromDateInput?.value
                ? new Date(`${fromDateInput.value}T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
                : 'This month';
            hint += ` · ${monthLabel}: ${type.monthly_used ?? 0} used, ${type.monthly_remaining} ${unit} remaining`;
        }

        if (type.requires_proof) {
            hint += hint ? ' · ' : '';
            hint += 'Proof required before approval — you can upload now or later from the leave request page';
        }

        policyHint.textContent = hint;
        policyHint.classList.toggle('d-none', !hint);
        updateSessionUi();
    };

    const monthParamsFromDate = (dateValue) => {
        if (!dateValue) {
            return {};
        }

        const date = new Date(`${dateValue}T00:00:00`);

        return {
            year: date.getFullYear(),
            month: date.getMonth() + 1,
        };
    };

    const loadTypes = async (referenceDate = null) => {
        try {
            const params = referenceDate
                ? { year: referenceDate.getFullYear(), month: referenceDate.getMonth() + 1 }
                : monthParamsFromDate(fromDateInput?.value);
            const { data } = await api.get('/leave-types/options', { params });
            const selectedId = typeSelect.value;
            leaveTypes = data.data.leave_types || [];
            typeSelect.innerHTML = '<option value="">Select leave type</option>' + leaveTypes.map((type) => `
                <option value="${type.id}" data-requires-proof="${type.requires_proof ? '1' : '0'}">${type.name} (${type.code})</option>
            `).join('');

            if (selectedId) {
                typeSelect.value = selectedId;
            }

            updatePolicyHint();
        } catch (error) {
            showAlert(getErrorMessage(error));
        }
    };

    const flattenPreviewErrors = (errors = {}) => Object.values(errors).flat().join(' ');

    const renderDaysPreview = (preview) => {
        if (!daysPreview) {
            return;
        }

        if (!preview) {
            daysPreview.classList.add('d-none');
            daysPreview.textContent = '';
            daysPreview.classList.remove('text-danger', 'text-success');
            return;
        }

        const unit = isHourlyType() ? 'hour(s)' : 'working day(s)';
        const countLabel = isHourlyType()
            ? preview.working_days
            : `${preview.working_days} ${unit}`;

        if (!preview.valid) {
            daysPreview.className = 'form-text text-danger';
            daysPreview.textContent = `${flattenPreviewErrors(preview.errors)} (${countLabel} in selected range)`;
            return;
        }

        daysPreview.className = 'form-text text-success';
        daysPreview.textContent = isHourlyType()
            ? `Deducts ${countLabel} from your balance.`
            : `This request uses ${countLabel}. Weekends and holidays are excluded.`;
    };

    const runPreview = async () => {
        if (!typeSelect.value || !fromDateInput.value || !toDateInput.value) {
            lastPreview = null;
            renderDaysPreview(null);
            return;
        }

        try {
            const payload = {
                leave_type_id: Number(typeSelect.value),
                from_date: fromDateInput.value,
                to_date: toDateInput.value,
                session: isHourlyType() ? 'hourly' : sessionSelect.value,
            };

            if (isHourlyType()) {
                payload.duration_minutes = Number(durationSelect.value);
            }

            const { data } = await api.post('/leave-requests/preview', payload);
            lastPreview = data.data.preview;
            renderDaysPreview(lastPreview);
        } catch {
            lastPreview = null;
            renderDaysPreview(null);
        }
    };

    const schedulePreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 300);
    };

    const refreshMonthlyContext = async () => {
        const fromValue = fromDateInput?.value;

        if (fromValue) {
            await loadTypes(new Date(`${fromValue}T00:00:00`));
        } else {
            await loadTypes();
        }

        schedulePreview();
    };

    typeSelect?.addEventListener('change', () => {
        updatePolicyHint();
        schedulePreview();
    });
    sessionSelect?.addEventListener('change', () => {
        updateSessionUi();
        schedulePreview();
    });
    durationSelect?.addEventListener('change', () => {
        updateHourlyHint();
        schedulePreview();
    });
    fromDateInput?.addEventListener('change', () => {
        syncDateFields();
        refreshMonthlyContext();
    });
    toDateInput?.addEventListener('change', schedulePreview);

    proofInput?.addEventListener('change', () => {
        const files = Array.from(proofInput.files || []);
        proofPreview.textContent = files.length
            ? `${files.length} file(s) selected: ${files.map((f) => f.name).join(', ')}`
            : '';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFormErrors(form);

        await runPreview();

        if (lastPreview && !lastPreview.valid) {
            applyBackendErrors(form, { response: { data: { errors: lastPreview.errors } } });
            showAlert(flattenPreviewErrors(lastPreview.errors));
            return;
        }

        const files = Array.from(proofInput?.files || []);

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Compressing files...' });
            const preparedFiles = await compressImageFiles(files);

            const formData = new FormData();
            formData.append('leave_type_id', typeSelect.value);
            formData.append('from_date', fromDateInput.value);
            formData.append('to_date', toDateInput.value);
            formData.append('session', isHourlyType() ? 'hourly' : sessionSelect.value);
            if (isHourlyType()) {
                formData.append('duration_minutes', durationSelect.value);
            }
            formData.append('reason', form.querySelector('#reason').value.trim());
            preparedFiles.forEach((file) => formData.append('proofs[]', file));

            setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });
            const { data } = await api.post('/leave-requests', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            window.location.href = `${window.HRMS_WEB_ROUTES?.leaveShow || '/leave'}/${data.data.leave_request.id}`;
        } catch (error) {
            applyBackendErrors(form, error);
            showAlert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    await Promise.all([loadTypes(), loadBalances()]);
});
