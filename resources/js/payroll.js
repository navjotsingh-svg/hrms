import { Offcanvas } from 'bootstrap';
import './payroll-settings';
import api, { getErrorMessage } from './api';
import { filterEmployeeOptions, formatEmployeeLabel, initEmployeeAutocomplete } from './employee-autocomplete';
import { bindPagination, bindPerPageSelect, paginateArray, readPerPage, renderListPagination } from './pagination';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const paidDaysForPayslip = (payslip) => Math.max(
    (Number(payslip.payable_days) || 0) - (Number(payslip.lop_days) || 0),
    0,
);

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
    const exportBtn = document.getElementById('payrollExportBtn');
    const markPaidBtn = document.getElementById('payrollMarkPaidBtn');
    const periodStatusEl = document.getElementById('payrollPeriodStatus');
    const summaryWrap = document.getElementById('payrollSummaryWrap');
    const summaryHead = document.getElementById('payrollSummaryHead');
    const summaryBody = document.getElementById('payrollSummaryBody');
    const summarySearch = document.getElementById('payrollSummarySearch');
    const summaryPeriodLabel = document.getElementById('payrollSummaryPeriodLabel');
    const summaryTotals = document.getElementById('payrollSummaryTotals');
    const summaryPaginationInfo = document.getElementById('payrollSummaryPaginationInfo');
    const summaryPaginationList = document.getElementById('payrollSummaryPaginationList');
    const summaryPaginationWrap = document.getElementById('payrollSummaryPaginationWrap');
    const summaryPerPageSelect = document.getElementById('payrollSummaryPerPage');
    const detailDrawerEl = document.getElementById('payrollDetailDrawer');
    const detailDrawer = detailDrawerEl ? Offcanvas.getOrCreateInstance(detailDrawerEl) : null;
    const yearSelect = document.getElementById('payrollYear');
    const monthSelect = document.getElementById('payrollMonth');
    const offboardForm = document.getElementById('payrollOffboardForm');
    const offboardEmployeeSelect = document.getElementById('payrollOffboardEmployee');
    const offboardGenerateBtn = document.getElementById('payrollOffboardGenerateBtn');
    const offboardRefreshBtn = document.getElementById('payrollOffboardRefreshBtn');

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
    let summaryPayslipsAll = [];
    let summarySearchTerm = '';
    let summaryPage = 1;
    let summaryPerPage = readPerPage(summaryPerPageSelect);

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

    const selectedPeriod = () => periods.find((item) => String(item.id) === String(periodSelect?.value));

    const updatePeriodActions = () => {
        const period = selectedPeriod();
        const isPaid = Boolean(period?.is_paid || period?.status === 'paid');
        const isOffboard = period?.type === 'offboard' || period?.is_offboard;

        if (regenerateBtn) {
            regenerateBtn.disabled = isPaid || isOffboard;
            regenerateBtn.title = isOffboard
                ? 'Use offboard payroll for exiting employees.'
                : (isPaid ? 'Paid payroll cannot be regenerated.' : '');
        }

        if (markPaidBtn) {
            markPaidBtn.disabled = !period || isPaid;
            markPaidBtn.classList.toggle('d-none', isPaid);
        }

        if (periodStatusEl) {
            if (!period) {
                periodStatusEl.classList.add('d-none');
                periodStatusEl.innerHTML = '';
                return;
            }

            periodStatusEl.classList.remove('d-none');

            if (isPaid) {
                const paidMeta = period.paid_at_label
                    ? `Marked paid on ${period.paid_at_label}${period.paid_by?.name ? ` by ${period.paid_by.name}` : ''}`
                    : 'This payroll period has been marked as paid.';

                periodStatusEl.innerHTML = `
                    <div class="payroll-period-status-card payroll-period-status-card--paid">
                        <span class="payroll-period-status-badge payroll-period-status-badge--paid">Paid</span>
                        <span class="payroll-period-status-text">${escapeHtml(paidMeta)}</span>
                    </div>
                `;
            } else {
                periodStatusEl.innerHTML = `
                    <div class="payroll-period-status-card">
                        <span class="payroll-period-status-badge payroll-period-status-badge--processed">Processed</span>
                        <span class="payroll-period-status-text">Review the payout summary and mark as paid once salaries are disbursed.</span>
                    </div>
                `;
            }
        }
    };

    const updateActionButtons = () => {
        const hasPayslip = Boolean(selectedPayslipId());

        viewBtn.disabled = !hasPayslip;
        downloadBtn.disabled = !hasPayslip;

        if (exportBtn) {
            const periodId = periodSelect?.value;
            const payslips = periodId ? (payslipsByPeriod.get(periodId) || []) : [];
            exportBtn.disabled = !periodId || !payslips.length;
        }

        updatePeriodActions();
    };

    const hideSummary = () => {
        summaryWrap?.classList.add('d-none');
        summaryPayslipsAll = [];
        summarySearchTerm = '';
        summaryPage = 1;

        if (summarySearch) {
            summarySearch.value = '';
        }

        if (summaryBody) {
            summaryBody.innerHTML = '';
        }

        if (summaryHead) {
            summaryHead.innerHTML = '';
        }

        if (periodStatusEl) {
            periodStatusEl.classList.add('d-none');
            periodStatusEl.innerHTML = '';
        }
    };

    const formatEmployeeLink = (payslip) => {
        const name = escapeHtml(payslip.employee_name);
        const code = escapeHtml(payslip.employee_code || payslip.employee_id);
        const href = `/employees/${payslip.employee_id}`;

        return `
            <a href="${href}" class="payroll-employee-link" target="_blank" rel="noopener noreferrer">
                ${name} (${code})
                <span class="payroll-external-icon" aria-hidden="true">&#8599;</span>
            </a>
        `;
    };

    const filteredSummaryPayslips = () => {
        const term = summarySearchTerm.trim().toLowerCase();

        if (!term) {
            return summaryPayslipsAll;
        }

        return summaryPayslipsAll.filter((payslip) => {
            const name = String(payslip.employee_name || '').toLowerCase();
            const code = String(payslip.employee_code || '').toLowerCase();

            return name.includes(term) || code.includes(term);
        });
    };

    const renderDetailDrawer = (payslip) => {
        const titleEl = document.getElementById('payrollDetailDrawerLabel');
        const subtitleEl = document.getElementById('payrollDetailSubtitle');
        const bodyEl = document.getElementById('payrollDetailBody');

        if (!bodyEl) {
            return;
        }

        if (titleEl) {
            titleEl.textContent = payslip.employee_name || 'Detailed Calculations';
        }

        if (subtitleEl) {
            subtitleEl.textContent = `${payslip.employee_code || payslip.employee_id || '—'} · ${summaryPeriodLabel?.textContent?.replace(/^—\s*/, '') || 'Payroll period'}`;
        }

        const earningRows = (payslip.earnings || [])
            .filter((row) => row.label && row.label !== 'Expense Reimbursement')
            .map((row) => `
                <tr>
                    <td>${escapeHtml(row.label)}</td>
                    <td class="text-end">${formatAmount(row.amount)}</td>
                </tr>
            `).join('');

        const deductionRows = (payslip.deductions || []).map((row) => `
            <tr>
                <td>${escapeHtml(row.label)}</td>
                <td class="text-end">${formatAmount(row.amount)}</td>
            </tr>
        `).join('') || '<tr><td colspan="2" class="text-muted">No deductions</td></tr>';

        bodyEl.innerHTML = `
            <div class="payroll-detail-metrics row g-3 mb-4">
                <div class="col-4">
                    <div class="payroll-detail-metric">
                        <span class="payroll-detail-metric-label">Payable Days</span>
                        <span class="payroll-detail-metric-value">${Number(payslip.payable_days) || 0}</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="payroll-detail-metric">
                        <span class="payroll-detail-metric-label">LOP Days</span>
                        <span class="payroll-detail-metric-value">${Number(payslip.lop_days) || 0}</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="payroll-detail-metric">
                        <span class="payroll-detail-metric-label">Paid Days</span>
                        <span class="payroll-detail-metric-value">${paidDaysForPayslip(payslip).toFixed(1)}</span>
                    </div>
                </div>
            </div>

            <h6 class="mb-2">Earnings</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm payroll-detail-table">
                    <tbody>${earningRows || '<tr><td colspan="2" class="text-muted">No earnings</td></tr>'}</tbody>
                    <tfoot>
                        <tr>
                            <th>Gross Salary</th>
                            <th class="text-end">${formatAmount(payslip.total_earnings)}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h6 class="mb-2">Deductions</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm payroll-detail-table">
                    <tbody>${deductionRows}</tbody>
                    <tfoot>
                        <tr>
                            <th>Total Deductions</th>
                            <th class="text-end">${formatAmount(payslip.total_deductions)}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="payroll-detail-net">
                <span>Net Salary</span>
                <strong>${formatAmount(payslip.net_pay)}</strong>
            </div>
        `;

        detailDrawer?.show();
    };

    const openPayslipPreview = async (payslipId) => {
        const payslip = currentPayslipOptions.find((item) => String(item.id) === String(payslipId));

        if (payslip && employeeSearch) {
            employeeSearch.setSelection(payslip);
        }

        updateActionButtons();
        await viewPayslip();
        viewerWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderSummary = (payslips) => {
        if (!summaryWrap || !summaryBody) {
            return;
        }

        summaryPayslipsAll = payslips;

        if (!payslips.length) {
            hideSummary();
            return;
        }

        const period = periods.find((item) => String(item.id) === String(periodSelect?.value));
        const filteredPayslips = filteredSummaryPayslips();
        const { items: visiblePayslips, pagination } = paginateArray(filteredPayslips, summaryPage, summaryPerPage);

        if (summaryPeriodLabel) {
            summaryPeriodLabel.textContent = period ? `— ${period.label}` : '';
        }

        if (summaryTotals) {
            const totalGross = payslips.reduce((sum, item) => sum + (Number(item.total_earnings) || 0), 0);
            const totalNet = payslips.reduce((sum, item) => sum + (Number(item.net_pay) || 0), 0);
            summaryTotals.textContent = `${payslips.length} employees · Gross ${formatAmount(totalGross)} · Net ${formatAmount(totalNet)}`;
        }

        if (!filteredPayslips.length) {
            if (summaryHead) {
                summaryHead.innerHTML = `
                    <tr>
                        <th>Full Name</th>
                        <th>Comments</th>
                        <th class="text-end">Gross Salary</th>
                        <th class="text-end">Net Salary</th>
                        <th class="text-center">Payslip Preview</th>
                        <th class="text-center">Detailed Calculations</th>
                    </tr>
                `;
            }

            summaryBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No employees match your search.</td>
                </tr>
            `;
            summaryWrap.classList.remove('d-none');
            renderListPagination({
                infoEl: summaryPaginationInfo,
                listEl: summaryPaginationList,
                perPageSelectEl: summaryPerPageSelect,
                pagination: { total: 0, from: 0, to: 0, current_page: 1, last_page: 1, per_page: summaryPerPage },
                itemLabel: 'employees',
                emptyMessage: 'No employees match your search.',
            });
            return;
        }

        if (summaryHead) {
            summaryHead.innerHTML = `
                <tr>
                    <th>Full Name</th>
                    <th>Comments</th>
                    <th class="text-end">Gross Salary</th>
                    <th class="text-end">Net Salary</th>
                    <th class="text-center">Payslip Preview</th>
                    <th class="text-center">Detailed Calculations</th>
                </tr>
            `;
        }

        summaryBody.innerHTML = visiblePayslips.map((payslip) => `
            <tr>
                <td>${formatEmployeeLink(payslip)}</td>
                <td class="text-muted">—</td>
                <td class="text-end">${formatAmount(payslip.total_earnings)}</td>
                <td class="text-end">${formatAmount(payslip.net_pay)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm p-0 payroll-action-link" data-summary-view="${payslip.id}">View</button>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm p-0 payroll-action-link payroll-detail-link" data-summary-detail="${payslip.id}">
                        View <span class="payroll-sparkle" aria-hidden="true">&#10024;</span>
                    </button>
                </td>
            </tr>
        `).join('');

        renderListPagination({
            infoEl: summaryPaginationInfo,
            listEl: summaryPaginationList,
            perPageSelectEl: summaryPerPageSelect,
            pagination,
            itemLabel: 'employees',
            emptyMessage: 'No employees match your search.',
        });

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

        const options = periods.map((period) => {
            const statusSuffix = period.is_paid || period.status === 'paid' ? ' · Paid' : ' · Processed';

            return `
                <option value="${period.id}">
                    ${period.label} (${period.type_label || (period.type === 'regular' ? 'Regular' : period.type)})${statusSuffix}
                </option>
            `;
        }).join('');

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

    const loadEligibleOffboardEmployees = async () => {
        if (!offboardEmployeeSelect) {
            return;
        }

        offboardEmployeeSelect.innerHTML = '<option value="">Loading eligible employees...</option>';
        offboardGenerateBtn && (offboardGenerateBtn.disabled = true);

        try {
            const { data } = await api.get('/payroll-periods/offboard/eligible');
            const employees = data.data.employees || [];

            if (!employees.length) {
                offboardEmployeeSelect.innerHTML = '<option value="">No offboard employees pending final payroll</option>';
                return;
            }

            offboardEmployeeSelect.innerHTML = [
                '<option value="">Select offboarded employee...</option>',
                ...employees.map((employee) => `
                    <option value="${employee.employee_id}">
                        ${escapeHtml(employee.employee_name || 'Employee')} (${escapeHtml(employee.employee_code || employee.employee_id)}) · LWD ${escapeHtml(employee.last_working_date_label || '—')}
                    </option>
                `),
            ].join('');
            offboardGenerateBtn && (offboardGenerateBtn.disabled = false);
        } catch (error) {
            offboardEmployeeSelect.innerHTML = '<option value="">Unable to load offboard employees</option>';
            showAlert(getErrorMessage(error), 'danger');
        }
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
        summaryPage = 1;

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
        const previewTrigger = event.target.closest('[data-summary-view]');
        const detailTrigger = event.target.closest('[data-summary-detail]');

        if (previewTrigger) {
            await openPayslipPreview(previewTrigger.dataset.summaryView);
            return;
        }

        if (detailTrigger) {
            const payslip = summaryPayslipsAll.find((item) => String(item.id) === String(detailTrigger.dataset.summaryDetail));

            if (payslip) {
                renderDetailDrawer(payslip);
            }
        }
    });

    summarySearch?.addEventListener('input', () => {
        summarySearchTerm = summarySearch.value || '';
        summaryPage = 1;
        renderSummary(summaryPayslipsAll);
    });

    bindPagination(summaryPaginationWrap, (page) => {
        summaryPage = page;
        renderSummary(summaryPayslipsAll);
    });

    bindPerPageSelect(summaryPerPageSelect, (perPage) => {
        summaryPerPage = perPage;
        summaryPage = 1;
        renderSummary(summaryPayslipsAll);
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

    markPaidBtn?.addEventListener('click', async () => {
        const periodId = periodSelect?.value;

        if (!periodId) {
            return;
        }

        const period = selectedPeriod();

        if (!period || period.is_paid || period.status === 'paid') {
            return;
        }

        if (!window.confirm(`Mark payroll for ${period.label} as paid? Regeneration will be locked after this.`)) {
            return;
        }

        markPaidBtn.disabled = true;
        const originalText = markPaidBtn.textContent;
        markPaidBtn.textContent = 'Marking...';

        try {
            const { data } = await api.post(`/payroll-periods/${periodId}/mark-paid`);
            const updatedPeriod = data.data?.period;

            if (updatedPeriod) {
                periods = periods.map((item) => (
                    String(item.id) === String(updatedPeriod.id) ? updatedPeriod : item
                ));
                renderPeriodOptions();
                periodSelect.value = String(updatedPeriod.id);
            } else {
                await loadPeriods();
                periodSelect.value = periodId;
            }

            updatePeriodActions();
            showAlert(data.message || 'Payroll marked as paid successfully.');

            if (updatedPeriod?.type === 'offboard' || updatedPeriod?.is_offboard) {
                await loadEligibleOffboardEmployees();
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            markPaidBtn.textContent = originalText;
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

    offboardForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const employeeId = offboardEmployeeSelect?.value;

        if (!employeeId) {
            showAlert('Select an offboarded employee to generate final payroll.', 'warning');
            return;
        }

        offboardGenerateBtn.disabled = true;
        const originalText = offboardGenerateBtn.textContent;
        offboardGenerateBtn.textContent = 'Generating...';

        try {
            const { data } = await api.post('/payroll-periods/offboard/generate', {
                employee_id: Number(employeeId),
            });

            showAlert(data.message || 'Offboard payroll generated successfully.');
            payslipsByPeriod.delete(String(data.data.period.id));
            await loadPeriods();
            await loadEligibleOffboardEmployees();

            if (periodSelect && data.data?.period?.id) {
                periodSelect.value = String(data.data.period.id);
                periodSelect.dispatchEvent(new Event('change'));
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            offboardGenerateBtn.disabled = false;
            offboardGenerateBtn.textContent = originalText;
        }
    });

    offboardRefreshBtn?.addEventListener('click', () => {
        loadEligibleOffboardEmployees().catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    populateGenerateSelectors();

    try {
        if (isManageMode) {
            await loadPeriods();
            await loadEligibleOffboardEmployees();
        } else {
            await loadMyPayslips();
        }
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
