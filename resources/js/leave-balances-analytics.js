import { Offcanvas } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';

const COLUMN_DEFS = {
    employee_code: { label: 'Employee ID', className: '' },
    employee_name: { label: 'Full Name', className: '' },
    department: { label: 'Department', className: '' },
    designation: { label: 'Designation', className: '' },
    joining_date_label: { label: 'Date of Joining', className: '' },
    policy_name: { label: 'Policy Name', className: '' },
    from_balance: { label: 'From Balance', className: 'text-end' },
    initial_balance: { label: 'Initial Balance', className: 'text-end' },
    accrued_leaves: { label: 'Accrued Leaves', className: 'text-end' },
    manual_reset_leaves: { label: 'Manual Reset', className: 'text-end' },
    expiration_changes: { label: 'Expiration', className: 'text-end' },
    carry_forward_changes: { label: 'Carry Forward', className: 'text-end' },
    leaves_taken: { label: 'Leaves Taken', className: 'text-end' },
    to_balance: { label: 'To Balance', className: 'text-end' },
    balance_change: { label: 'Balance Change', className: 'text-end' },
    balance_change_type: { label: 'Change Type', className: '' },
};

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('leaveBalancesAlert');
    const tableHead = document.getElementById('leaveBalancesTableHead');
    const tableBody = document.getElementById('leaveBalancesTableBody');
    const paginationInfo = document.getElementById('leaveBalancesPaginationInfo');
    const paginationList = document.getElementById('leaveBalancesPaginationList');
    const exportBtn = document.getElementById('exportLeaveBalancesBtn');
    const loadBtn = document.getElementById('loadLeaveBalancesBtn');
    const reportPanel = document.getElementById('reportPanel');
    const chartsPanel = document.getElementById('chartsPanel');
    const tabButtons = Array.from(document.querySelectorAll('[data-analytics-tab]'));
    const detailDrawerEl = document.getElementById('leaveBalanceDetailDrawer');
    const detailDrawer = detailDrawerEl ? Offcanvas.getOrCreateInstance(detailDrawerEl) : null;

    let currentPage = 1;
    let reportLoaded = false;

    const today = new Date();
    const yearStart = `${today.getFullYear()}-01-01`;
    const todayStr = today.toISOString().slice(0, 10);

    const fromDateInput = document.getElementById('filterFromDate');
    const toDateInput = document.getElementById('filterToDate');
    if (fromDateInput) fromDateInput.value = yearStart;
    if (toDateInput) toDateInput.value = todayStr;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const formatAmount = (row, value) => {
        if (row?.is_unlimited && ['from_balance', 'to_balance', 'balance_change'].includes(row.__col)) {
            return 'Unlimited';
        }

        if (value === null || value === undefined) {
            return '—';
        }

        return Number(value).toFixed(2);
    };

    const changeTypeLabel = (type) => ({
        increase: 'Increase',
        decrease: 'Decrease',
        no_change: 'No Change',
        unlimited: 'Unlimited',
    }[type] || type);

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const visibleColumns = () => Object.keys(COLUMN_DEFS);

    const filters = () => ({
        from_date: fromDateInput?.value || '',
        to_date: toDateInput?.value || '',
        employee_status: document.getElementById('filterEmployeeStatus')?.value || 'active',
        employment_type: document.getElementById('filterEmploymentType')?.value || 'all',
        policy_status: document.getElementById('filterPolicyStatus')?.value || 'active',
        assignment_status: document.getElementById('filterAssignmentStatus')?.value || 'active',
        department_id: document.getElementById('filterDepartmentId')?.value || '',
        leave_type_id: document.getElementById('filterLeaveTypeId')?.value || '',
        employee_id: document.getElementById('filterEmployeeId')?.value || '',
        search: document.getElementById('filterSearch')?.value.trim() || '',
        per_page: Number(document.getElementById('itemsPerPage')?.value || 25),
        page: currentPage,
    });

    const renderTableHead = () => {
        if (!tableHead) return;
        const columns = visibleColumns();
        tableHead.innerHTML = `<tr>${columns.map((key) => `<th class="${COLUMN_DEFS[key].className}">${COLUMN_DEFS[key].label}</th>`).join('')}<th class="text-center">Detailed Calculations</th></tr>`;
    };

    const renderCellValue = (row, key) => {
        if (key === 'balance_change_type') {
            return escapeHtml(changeTypeLabel(row.balance_change_type));
        }

        if (['from_balance', 'to_balance', 'balance_change', 'initial_balance', 'accrued_leaves', 'manual_reset_leaves', 'expiration_changes', 'carry_forward_changes', 'leaves_taken'].includes(key)) {
            if (row.is_unlimited && ['from_balance', 'to_balance', 'balance_change'].includes(key)) {
                return 'Unlimited';
            }

            return formatAmount(row, row[key]);
        }

        return escapeHtml(row[key] ?? '—');
    };

    const renderTable = (rows) => {
        renderTableHead();
        const columns = visibleColumns();

        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${columns.length + 1}" class="text-center text-muted py-5">No data available for the selected filters.</td></tr>`;
            return;
        }

        tableBody.innerHTML = rows.map((row) => `
            <tr>
                ${columns.map((key) => `<td class="${COLUMN_DEFS[key].className}">${renderCellValue(row, key)}</td>`).join('')}
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm p-0" data-view-detail="${row.employee_id}:${row.leave_type_id}">View</button>
                </td>
            </tr>
        `).join('');
    };

    const renderPagination = (pagination) => {
        if (!paginationInfo || !paginationList) return;

        if (!pagination?.total) {
            paginationInfo.textContent = '0-0 of 0';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = `${pagination.from}-${pagination.to} of ${pagination.total}`;
        paginationList.innerHTML = '';

        const addItem = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<button type="button" class="page-link">${label}</button>`;
            if (!disabled && !active) {
                li.querySelector('button').addEventListener('click', () => {
                    currentPage = page;
                    loadReport();
                });
            }
            paginationList.appendChild(li);
        };

        addItem('«', 1, pagination.current_page <= 1);
        addItem('‹', pagination.current_page - 1, pagination.current_page <= 1);
        addItem(String(pagination.current_page), pagination.current_page, false, true);
        addItem('›', pagination.current_page + 1, pagination.current_page >= pagination.last_page);
        addItem('»', pagination.last_page, pagination.current_page >= pagination.last_page);
    };

    const renderBarChart = (container, items, labelKey, valueKey, unit = '') => {
        if (!container) return;

        if (!items?.length) {
            container.innerHTML = '<div class="text-muted small">No chart data for the selected filters.</div>';
            return;
        }

        const max = Math.max(...items.map((item) => Math.abs(Number(item[valueKey]) || 0)), 1);

        container.innerHTML = items.map((item) => {
            const value = Number(item[valueKey]) || 0;
            const width = Math.max(4, Math.round((Math.abs(value) / max) * 100));

            return `
                <div class="analytics-bar-row">
                    <div class="analytics-bar-label">${escapeHtml(item[labelKey])}</div>
                    <div class="analytics-bar-track">
                        <div class="analytics-bar-fill ${value < 0 ? 'analytics-bar-fill--negative' : ''}" style="width:${width}%"></div>
                    </div>
                    <div class="analytics-bar-value">${value.toFixed(2)}${unit}</div>
                </div>
            `;
        }).join('');
    };

    const renderCharts = (charts) => {
        renderBarChart(
            document.getElementById('chartDepartment'),
            charts?.balance_change_by_department || [],
            'department',
            'balance_change',
        );
        renderBarChart(
            document.getElementById('chartPolicy'),
            charts?.leaves_taken_by_policy || [],
            'policy',
            'leaves_taken',
        );

        const changeTypeContainer = document.getElementById('chartChangeType');
        if (changeTypeContainer) {
            const items = charts?.balance_change_by_type || [];
            if (!items.length) {
                changeTypeContainer.innerHTML = '<div class="text-muted small">No chart data for the selected filters.</div>';
            } else {
                changeTypeContainer.innerHTML = items.map((item) => `
                    <div class="analytics-bar-row">
                        <div class="analytics-bar-label">${escapeHtml(item.type_label || item.type)}</div>
                        <div class="analytics-bar-track">
                            <div class="analytics-bar-fill analytics-bar-fill--muted" style="width:${Math.max(8, item.count * 12)}%"></div>
                        </div>
                        <div class="analytics-bar-value">${item.count}</div>
                    </div>
                `).join('');
            }
        }
    };

    const loadReport = async () => {
        if (!fromDateInput?.value || !toDateInput?.value) {
            showAlert('From date and to date are required.', 'danger');
            return;
        }

        loadBtn.disabled = true;
        tableBody.innerHTML = `<tr><td colspan="12" class="text-center text-muted py-5">Loading...</td></tr>`;

        try {
            const response = await api.get('/analytics/leave-balances', { params: filters() });
            const payload = response.data.data;
            renderTable(payload.rows || []);
            renderPagination(payload.pagination);
            renderCharts(payload.charts);
            reportLoaded = true;
            exportBtn?.classList.remove('d-none');
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            loadBtn.disabled = false;
        }
    };

    const openDetail = async (employeeId, leaveTypeId) => {
        if (!detailDrawer) return;

        const summaryEl = document.getElementById('leaveBalanceDetailSummary');
        const timelineEl = document.getElementById('leaveBalanceDetailTimeline');
        const subtitleEl = document.getElementById('leaveBalanceDetailSubtitle');

        summaryEl.innerHTML = '<div class="text-muted small">Loading...</div>';
        timelineEl.innerHTML = '';

        try {
            const response = await api.get(`/analytics/leave-balances/employees/${employeeId}/policies/${leaveTypeId}`, {
                params: filters(),
            });
            const payload = response.data.data;
            const summary = payload.summary;

            document.getElementById('leaveBalanceDetailDrawerLabel').textContent = payload.employee?.full_name || 'Detailed Calculations';
            subtitleEl.textContent = `${payload.policy?.name || 'Policy'} · ${fromDateInput.value} to ${toDateInput.value}`;

            if (!summary) {
                summaryEl.innerHTML = '<div class="text-muted">No balance record found for this employee and policy.</div>';
            } else {
                summaryEl.innerHTML = `
                    <div class="row g-2 small">
                        <div class="col-6"><span class="text-muted">From Balance</span><div class="fw-semibold">${summary.is_unlimited ? 'Unlimited' : Number(summary.from_balance).toFixed(2)}</div></div>
                        <div class="col-6"><span class="text-muted">To Balance</span><div class="fw-semibold">${summary.is_unlimited ? 'Unlimited' : Number(summary.to_balance).toFixed(2)}</div></div>
                        <div class="col-6"><span class="text-muted">Leaves Taken</span><div class="fw-semibold">${Number(summary.leaves_taken).toFixed(2)}</div></div>
                        <div class="col-6"><span class="text-muted">Balance Change</span><div class="fw-semibold">${summary.is_unlimited ? '—' : Number(summary.balance_change).toFixed(2)} (${escapeHtml(changeTypeLabel(summary.balance_change_type))})</div></div>
                    </div>
                `;
            }

            timelineEl.innerHTML = (payload.events || []).map((event) => `
                <div class="analytics-timeline-item">
                    <div class="analytics-timeline-date">${escapeHtml(event.date_label)}</div>
                    <div class="analytics-timeline-title">${escapeHtml(event.type_label)}</div>
                    <div class="analytics-timeline-meta">${event.direction === 'credit' ? '+' : '-'}${Number(event.amount).toFixed(2)} · ${escapeHtml(event.notes || '')}</div>
                </div>
            `).join('') || '<div class="text-muted small">No activity in this period.</div>';

            detailDrawer.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadFilterOptions = async () => {
        try {
            const [departmentsRes, leaveTypesRes] = await Promise.all([
                api.get('/departments', { params: { per_page: 50 } }).catch(() => ({ data: { data: { departments: [] } } })),
                api.get('/leave-types', { params: { per_page: 50, status: 'active' } }),
            ]);

            const departments = departmentsRes.data.data.departments || [];
            const leaveTypes = leaveTypesRes.data.data.leave_types || [];

            const departmentSelect = document.getElementById('filterDepartmentId');
            if (departmentSelect) {
                departmentSelect.innerHTML = '<option value="">All Departments</option>' + departments
                    .map((dept) => `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`)
                    .join('');
            }

            const leaveTypeSelect = document.getElementById('filterLeaveTypeId');
            if (leaveTypeSelect) {
                leaveTypeSelect.innerHTML = '<option value="">All Policies</option>' + leaveTypes
                    .map((type) => `<option value="${type.id}">${escapeHtml(type.name)}</option>`)
                    .join('');
            }
        } catch {
            // Optional filter data.
        }
    };

    bindEmployeeSearchSelect({
        inputId: 'filterEmployeeInput',
        hiddenId: 'filterEmployeeId',
    });

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            tabButtons.forEach((tab) => tab.classList.toggle('active', tab === button));
            const tab = button.dataset.analyticsTab;
            reportPanel?.classList.toggle('d-none', tab !== 'report');
            chartsPanel?.classList.toggle('d-none', tab !== 'charts');
        });
    });

    loadBtn?.addEventListener('click', () => {
        currentPage = 1;
        loadReport();
    });

    document.getElementById('filterReset')?.addEventListener('click', () => {
        if (fromDateInput) fromDateInput.value = yearStart;
        if (toDateInput) toDateInput.value = todayStr;
        document.getElementById('filterEmployeeStatus').value = 'active';
        document.getElementById('filterEmploymentType').value = 'all';
        document.getElementById('filterPolicyStatus').value = 'active';
        document.getElementById('filterAssignmentStatus').value = 'active';
        document.getElementById('filterDepartmentId').value = '';
        document.getElementById('filterLeaveTypeId').value = '';
        document.getElementById('filterSearch').value = '';
        document.getElementById('itemsPerPage').value = '25';
        document.getElementById('filterEmployeeId').value = '';
        document.getElementById('filterEmployeeInput').value = '';
        currentPage = 1;
        reportLoaded = false;
        exportBtn?.classList.add('d-none');
        tableBody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-5">Choose a date range and click Load Report.</td></tr>';
        paginationInfo.textContent = 'Select dates and load the report.';
        paginationList.innerHTML = '';
    });

    tableBody?.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-view-detail]');
        if (!trigger) return;
        const [employeeId, leaveTypeId] = trigger.dataset.viewDetail.split(':');
        openDetail(employeeId, leaveTypeId);
    });

    exportBtn?.addEventListener('click', async () => {
        exportBtn.disabled = true;

        try {
            const response = await api.get('/analytics/leave-balances/export', {
                params: filters(),
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = `leave-balances-report-${fromDateInput.value}-to-${toDateInput.value}.csv`;
            link.click();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            exportBtn.disabled = false;
        }
    });

    renderTableHead();
    await loadFilterOptions();
});
