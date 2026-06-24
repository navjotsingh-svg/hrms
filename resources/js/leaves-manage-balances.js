import api, { getErrorMessage } from './api';
import { debounce } from './form-utils';
import { Modal } from 'bootstrap';

document.addEventListener('DOMContentLoaded', async () => {
    const yearSelect = document.getElementById('balanceYear');
    const departmentSelect = document.getElementById('balanceDepartment');
    const statusSelect = document.getElementById('balanceStatus');
    const searchInput = document.getElementById('balanceSearch');
    const matrixHead = document.getElementById('leaveBalanceMatrixHead');
    const matrixBody = document.getElementById('leaveBalanceMatrixBody');
    const overviewTitle = document.getElementById('balancesOverviewTitle');
    const paginationInfo = document.getElementById('balancesPaginationInfo');
    const paginationSummary = document.getElementById('balancesPaginationSummary');
    const paginationList = document.getElementById('balancesPaginationList');
    const alertBox = document.getElementById('manageBalancesAlert');
    const modalEl = document.getElementById('employeeBalanceModal');
    const modalTitle = document.getElementById('employeeBalanceModalTitle');
    const modalSubtitle = document.getElementById('employeeBalanceModalSubtitle');
    const tableBody = document.getElementById('manageBalancesTableBody');
    const grantCompOffCard = document.getElementById('grantCompOffCard');
    const grantCompOffBtn = document.getElementById('grantCompOffBtn');
    const grantCompOffDays = document.getElementById('grantCompOffDays');
    const compOffSummary = document.getElementById('compOffSummary');
    const currentYear = new Date().getFullYear();

    let currentPage = 1;
    let leaveTypes = [];
    let compOffBalanceId = null;
    let selectedEmployeeId = null;
    let modalInstance = null;

    if (!matrixHead || !matrixBody) {
        return;
    }

    if (modalEl) {
        modalInstance = Modal.getOrCreateInstance(modalEl);
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    if (yearSelect) {
        yearSelect.innerHTML = Array.from({ length: 3 }, (_, index) => currentYear - 1 + index)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`)
            .join('');
    }

    const filters = () => ({
        year: yearSelect?.value || currentYear,
        department_id: departmentSelect?.value || undefined,
        status: statusSelect?.value || 'active',
        search: searchInput?.value?.trim() || undefined,
        page: currentPage,
        per_page: 25,
    });

    const formatAvailable = (cell) => {
        if (!cell) {
            return '<span class="leave-balance-cell leave-balance-cell--na">—</span>';
        }

        if (cell.is_comp_off) {
            return `<span class="leave-balance-cell leave-balance-cell--comp" title="Credited: ${cell.adjusted} · Used: ${cell.used} · Pending: ${cell.pending}">${cell.available ?? 0}</span>`;
        }

        const unit = cell.unit === 'hours' ? 'h' : 'd';
        const availableLabel = cell.available ?? '∞';
        const title = `Allocated: ${cell.allocated} · Used: ${cell.used} · Pending: ${cell.pending}`;

        return `<span class="leave-balance-cell" title="${escapeHtml(title)}">${availableLabel}<span class="leave-balance-cell-unit">${unit}</span></span>`;
    };

    const renderMatrixHead = () => {
        if (!leaveTypes.length) {
            matrixHead.innerHTML = '<tr><th colspan="4" class="text-muted py-4">No active leave types configured.</th></tr>';

            return;
        }

        matrixHead.innerHTML = `
            <tr>
                <th class="leave-balance-matrix-sticky leave-balance-matrix-sticky--code">Code</th>
                <th class="leave-balance-matrix-sticky leave-balance-matrix-sticky--name">Employee</th>
                <th class="leave-balance-matrix-sticky leave-balance-matrix-sticky--dept">Department</th>
                ${leaveTypes.map((type) => `
                    <th class="leave-balance-matrix-type" title="${escapeHtml(type.name)}">
                        <span class="leave-balance-matrix-type-code">${escapeHtml(type.code)}</span>
                        <span class="leave-balance-matrix-type-unit">${type.quota_unit === 'hours' ? 'hrs' : 'days'}</span>
                    </th>
                `).join('')}
                <th class="leave-balance-matrix-sticky leave-balance-matrix-sticky--action text-end">Manage</th>
            </tr>
        `;
    };

    const renderMatrixBody = (employees) => {
        if (!leaveTypes.length) {
            matrixBody.innerHTML = '';

            return;
        }

        if (!employees.length) {
            matrixBody.innerHTML = `<tr><td colspan="${leaveTypes.length + 4}" class="text-center text-muted py-5">No employees found for the selected filters.</td></tr>`;

            return;
        }

        matrixBody.innerHTML = employees.map((employee) => `
            <tr class="companies-data-row">
                <td class="leave-balance-matrix-sticky leave-balance-matrix-sticky--code">
                    <span class="fw-semibold">${escapeHtml(employee.employee_code)}</span>
                </td>
                <td class="leave-balance-matrix-sticky leave-balance-matrix-sticky--name">
                    <span class="fw-semibold">${escapeHtml(employee.full_name)}</span>
                    ${employee.designation ? `<div class="small text-muted">${escapeHtml(employee.designation)}</div>` : ''}
                </td>
                <td class="leave-balance-matrix-sticky leave-balance-matrix-sticky--dept">${escapeHtml(employee.department || '—')}</td>
                ${leaveTypes.map((type) => {
                    const cell = employee.balances?.[String(type.id)] ?? employee.balances?.[type.id] ?? null;

                    return `<td class="text-center">${formatAvailable(cell)}</td>`;
                }).join('')}
                <td class="leave-balance-matrix-sticky leave-balance-matrix-sticky--action text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-manage-employee="${employee.id}">Manage</button>
                </td>
            </tr>
        `).join('');
    };

    const renderPagination = (pagination) => {
        if (!pagination) {
            paginationInfo.textContent = '—';
            paginationSummary.textContent = '';
            paginationList.innerHTML = '';

            return;
        }

        paginationInfo.textContent = pagination.total
            ? `${pagination.total} employee(s)`
            : 'No employees';

        paginationSummary.textContent = pagination.total
            ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
            : '';

        if (!pagination.last_page || pagination.last_page <= 1) {
            paginationList.innerHTML = '';

            return;
        }

        paginationList.innerHTML = Array.from({ length: pagination.last_page }, (_, index) => {
            const page = index + 1;

            return `<li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${page}">${page}</button>
            </li>`;
        }).join('');
    };

    const loadOverview = async () => {
        matrixBody.innerHTML = `<tr><td colspan="12" class="text-center text-muted py-5">Loading...</td></tr>`;

        try {
            const { data } = await api.get('/leave-balances/overview', { params: filters() });
            const payload = data.data;

            leaveTypes = payload.leave_types || [];
            overviewTitle.textContent = `Leave balance overview — ${payload.year}`;
            renderMatrixHead();
            renderMatrixBody(payload.employees || []);
            renderPagination(payload.pagination);
        } catch (error) {
            matrixHead.innerHTML = '';
            matrixBody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
            renderPagination(null);
        }
    };

    const quotaLabel = (item) => {
        if (item.is_comp_off || item.leave_type?.is_comp_off) {
            return 'Earned';
        }

        const unit = item.leave_type?.quota_unit === 'hours' ? ' hours' : '';

        return `${item.leave_type?.annual_quota ?? 'Unlimited'}${item.leave_type?.annual_quota != null ? unit : ''}`;
    };

    const unitLabel = (item) => (item.balance_unit === 'hours' ? 'hours' : 'days');

    const availableLabel = (item) => item.available ?? 'Unlimited';

    const renderDetailRow = (item) => {
        const isCompOff = item.is_comp_off || item.leave_type?.is_comp_off;

        return `<tr data-balance-id="${item.id}" data-is-comp-off="${isCompOff ? '1' : '0'}">
            <td>
                <span class="fw-semibold">${escapeHtml(item.leave_type?.name || '—')}</span>
                <div class="small text-muted">${escapeHtml(item.leave_type?.code || '')}</div>
            </td>
            <td>${quotaLabel(item)}</td>
            <td>${item.allocated} ${unitLabel(item)}</td>
            <td class="adjusted-cell">${item.adjusted}</td>
            <td class="used-cell">${item.used} ${unitLabel(item)}</td>
            <td>${item.pending} ${unitLabel(item)}</td>
            <td class="available-cell">${availableLabel(item)}${item.available != null ? ` ${unitLabel(item)}` : ''}</td>
            <td>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex gap-2 align-items-center">
                        <input type="number" class="form-control form-control-sm used-input" min="0" step="0.5" value="${item.used}" style="max-width: 90px;" title="Used days">
                        <button type="button" class="btn btn-sm btn-primary save-used-btn">Save Used</button>
                    </div>
                    ${isCompOff ? `
                    <div class="d-flex gap-2 align-items-center">
                        <input type="number" class="form-control form-control-sm adjusted-input" min="0" step="0.5" value="${item.adjusted}" style="max-width: 90px;" title="Comp off credited">
                        <button type="button" class="btn btn-sm btn-outline-success save-adjusted-btn">Set Credit</button>
                    </div>` : ''}
                </div>
            </td>
        </tr>`;
    };

    const updateCompOffSummary = (balances) => {
        const compOff = balances.find((item) => item.is_comp_off || item.leave_type?.is_comp_off);
        compOffBalanceId = compOff?.id || null;

        if (!compOffSummary) {
            return;
        }

        if (!compOff) {
            compOffSummary.textContent = 'Comp off leave type is not configured.';
            grantCompOffCard?.classList.add('d-none');

            return;
        }

        grantCompOffCard?.classList.remove('d-none');
        compOffSummary.textContent = `Credited: ${compOff.adjusted} · Used: ${compOff.used} · Pending: ${compOff.pending} · Available: ${compOff.available ?? 0}`;
    };

    const loadEmployeeDetail = async (employeeId) => {
        selectedEmployeeId = employeeId;
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';

        try {
            const { data } = await api.get(`/leave-balances/employees/${employeeId}`, {
                params: { year: yearSelect?.value || currentYear },
            });
            const employee = data.data.employee;
            const balances = data.data.balances || [];

            modalTitle.textContent = employee?.full_name || 'Employee balances';
            modalSubtitle.textContent = `${employee?.employee_code || '—'} · ${yearSelect?.value || currentYear}`;
            updateCompOffSummary(balances);
            tableBody.innerHTML = balances.length
                ? balances.map(renderDetailRow).join('')
                : '<tr><td colspan="8" class="text-center text-muted py-4">No balances found for this year.</td></tr>';

            if (modalInstance) {
                modalInstance.show();
            } else {
                showAlert('Unable to open the balance editor. Please refresh the page.', 'danger');
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const refreshDetailRow = (row, balance) => {
        row.querySelector('.used-cell').textContent = `${balance.used} ${balance.balance_unit === 'hours' ? 'hours' : 'days'}`;
        row.querySelector('.adjusted-cell').textContent = balance.adjusted;
        row.querySelector('.available-cell').textContent = `${balance.available ?? 'Unlimited'}${balance.available != null ? ` ${balance.balance_unit === 'hours' ? 'hours' : 'days'}` : ''}`;
        row.querySelector('.used-input').value = balance.used;

        const adjustedInput = row.querySelector('.adjusted-input');

        if (adjustedInput) {
            adjustedInput.value = balance.adjusted;
        }
    };

    const loadDepartments = async () => {
        if (!departmentSelect) {
            return;
        }

        try {
            const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
            const departments = data.data.departments || [];

            departmentSelect.innerHTML = '<option value="">All Departments</option>' + departments
                .map((department) => `<option value="${department.id}">${escapeHtml(department.name)}</option>`)
                .join('');
        } catch {
            // Keep default option only.
        }
    };

    matrixBody.addEventListener('click', (event) => {
        const manageBtn = event.target.closest('[data-manage-employee]');

        if (manageBtn) {
            loadEmployeeDetail(Number(manageBtn.dataset.manageEmployee));
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const saveUsedBtn = event.target.closest('.save-used-btn');
        const saveAdjustedBtn = event.target.closest('.save-adjusted-btn');

        if (!saveUsedBtn && !saveAdjustedBtn) {
            return;
        }

        const row = event.target.closest('tr');
        const balanceId = row?.dataset.balanceId;
        const payload = {};

        if (saveUsedBtn) {
            payload.used = Number(row.querySelector('.used-input')?.value);
        }

        if (saveAdjustedBtn) {
            payload.adjusted = Number(row.querySelector('.adjusted-input')?.value);
        }

        if (!balanceId) {
            return;
        }

        const button = saveUsedBtn || saveAdjustedBtn;
        button.disabled = true;

        try {
            const { data } = await api.patch(`/leave-balances/${balanceId}`, payload);
            refreshDetailRow(row, data.data.balance);
            showAlert('Leave balance updated successfully.');
            await loadEmployeeDetail(selectedEmployeeId);
            await loadOverview();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            button.disabled = false;
        }
    });

    grantCompOffBtn?.addEventListener('click', async () => {
        const days = Number(grantCompOffDays?.value);

        if (!compOffBalanceId) {
            showAlert('Comp off leave type is not available for this employee.', 'warning');

            return;
        }

        if (!days || days <= 0) {
            showAlert('Enter valid comp off days to grant.', 'warning');

            return;
        }

        grantCompOffBtn.disabled = true;

        try {
            await api.post(`/leave-balances/${compOffBalanceId}/grant-comp-off`, { days });
            showAlert(`Granted ${days} comp off day(s) successfully.`);
            await loadEmployeeDetail(selectedEmployeeId);
            await loadOverview();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            grantCompOffBtn.disabled = false;
        }
    });

    paginationList?.addEventListener('click', (event) => {
        const pageBtn = event.target.closest('[data-page]');

        if (!pageBtn) {
            return;
        }

        currentPage = Number(pageBtn.dataset.page);
        loadOverview();
    });

    yearSelect?.addEventListener('change', () => {
        currentPage = 1;
        loadOverview();
    });

    departmentSelect?.addEventListener('change', () => {
        currentPage = 1;
        loadOverview();
    });

    statusSelect?.addEventListener('change', () => {
        currentPage = 1;
        loadOverview();
    });

    searchInput?.addEventListener('input', debounce(() => {
        currentPage = 1;
        loadOverview();
    }, 400));

    await loadDepartments();
    await loadOverview();
});
