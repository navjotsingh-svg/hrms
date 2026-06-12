import api, { getErrorMessage } from './api';
import { filterEmployeeOptions, formatEmployeeLabel, initEmployeeAutocomplete } from './employee-autocomplete';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

document.addEventListener('DOMContentLoaded', async () => {
    const mode = window.PAYROLL_MODE || 'employee';
    const isManageMode = mode === 'manage';

    const alertBox = document.getElementById('payrollAlert');
    const periodSelect = document.getElementById('payrollPeriodSelect');
    const employeeHidden = document.getElementById('payrollEmployeeId');
    const employeeInput = document.getElementById('payrollEmployeeInput');
    const viewBtn = document.getElementById('payrollViewBtn');
    const downloadBtn = document.getElementById('payrollDownloadBtn');
    const viewerEmpty = document.getElementById('payrollViewerEmpty');
    const viewerWrap = document.getElementById('payrollViewerWrap');
    const viewerFrame = document.getElementById('payrollViewerFrame');
    const generateForm = document.getElementById('payrollGenerateForm');
    const generateBtn = document.getElementById('payrollGenerateBtn');
    const regenerateBtn = document.getElementById('payrollRegenerateBtn');
    const deletePeriodBtn = document.getElementById('payrollDeletePeriodBtn');
    const exportBtn = document.getElementById('payrollExportBtn');
    const summaryWrap = document.getElementById('payrollSummaryWrap');
    const summaryBody = document.getElementById('payrollSummaryBody');
    const summaryPeriodLabel = document.getElementById('payrollSummaryPeriodLabel');
    const summaryTotals = document.getElementById('payrollSummaryTotals');
    const yearSelect = document.getElementById('payrollYear');
    const monthSelect = document.getElementById('payrollMonth');

    const inrFormatter = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const formatAmount = (value) => inrFormatter.format(Number(value) || 0);

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    let periods = [];
    let payslipsByPeriod = new Map();
    let myPayslips = [];
    let previewUrl = null;
    let currentPayslipOptions = [];
    let employeeSearch = null;

    if (isManageMode && employeeInput && employeeHidden) {
        employeeSearch = initEmployeeAutocomplete({
            input: employeeInput,
            menu: document.getElementById('payrollEmployeeInputMenu'),
            hiddenInput: employeeHidden,
            wrap: document.getElementById('payrollEmployeeInputWrap'),
            toggleButton: document.getElementById('payrollEmployeeInputToggle'),
            fetchSuggestions: async (term) => filterEmployeeOptions(currentPayslipOptions, term),
            onSelect: () => {
                clearPreview();
                updateActionButtons();
            },
            onClear: () => {
                clearPreview();
                updateActionButtons();
            },
        });
        employeeSearch.setDisabled(true);
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const clearPreview = () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        if (viewerFrame) {
            viewerFrame.removeAttribute('src');
        }

        viewerWrap?.classList.add('d-none');
        viewerEmpty?.classList.remove('d-none');
    };

    const selectedPayslipId = () => {
        if (isManageMode) {
            return employeeSearch?.getSelectedId?.() || employeeHidden?.value || '';
        }

        const periodId = periodSelect?.value;

        if (!periodId) {
            return '';
        }

        const payslip = myPayslips.find((item) => String(item.payroll_period_id) === String(periodId));

        return payslip ? String(payslip.id) : '';
    };

    const updateActionButtons = () => {
        const hasPayslip = Boolean(selectedPayslipId());

        viewBtn.disabled = !hasPayslip;
        downloadBtn.disabled = !hasPayslip;

        if (deletePeriodBtn) {
            deletePeriodBtn.disabled = !periodSelect?.value;
        }

        if (exportBtn) {
            const periodId = periodSelect?.value;
            const payslips = periodId ? (payslipsByPeriod.get(periodId) || []) : [];
            exportBtn.disabled = !periodId || !payslips.length;
        }
    };

    const hideSummary = () => {
        summaryWrap?.classList.add('d-none');

        if (summaryBody) {
            summaryBody.innerHTML = '';
        }
    };

    const renderSummary = (payslips) => {
        if (!summaryWrap || !summaryBody) {
            return;
        }

        if (!payslips.length) {
            hideSummary();
            return;
        }

        const period = periods.find((item) => String(item.id) === String(periodSelect?.value));

        if (summaryPeriodLabel) {
            summaryPeriodLabel.textContent = period ? `— ${period.label}` : '';
        }

        if (summaryTotals) {
            const totalGross = payslips.reduce((sum, item) => sum + (Number(item.total_earnings) || 0), 0);
            const totalNet = payslips.reduce((sum, item) => sum + (Number(item.net_pay) || 0), 0);
            summaryTotals.textContent = `${payslips.length} employees · Gross ${formatAmount(totalGross)} · Net ${formatAmount(totalNet)}`;
        }

        summaryBody.innerHTML = payslips.map((payslip) => `
            <tr>
                <td>
                    <div class="fw-semibold">${escapeHtml(payslip.employee_name)}</div>
                    <div class="text-muted small">${escapeHtml(payslip.employee_code || payslip.employee_id)}</div>
                </td>
                <td class="text-end">${Number(payslip.payable_days) || 0}</td>
                <td class="text-end">${Number(payslip.lop_days) || 0}</td>
                <td class="text-end">${formatAmount(payslip.total_earnings)}</td>
                <td class="text-end fw-semibold">${formatAmount(payslip.net_pay)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm p-0" data-summary-view="${payslip.id}">View</button>
                </td>
            </tr>
        `).join('');

        summaryWrap.classList.remove('d-none');
    };

    const populateGenerateSelectors = () => {
        if (!yearSelect || !monthSelect) {
            return;
        }

        const currentYear = new Date().getFullYear();

        yearSelect.innerHTML = Array.from({ length: 3 }, (_, index) => currentYear - index)
            .map((year) => `<option value="${year}">${year}</option>`)
            .join('');

        monthSelect.innerHTML = MONTHS.map((label, index) => {
            const month = index + 1;
            const selected = month === new Date().getMonth() + 1 ? 'selected' : '';

            return `<option value="${month}" ${selected}>${label}</option>`;
        }).join('');
    };

    const renderPeriodOptions = () => {
        if (!periodSelect) {
            return;
        }

        const options = periods.map((period) => `
            <option value="${period.id}">
                ${period.label} (${period.type === 'regular' ? 'Regular' : period.type})
            </option>
        `).join('');

        periodSelect.innerHTML = `<option value="">Choose period...</option>${options}`;

        if (periods.length === 1) {
            periodSelect.value = String(periods[0].id);
            periodSelect.dispatchEvent(new Event('change'));
        }
    };

    const renderEmployeeOptions = (payslips) => {
        if (!employeeSearch || !employeeInput) {
            updateActionButtons();
            return;
        }

        currentPayslipOptions = payslips.map((payslip) => ({
            id: payslip.id,
            label: formatEmployeeLabel(payslip),
            employee: payslip,
        }));

        if (!payslips.length) {
            employeeSearch.clearSelection();
            employeeInput.placeholder = 'No payslips found';
            employeeSearch.setDisabled(true);
            updateActionButtons();
            return;
        }

        employeeInput.placeholder = 'Select or search employee...';
        employeeSearch.setDisabled(false);
        employeeSearch.setSelection(currentPayslipOptions[0]);
        updateActionButtons();
    };

    const loadPeriods = async () => {
        const { data } = await api.get('/payroll-periods');
        periods = data.data.periods || [];
        renderPeriodOptions();
    };

    const loadMyPayslips = async () => {
        const { data } = await api.get('/my-payslips');
        myPayslips = data.data.payslips || [];
        periods = myPayslips
            .map((payslip) => payslip.period)
            .filter(Boolean)
            .reduce((unique, period) => {
                if (!unique.some((item) => item.id === period.id)) {
                    unique.push({
                        ...period,
                        payslips_count: 1,
                    });
                }

                return unique;
            }, [])
            .sort((a, b) => (b.year - a.year) || (b.month - a.month));

        renderPeriodOptions();
    };

    const loadPayslipsForPeriod = async (periodId) => {
        if (payslipsByPeriod.has(periodId)) {
            const cached = payslipsByPeriod.get(periodId);
            renderEmployeeOptions(cached);
            renderSummary(cached);
            return;
        }

        const { data } = await api.get(`/payroll-periods/${periodId}/payslips`);
        const payslips = data.data.payslips || [];
        payslipsByPeriod.set(periodId, payslips);
        renderEmployeeOptions(payslips);
        renderSummary(payslips);
    };

    const viewPayslip = async () => {
        const payslipId = selectedPayslipId();

        if (!payslipId) {
            return;
        }

        clearPreview();

        try {
            const response = await api.get(`/payslips/${payslipId}/view`, { responseType: 'blob' });
            previewUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            viewerFrame.src = previewUrl;
            viewerEmpty?.classList.add('d-none');
            viewerWrap?.classList.remove('d-none');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const downloadPayslip = async () => {
        const payslipId = selectedPayslipId();

        if (!payslipId) {
            return;
        }

        try {
            const response = await api.get(`/payslips/${payslipId}/download`, { responseType: 'blob' });
            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const disposition = response.headers['content-disposition'] || '';
            const match = disposition.match(/filename="?([^"]+)"?/i);
            link.href = url;
            link.download = match?.[1] || `payslip-${payslipId}.pdf`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    periodSelect?.addEventListener('change', async () => {
        clearPreview();
        const periodId = periodSelect.value;

        if (!periodId) {
            if (employeeSearch) {
                employeeSearch.clearSelection();
                employeeInput.placeholder = 'Select period first...';
                employeeSearch.setDisabled(true);
            }

            hideSummary();
            updateActionButtons();
            return;
        }

        if (isManageMode) {
            await loadPayslipsForPeriod(periodId);
        } else {
            updateActionButtons();
        }
    });

    viewBtn?.addEventListener('click', viewPayslip);
    downloadBtn?.addEventListener('click', downloadPayslip);

    summaryBody?.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-summary-view]');

        if (!trigger || !employeeSearch) {
            return;
        }

        const payslip = currentPayslipOptions.find((item) => String(item.id) === String(trigger.dataset.summaryView));

        if (payslip) {
            employeeSearch.setSelection(payslip);
        }

        updateActionButtons();
        await viewPayslip();
        viewerWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    exportBtn?.addEventListener('click', async () => {
        const periodId = periodSelect?.value;

        if (!periodId) {
            return;
        }

        exportBtn.disabled = true;
        const originalText = exportBtn.textContent;
        exportBtn.textContent = 'Exporting...';

        try {
            const response = await api.get(`/payroll-periods/${periodId}/export`, { responseType: 'blob' });
            const blob = new Blob([response.data], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const disposition = response.headers['content-disposition'] || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            link.href = url;
            link.download = match?.[1] || `payroll-${periodId}.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            exportBtn.textContent = originalText;
            updateActionButtons();
        }
    });

    const submitPayrollRun = async (endpoint, button, loadingText, successFallback) => {
        if (!button || !yearSelect || !monthSelect) {
            return;
        }

        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = loadingText;

        try {
            const { data } = await api.post(endpoint, {
                year: Number(yearSelect.value),
                month: Number(monthSelect.value),
            });

            showAlert(data.message || successFallback);
            payslipsByPeriod.clear();
            clearPreview();
            await loadPeriods();

            if (data.data?.period?.id) {
                periodSelect.value = String(data.data.period.id);
                periodSelect.dispatchEvent(new Event('change'));
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    };

    generateForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitPayrollRun(
            '/payroll-periods/generate',
            generateBtn,
            'Generating...',
            'Payroll generated successfully.',
        );
    });

    deletePeriodBtn?.addEventListener('click', async () => {
        const periodId = periodSelect?.value;

        if (!periodId) {
            return;
        }

        const period = periods.find((item) => String(item.id) === String(periodId));

        if (!window.confirm(`Delete payroll for ${period?.label || 'this period'}? All payslips in this period will be removed.`)) {
            return;
        }

        deletePeriodBtn.disabled = true;

        try {
            const { data } = await api.delete(`/payroll-periods/${periodId}`);
            showAlert(data.message || 'Payroll period deleted successfully.');
            payslipsByPeriod.delete(periodId);
            clearPreview();
            hideSummary();
            await loadPeriods();

            if (employeeSearch) {
                employeeSearch.clearSelection();
                employeeInput.placeholder = 'Select period first...';
                employeeSearch.setDisabled(true);
            }

            updateActionButtons();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
            deletePeriodBtn.disabled = false;
        }
    });

    regenerateBtn?.addEventListener('click', async () => {
        if (!window.confirm('Regenerate payroll for this month? Existing payslips will be replaced using latest attendance and leave data.')) {
            return;
        }

        await submitPayrollRun(
            '/payroll-periods/regenerate',
            regenerateBtn,
            'Regenerating...',
            'Payroll regenerated successfully.',
        );
    });

    populateGenerateSelectors();

    try {
        if (isManageMode) {
            await loadPeriods();
        } else {
            await loadMyPayslips();
        }
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
