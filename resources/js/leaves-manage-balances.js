import api, { getErrorMessage } from './api';

document.addEventListener('DOMContentLoaded', async () => {
    const employeeSelect = document.getElementById('balanceEmployee');
    const yearSelect = document.getElementById('balanceYear');
    const loadBtn = document.getElementById('loadBalancesBtn');
    const tableBody = document.getElementById('manageBalancesTableBody');
    const balancesCard = document.getElementById('balancesCard');
    const grantCompOffCard = document.getElementById('grantCompOffCard');
    const grantCompOffBtn = document.getElementById('grantCompOffBtn');
    const grantCompOffDays = document.getElementById('grantCompOffDays');
    const compOffSummary = document.getElementById('compOffSummary');
    const employeeTitle = document.getElementById('balancesEmployeeTitle');
    const alertBox = document.getElementById('manageBalancesAlert');
    const currentYear = new Date().getFullYear();
    let compOffBalanceId = null;

    if (!employeeSelect) return;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    if (yearSelect) {
        yearSelect.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`)
            .join('');
    }

    const loadEmployees = async () => {
        try {
            const { data } = await api.get('/employees', { params: { per_page: 100, status: 'active' } });
            const employees = data.data.employees || [];
            employeeSelect.innerHTML = '<option value="">Select employee</option>' + employees.map((emp) => `
                <option value="${emp.id}">${emp.full_name}${emp.employee_code ? ` (${emp.employee_code})` : ''}</option>
            `).join('');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
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

    const renderRow = (item) => {
        const isCompOff = item.is_comp_off || item.leave_type?.is_comp_off;

        return `<tr data-balance-id="${item.id}" data-is-comp-off="${isCompOff ? '1' : '0'}">
            <td>
                <span class="fw-semibold">${item.leave_type?.name || '—'}</span>
                <div class="small text-muted">${item.leave_type?.code || ''}</div>
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

        if (!compOffSummary) return;

        if (!compOff) {
            compOffSummary.textContent = 'Comp off leave type is not configured.';
            grantCompOffCard?.classList.add('d-none');
            return;
        }

        grantCompOffCard?.classList.remove('d-none');
        compOffSummary.textContent = `Credited: ${compOff.adjusted} · Used: ${compOff.used} · Pending: ${compOff.pending} · Available: ${compOff.available ?? 0}`;
    };

    const loadBalances = async () => {
        const employeeId = employeeSelect.value;
        const year = yearSelect?.value || currentYear;

        if (!employeeId) {
            showAlert('Please select an employee.', 'warning');
            return;
        }

        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
        balancesCard?.classList.remove('d-none');

        try {
            const { data } = await api.get(`/leave-balances/employees/${employeeId}`, { params: { year } });
            const employee = data.data.employee;
            const balances = data.data.balances || [];

            employeeTitle.textContent = `${employee?.full_name || 'Employee'} — ${year}`;
            updateCompOffSummary(balances);

            tableBody.innerHTML = balances.length
                ? balances.map(renderRow).join('')
                : '<tr><td colspan="8" class="text-center text-muted py-4">No balances found for this year.</td></tr>';
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const refreshRow = (row, balance) => {
        row.querySelector('.used-cell').textContent = balance.used;
        row.querySelector('.adjusted-cell').textContent = balance.adjusted;
        row.querySelector('.available-cell').textContent = balance.available ?? 'Unlimited';
        row.querySelector('.used-input').value = balance.used;
        row.querySelector('.adjusted-input') && (row.querySelector('.adjusted-input').value = balance.adjusted);
    };

    tableBody?.addEventListener('click', async (event) => {
        const saveUsedBtn = event.target.closest('.save-used-btn');
        const saveAdjustedBtn = event.target.closest('.save-adjusted-btn');

        if (!saveUsedBtn && !saveAdjustedBtn) return;

        const row = event.target.closest('tr');
        const balanceId = row?.dataset.balanceId;
        const payload = {};

        if (saveUsedBtn) {
            payload.used = Number(row.querySelector('.used-input')?.value);
        }

        if (saveAdjustedBtn) {
            payload.adjusted = Number(row.querySelector('.adjusted-input')?.value);
        }

        if (!balanceId || Number.isNaN(payload.used ?? 0) && payload.adjusted === undefined) return;

        const button = saveUsedBtn || saveAdjustedBtn;
        button.disabled = true;

        try {
            const { data } = await api.patch(`/leave-balances/${balanceId}`, payload);
            const balance = data.data.balance;
            refreshRow(row, balance);
            showAlert('Leave balance updated successfully.');
            await loadBalances();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            button.disabled = false;
        }
    });

    grantCompOffBtn?.addEventListener('click', async () => {
        const days = Number(grantCompOffDays?.value);

        if (!compOffBalanceId) {
            showAlert('Select an employee with a comp off balance first.', 'warning');
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
            await loadBalances();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            grantCompOffBtn.disabled = false;
        }
    });

    loadBtn?.addEventListener('click', loadBalances);
    employeeSelect.addEventListener('change', () => {
        if (employeeSelect.value) {
            loadBalances();
        } else {
            balancesCard?.classList.add('d-none');
            grantCompOffCard?.classList.add('d-none');
        }
    });

    await loadEmployees();
});
