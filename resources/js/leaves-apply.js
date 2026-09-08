import api, { getErrorMessage } from './api';
import { applyBackendErrors, clearFormErrors, setSubmitLoading } from './form-utils';
import { compressImageFiles } from './image-compress';

const renderBalances = (container, balances) => {
    const dayBalances = balances.filter((item) => !item.leave_type?.is_hourly_leave);

    if (!dayBalances.length) {
        container.innerHTML = '<div class="text-muted">No balances available.</div>';
        return;
    }

    container.innerHTML = dayBalances.map((item) => {
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
    const paidLeaveRestrictionNotice = document.getElementById('paidLeaveRestrictionNotice');
    const submitBtn = document.getElementById('leaveSubmitBtn');
    const applicationTypeSelect = document.getElementById('leave_application_type');
    const typeSelect = document.getElementById('leave_type_id');
    const balanceCards = document.getElementById('leaveBalanceCards');
    const proofInput = document.getElementById('proofs');
    const proofPreview = document.getElementById('proofPreview');
    const policyHint = document.getElementById('leaveTypePolicyHint');
    const sessionSelect = document.getElementById('session');
    const sessionWrap = document.getElementById('sessionWrap');
    const sessionHelpText = document.getElementById('sessionHelpText');
    const fromDateInput = document.getElementById('from_date');
    const fromDateLabel = document.getElementById('fromDateLabel');
    const toDateInput = document.getElementById('to_date');
    const toDateWrap = document.getElementById('toDateWrap');
    const daysPreview = document.getElementById('leaveDaysPreview');
    let leaveTypes = [];
    let previewTimer = null;
    let lastPreview = null;

    if (!form) {
        return;
    }

    const showAlert = (message, type = 'danger') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const updatePaidLeaveRestrictionNotice = (message) => {
        if (!paidLeaveRestrictionNotice) {
            return;
        }

        if (!message) {
            paidLeaveRestrictionNotice.classList.add('d-none');
            paidLeaveRestrictionNotice.textContent = '';

            return;
        }

        paidLeaveRestrictionNotice.textContent = message;
        paidLeaveRestrictionNotice.classList.remove('d-none');
    };

    const isSingleDayApplication = () => applicationTypeSelect?.value === 'single';

    const selectedLeaveType = () => leaveTypes.find((item) => String(item.id) === typeSelect.value);

    const getFromDate = () => fromDateInput?.value?.trim() ?? '';

    const getToDate = () => (isSingleDayApplication() ? getFromDate() : toDateInput?.value?.trim() ?? '');

    const updateApplicationTypeUi = () => {
        const single = isSingleDayApplication();

        toDateWrap?.classList.toggle('d-none', single);
        sessionWrap?.classList.toggle('d-none', !single);

        if (fromDateLabel) {
            fromDateLabel.innerHTML = single
                ? 'Leave Date <span class="text-danger">*</span>'
                : 'From Date <span class="text-danger">*</span>';
        }

        if (single) {
            if (getFromDate()) {
                toDateInput.value = getFromDate();
            }
            sessionHelpText.textContent = 'Choose full day or half day for the selected date.';
        } else {
            sessionSelect.value = 'full_day';
            sessionHelpText.textContent = 'Multiple-day leave applies as full days only.';
            if (getFromDate() && !toDateInput.value) {
                toDateInput.value = getFromDate();
            }
        }

        schedulePreview();
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

        if (!policyHint) {
            return;
        }

        if (!type) {
            policyHint.classList.add('d-none');
            policyHint.textContent = '';
            return;
        }

        let hint = type.application_policy_label || '';

        if (type.monthly_remaining !== null && type.monthly_remaining !== undefined) {
            const unit = type.monthly_limit_unit === 'hours' ? 'hour(s)' : 'day(s)';
            const monthLabel = getFromDate()
                ? new Date(`${getFromDate()}T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
                : 'This month';
            hint += ` · ${monthLabel}: ${type.monthly_used ?? 0} used, ${type.monthly_remaining} ${unit} remaining`;
        }

        if (type.requires_proof) {
            hint += hint ? ' · ' : '';
            hint += 'Proof required before approval — you can upload now or later from the leave request page';
        }

        policyHint.textContent = hint;
        policyHint.classList.toggle('d-none', !hint);
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
                : monthParamsFromDate(getFromDate());
            const { data } = await api.get('/leave-types/options', { params });
            const selectedId = typeSelect.value;
            leaveTypes = (data.data.leave_types || []).filter((type) => !type.is_hourly_leave);
            updatePaidLeaveRestrictionNotice(data.data.paid_leave_restriction_message || '');
            typeSelect.innerHTML = '<option value="">Select leave type</option>' + leaveTypes.map((type) => `
                <option value="${type.id}" data-requires-proof="${type.requires_proof ? '1' : '0'}">${type.name} (${type.code})</option>
            `).join('');

            if (selectedId && leaveTypes.some((type) => String(type.id) === selectedId)) {
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

        const countLabel = `${preview.working_days} working day(s)`;

        if (!preview.valid) {
            daysPreview.className = 'form-text text-danger';
            daysPreview.textContent = `${flattenPreviewErrors(preview.errors)} (${countLabel} in selected range)`;
            return;
        }

        daysPreview.className = 'form-text text-success';
        daysPreview.textContent = isSingleDayApplication() && sessionSelect.value !== 'full_day'
            ? `This half-day request uses ${countLabel}.`
            : `This request uses ${countLabel}. Weekends and holidays are excluded.`;
    };

    const runPreview = async () => {
        const fromDate = getFromDate();
        const toDate = getToDate();

        if (!typeSelect.value || !fromDate || !toDate) {
            lastPreview = null;
            renderDaysPreview(null);
            return;
        }

        try {
            const payload = {
                leave_type_id: Number(typeSelect.value),
                from_date: fromDate,
                to_date: toDate,
                session: isSingleDayApplication() ? sessionSelect.value : 'full_day',
            };

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
        const fromValue = getFromDate();

        if (fromValue) {
            await loadTypes(new Date(`${fromValue}T00:00:00`));
        } else {
            await loadTypes();
        }

        schedulePreview();
    };

    applicationTypeSelect?.addEventListener('change', updateApplicationTypeUi);
    typeSelect?.addEventListener('change', () => {
        updatePolicyHint();
        schedulePreview();
    });
    sessionSelect?.addEventListener('change', schedulePreview);
    fromDateInput?.addEventListener('change', () => {
        if (isSingleDayApplication()) {
            toDateInput.value = getFromDate();
        } else if (!toDateInput.value || toDateInput.value < getFromDate()) {
            toDateInput.value = getFromDate();
        }
        refreshMonthlyContext();
    });
    toDateInput?.addEventListener('change', schedulePreview);

    proofInput?.addEventListener('change', () => {
        const files = Array.from(proofInput.files || []);
        proofPreview.textContent = files.length
            ? `${files.length} file(s) selected: ${files.map((file) => file.name).join(', ')}`
            : '';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFormErrors(form);

        const fromDate = getFromDate();
        const toDate = getToDate();

        if (!fromDate || !toDate) {
            showAlert('Please select the required date(s).');
            return;
        }

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
            formData.append('from_date', fromDate);
            formData.append('to_date', toDate);
            formData.append('session', isSingleDayApplication() ? sessionSelect.value : 'full_day');
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

    updateApplicationTypeUi();
    await Promise.all([loadTypes(), loadBalances()]);
});
