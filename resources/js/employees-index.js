import api, { getErrorMessage } from './api';
import { consumePageFlashMessage } from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('employeesTableBody');
    const alertBox = document.getElementById('employeesAlert');
    const paginationInfo = document.getElementById('employeesPaginationInfo');
    const paginationList = document.getElementById('employeesPaginationList');
    const filterSearch = document.getElementById('filterSearch');
    const filterDepartment = document.getElementById('filterDepartment');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const actionsHeader = document.getElementById('employeesActionsHeader');
    const routes = webRoutes();

    let currentPage = 1;
    let searchTimeout = null;
    let canManage = false;
    let canViewProfile = false;

    if (!tableBody) {
        return;
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderStatusCell = (employee) => {
        const isActive = employee.status === 'active';
        const pillClass = isActive ? 'company-status-pill--active' : 'company-status-pill--inactive';
        const label = isActive ? 'Active' : 'Inactive';

        if (!canManage) {
            return `<span class="company-status-pill ${pillClass}">${label}</span>`;
        }

        const switchId = `employee-status-${employee.id}`;

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${employee.id}"
                        ${isActive ? 'checked' : ''}
                    >
                    <label
                        class="form-check-label company-status-pill ${pillClass}"
                        for="${switchId}"
                        data-status-label="${employee.id}"
                    >
                        ${label}
                    </label>
                </div>
            </div>
        `;
    };

    const setStatusLabel = (employeeId, status) => {
        const label = tableBody.querySelector(`[data-status-label="${employeeId}"]`);

        if (label) {
            label.textContent = status === 'active' ? 'Active' : 'Inactive';
            label.classList.remove('company-status-pill--active', 'company-status-pill--inactive');
            label.classList.add(status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive');
        }
    };

    const renderPortalBadge = (employee) => {
        return employee.has_portal_access
            ? '<span class="badge text-bg-success">Active</span>'
            : '<span class="badge text-bg-light text-muted">None</span>';
    };

    const MAIL_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/></svg>';

    const renderActions = (employee) => {
        if (!canManage && !canViewProfile) {
            return '';
        }

        const editUrl = `${routes.employeeEdit || '/employees'}/${employee.id}/edit`;
        const profileUrl = `${routes.employeeShow || '/employees'}/${employee.id}`;

        return `
            <td class="companies-td-actions">
                <div class="table-action-group">
                    ${canViewProfile ? `
                    <a href="${profileUrl}" class="table-action-btn table-action-btn--view" title="View Profile" aria-label="View profile for ${employee.full_name}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                    </a>
                    ` : ''}
                    ${canManage ? `
                    ${employee.has_portal_access ? `
                    <button type="button" class="table-action-btn table-action-btn--mail" title="Resend welcome email" aria-label="Resend welcome email to ${employee.full_name}" data-resend-welcome-email="${employee.id}">
                        ${MAIL_ICON}
                    </button>
                    ` : ''}
                    <a href="${editUrl}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${employee.full_name}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                    </a>
                    <button type="button" class="table-action-btn table-action-btn--delete" title="Delete" aria-label="Delete ${employee.full_name}" data-delete-employee="${employee.id}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                    </button>
                    ` : ''}
                </div>
            </td>
        `;
    };

    const columnCount = () => (canManage || canViewProfile ? 8 : 7);

    const renderRow = (employee, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const departmentName = employee.departments?.length
            ? employee.departments.map((department) => department.name).join(', ')
            : (employee.department?.name || '—');
        const roleName = employee.role?.name || '—';

        return `
            <tr class="companies-data-row">
                <td class="companies-td-serial"><span class="companies-serial">${serial}</span></td>
                <td>
                    <div class="companies-company-info">
                        <span class="companies-company-name">${employee.full_name}</span>
                        <span class="companies-company-meta">${employee.email}${employee.designation ? ` · ${employee.designation}` : ''}</span>
                    </div>
                </td>
                <td>${employee.employee_code || '—'}</td>
                <td>${departmentName}</td>
                <td>${roleName}</td>
                <td>${renderPortalBadge(employee)}</td>
                <td>${renderStatusCell(employee)}</td>
                ${renderActions(employee)}
            </tr>
        `;
    };

    const renderPagination = (pagination) => {
        if (!paginationList || !paginationInfo) {
            return;
        }

        if (!pagination?.total) {
            paginationInfo.textContent = 'No employees found';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} employees`;

        const pages = Array.from({ length: pagination.last_page }, (_, index) => {
            const page = index + 1;

            return `
                <li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${page}">${page}</button>
                </li>
            `;
        }).join('');

        paginationList.innerHTML = pages;
    };

    const applyCapabilities = (capabilities = {}) => {
        canManage = Boolean(capabilities.can_manage);
        canViewProfile = Boolean(capabilities.can_view_profile);
        actionsHeader?.classList.toggle('d-none', !canManage && !canViewProfile);
    };

    const loadDepartments = async () => {
        if (!filterDepartment) {
            return;
        }

        try {
            const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
            const departments = data.data.departments || [];

            filterDepartment.innerHTML = '<option value="">All departments</option>' + departments
                .map((department) => `<option value="${department.id}">${department.name}</option>`)
                .join('');
        } catch (error) {
            console.error('Failed to load departments', error);
        }
    };

    const loadEmployees = async (page = 1) => {
        currentPage = page;

        const params = { page, per_page: 10 };

        if (filterSearch?.value.trim()) {
            params.search = filterSearch.value.trim();
        }

        if (filterDepartment?.value) {
            params.department_id = filterDepartment.value;
        }

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        try {
            const { data } = await api.get('/employees', { params });
            const employees = data.data.employees || [];
            const pagination = data.data.pagination;

            applyCapabilities(data.data.capabilities);

            if (employees.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${columnCount()}" class="text-center text-muted py-5">No employees found.</td></tr>`;
            } else {
                tableBody.innerHTML = employees.map((employee, index) => renderRow(employee, index, pagination)).join('');
            }

            renderPagination(pagination);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadEmployees(1), 400);
    });

    filterDepartment?.addEventListener('change', () => loadEmployees(1));
    filterStatus?.addEventListener('change', () => loadEmployees(1));

    filterReset?.addEventListener('click', () => {
        if (filterSearch) {
            filterSearch.value = '';
        }

        if (filterDepartment) {
            filterDepartment.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        loadEmployees(1);
    });

    paginationList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button) {
            return;
        }

        loadEmployees(Number(button.dataset.page));
    });

    tableBody.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-status-toggle]');

        if (!toggle || !canManage) {
            return;
        }

        const employeeId = toggle.dataset.statusToggle;
        const status = toggle.checked ? 'active' : 'inactive';
        const previousChecked = !toggle.checked;

        toggle.disabled = true;

        try {
            const { data } = await api.patch(`/employees/${employeeId}/status`, { status });
            setStatusLabel(employeeId, data.data.employee.status);
            showAlert(data.message || 'Employee status updated successfully.');
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusLabel(employeeId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const resendButton = event.target.closest('[data-resend-welcome-email]');

        if (resendButton && canManage) {
            if (!window.confirm('Send a new welcome email with a freshly generated password? The employee will need to use the new password to sign in.')) {
                return;
            }

            resendButton.disabled = true;

            try {
                const { data } = await api.post(`/employees/${resendButton.dataset.resendWelcomeEmail}/resend-welcome-email`);
                showAlert(data.message || 'Welcome email sent with a new login password.');
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                resendButton.disabled = false;
            }

            return;
        }

        const button = event.target.closest('[data-delete-employee]');

        if (!button || !canManage) {
            return;
        }

        if (!window.confirm('Delete this employee? Their login account will also be removed.')) {
            return;
        }

        try {
            const { data } = await api.delete(`/employees/${button.dataset.deleteEmployee}`);
            showAlert(data.message || 'Employee deleted successfully.');
            await loadEmployees(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    await loadDepartments();
    await loadEmployees();
});
