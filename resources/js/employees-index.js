import api, { getErrorMessage } from './api';
import { consumePageFlashMessage } from './form-utils';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { bindPagination, bindPerPageSelect, getSerialNumber, readPerPage, renderListPagination } from './pagination';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const LAYOUT_STORAGE_KEY = 'hrms-employees-layout';
const AVATAR_COLORS = ['#8b5a3c', '#6b4c35', '#2f6b4f', '#7c5c8a', '#3b6f8f', '#8b4f5c'];

const avatarColor = (seed = '') => {
    let hash = 0;

    for (let i = 0; i < seed.length; i += 1) {
        hash = seed.charCodeAt(i) + ((hash << 5) - hash);
    }

    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const employeeInitials = (employee) => {
    const parts = String(employee.full_name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }

    return (parts[0]?.slice(0, 2) || 'EM').toUpperCase();
};

const departmentLabel = (employee) => {
    if (employee.departments?.length) {
        return employee.departments.map((department) => department.name).join(', ');
    }

    return employee.department?.name || '—';
};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('employeesTableBody');
    const cardGrid = document.getElementById('employeesCardGrid');
    const tableView = document.getElementById('employeesTableView');
    const cardView = document.getElementById('employeesCardView');
    const listContainer = document.getElementById('employeesListContainer');
    const layoutToggleButtons = document.querySelectorAll('[data-layout]');
    const alertBox = document.getElementById('employeesAlert');
    const paginationInfo = document.getElementById('employeesPaginationInfo');
    const paginationList = document.getElementById('employeesPaginationList');
    const perPageSelect = document.getElementById('employeesPerPage');
    const filterDepartment = document.getElementById('filterDepartment');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const actionsHeader = document.getElementById('employeesActionsHeader');
    const routes = webRoutes();

    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let currentLayout = localStorage.getItem(LAYOUT_STORAGE_KEY) === 'cards' ? 'cards' : 'table';
    let canManage = false;
    let canViewProfile = false;
    let canAssignAdmin = false;
    let employeeSearch = null;
    let selectedEmployee = null;

    if (!tableBody || !cardGrid) {
        return;
    }

    const setStatusToggle = (employeeId, status) => {
        document.querySelectorAll(`[data-status-toggle="${employeeId}"]`).forEach((toggle) => {
            toggle.checked = status === 'active';
        });
    };

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
        const switchId = `employee-status-${employee.id}`;
        const disabled = canManage ? '' : 'disabled';

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${employee.id}"
                        ${isActive ? 'checked' : ''}
                        ${disabled}
                        aria-label="Toggle employee status"
                    >
                </div>
            </div>
        `;
    };

    const setPortalToggle = (employeeId, hasPortal) => {
        document.querySelectorAll(`[data-portal-toggle="${employeeId}"]`).forEach((toggle) => {
            toggle.checked = hasPortal;
        });
    };

    const renderPortalCell = (employee) => {
        const hasPortal = Boolean(employee.has_portal_access);
        const switchId = `employee-portal-${employee.id}`;
        const isInactive = employee.status !== 'active';
        const disabled = !canManage || isInactive ? 'disabled' : '';

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-portal-toggle="${employee.id}"
                        ${hasPortal ? 'checked' : ''}
                        ${disabled}
                        aria-label="Toggle portal access"
                    >
                </div>
            </div>
        `;
    };


    const MAIL_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/></svg>';

    const ADMIN_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1.5 2 4v4.5c0 3.1 2.5 5.5 6 6.5 3.5-1 6-3.4 6-6.5V4L8 1.5Z"/></svg>';

    const REMOVE_ADMIN_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1.5 2 4v4.5c0 3.1 2.5 5.5 6 6.5 3.5-1 6-3.4 6-6.5V4L8 1.5Z"/><path d="m4.5 5.5 7 7M11.5 5.5l-7 7" stroke="currentColor" stroke-width="1.5"/></svg>';

    const renderActionButtons = (employee) => {
        if (!canManage && !canViewProfile && !canAssignAdmin) {
            return '';
        }

        const editUrl = `${routes.employeeEdit || '/employees'}/${employee.id}/edit`;
        const profileUrl = `${routes.employeeShow || '/employees'}/${employee.id}`;
        const portalHint = employee.has_portal_access
            ? ''
            : ' Portal access will be enabled automatically.';

        return `
            <div class="table-action-group">
                ${canViewProfile ? `
                <a href="${profileUrl}" class="table-action-btn table-action-btn--view" title="View Profile" aria-label="View profile for ${escapeHtml(employee.full_name)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                </a>
                ` : ''}
                ${canAssignAdmin && employee.is_company_admin ? `
                <button type="button" class="table-action-btn table-action-btn--reject" title="Remove Company Admin" aria-label="Remove company administrator access from ${escapeHtml(employee.full_name)}" data-remove-admin="${employee.id}" data-employee-name="${escapeHtml(employee.full_name)}">
                    ${REMOVE_ADMIN_ICON}
                </button>
                ` : ''}
                ${canAssignAdmin && !employee.is_company_admin ? `
                <button type="button" class="table-action-btn table-action-btn--approve" title="Make Company Admin${portalHint}" aria-label="Make ${escapeHtml(employee.full_name)} a company administrator" data-make-admin="${employee.id}" data-employee-name="${escapeHtml(employee.full_name)}">
                    ${ADMIN_ICON}
                </button>
                ` : ''}
                ${canManage ? `
                ${employee.has_portal_access ? `
                <button type="button" class="table-action-btn table-action-btn--mail" title="Resend welcome email" aria-label="Resend welcome email to ${escapeHtml(employee.full_name)}" data-resend-welcome-email="${employee.id}">
                    ${MAIL_ICON}
                </button>
                ` : ''}
                <a href="${editUrl}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${escapeHtml(employee.full_name)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                </a>
                ` : ''}
            </div>
        `;
    };

    const renderActions = (employee) => {
        const buttons = renderActionButtons(employee);

        if (!buttons) {
            return '';
        }

        return `<td class="companies-td-actions">${buttons}</td>`;
    };

    const columnCount = () => (canManage || canViewProfile || canAssignAdmin ? 7 : 6);

    const nonPaidBadge = (employee) => (
        employee.is_paid_employee === false
            ? ' <span class="badge text-bg-secondary ms-1">Non-paid</span>'
            : ''
    );

    const renderRow = (employee, index, pagination) => {
        const serial = getSerialNumber(index, pagination);
        const roleName = employee.is_company_admin
            ? `${escapeHtml(employee.role?.name || 'Company Admin')} <span class="badge text-bg-primary ms-1">Admin</span>`
            : escapeHtml(employee.role?.name || '—');

        return `
            <tr class="companies-data-row">
                <td class="companies-td-serial"><span class="companies-serial">${serial}</span></td>
                <td>
                    <div class="companies-company-info">
                        <span class="companies-company-name">${escapeHtml(employee.full_name)}${nonPaidBadge(employee)}</span>
                        <span class="companies-company-meta">${escapeHtml(employee.email)}${employee.designation ? ` · ${escapeHtml(employee.designation)}` : ''}</span>
                        <span class="companies-company-meta">${escapeHtml(employee.employee_code || '—')}</span>
                    </div>
                </td>
                <td>${escapeHtml(departmentLabel(employee))}</td>
                <td>${roleName}</td>
                <td>${renderPortalCell(employee)}</td>
                <td>${renderStatusCell(employee)}</td>
                ${renderActions(employee)}
            </tr>
        `;
    };

    const PHONE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58z"/></svg>';
    const EMAIL_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/></svg>';

    const renderCard = (employee) => {
        const profileUrl = `${routes.employeeShow || '/employees'}/${employee.id}`;
        const initials = employeeInitials(employee);
        const color = avatarColor(employee.full_name || employee.email || String(employee.id));
        const designation = employee.designation || employee.role?.name || 'Employee';
        const managerName = employee.manager?.full_name;
        const phone = employee.phone || '—';
        const email = employee.email || '—';
        const actionButtons = renderActionButtons(employee);
        const showFooter = canManage || actionButtons;

        return `
            <article class="employees-card">
                <div class="employees-card-body">
                    ${canViewProfile
                        ? (employee.profile_photo_url
                            ? `<a href="${profileUrl}" class="employees-card-avatar employees-card-avatar-link employees-card-avatar--photo" title="View profile" aria-label="View profile for ${escapeHtml(employee.full_name)}"><img src="${escapeHtml(employee.profile_photo_url)}" alt="" class="employees-card-avatar-img"></a>`
                            : `<a href="${profileUrl}" class="employees-card-avatar employees-card-avatar-link" style="background:${color}" title="View profile" aria-label="View profile for ${escapeHtml(employee.full_name)}">${escapeHtml(initials)}</a>`)
                        : (employee.profile_photo_url
                            ? `<span class="employees-card-avatar employees-card-avatar--photo"><img src="${escapeHtml(employee.profile_photo_url)}" alt="" class="employees-card-avatar-img"></span>`
                            : `<span class="employees-card-avatar" style="background:${color}">${escapeHtml(initials)}</span>`)}
                    ${canViewProfile
                        ? `<a href="${profileUrl}" class="employees-card-name">${escapeHtml(employee.full_name)}</a>`
                        : `<div class="employees-card-name">${escapeHtml(employee.full_name)}</div>`}
                    <div class="employees-card-designation">${escapeHtml(designation)}${nonPaidBadge(employee)}</div>
                    <div class="employees-card-code">${escapeHtml(employee.employee_code || '—')} · ${escapeHtml(departmentLabel(employee))}</div>
                    <div class="employees-card-contacts">
                        <div class="employees-card-contact">
                            ${PHONE_ICON}
                            <span>${escapeHtml(phone)}</span>
                        </div>
                        <div class="employees-card-contact">
                            ${EMAIL_ICON}
                            ${employee.email
                                ? `<a href="mailto:${escapeHtml(employee.email)}">${escapeHtml(email)}</a>`
                                : `<span>${escapeHtml(email)}</span>`}
                        </div>
                    </div>
                    ${managerName ? `<div class="employees-card-manager">Line Manager <strong>${escapeHtml(managerName)}</strong></div>` : ''}
                </div>
                ${showFooter ? `
                <div class="employees-card-footer">
                    ${canManage ? `
                    <div class="employees-card-toggles-row">
                        <div class="employees-card-toggles">
                            <div class="employees-card-toggle-item">
                                <span>Portal</span>
                                ${renderPortalCell(employee)}
                            </div>
                            <div class="employees-card-toggle-item">
                                <span>Status</span>
                                ${renderStatusCell(employee)}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    ${actionButtons ? `
                    <div class="employees-card-actions-row">
                        ${actionButtons}
                    </div>
                    ` : ''}
                </div>
                ` : ''}
            </article>
        `;
    };

    const renderPagination = (pagination) => {
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination,
            itemLabel: 'employees',
            emptyMessage: 'No employees found',
        });
    };

    const applyCapabilities = (capabilities = {}) => {
        canManage = Boolean(capabilities.can_manage);
        canViewProfile = Boolean(capabilities.can_view_profile);
        canAssignAdmin = Boolean(capabilities.can_assign_admin);
        actionsHeader?.classList.toggle('d-none', !canManage && !canViewProfile && !canAssignAdmin);
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

    const applyLayout = (layout, { reload = false } = {}) => {
        currentLayout = layout === 'cards' ? 'cards' : 'table';
        localStorage.setItem(LAYOUT_STORAGE_KEY, currentLayout);

        tableView?.classList.toggle('d-none', currentLayout !== 'table');
        cardView?.classList.toggle('d-none', currentLayout !== 'cards');

        layoutToggleButtons.forEach((button) => {
            const isActive = button.dataset.layout === currentLayout;
            button.classList.toggle('employees-layout-btn--active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (reload) {
            loadEmployees(1);
        }
    };

    const renderEmployeeList = (employees, pagination) => {
        if (employees.length === 0) {
            if (currentLayout === 'table') {
                tableBody.innerHTML = `<tr><td colspan="${columnCount()}" class="text-center text-muted py-5">No employees found.</td></tr>`;
            } else {
                cardGrid.innerHTML = '<div class="employees-card-empty text-center text-muted py-5">No employees found.</div>';
            }
            return;
        }

        if (currentLayout === 'table') {
            tableBody.innerHTML = employees.map((employee, index) => renderRow(employee, index, pagination)).join('');
            return;
        }

        cardGrid.innerHTML = employees.map((employee) => renderCard(employee)).join('');
    };

    const renderLoadingState = () => {
        const loadingMessage = 'Loading employees...';

        if (currentLayout === 'table') {
            tableBody.innerHTML = `<tr><td colspan="${columnCount()}" class="text-center text-muted py-5">${loadingMessage}</td></tr>`;
            return;
        }

        cardGrid.innerHTML = `<div class="employees-card-empty text-center text-muted py-5">${loadingMessage}</div>`;
    };

    const loadEmployees = async (page = 1) => {
        currentPage = page;
        renderLoadingState();

        const params = {
            page,
            per_page: currentPerPage,
        };

        if (selectedEmployee?.id) {
            params.employee_id = selectedEmployee.id;
        } else if (selectedEmployee?.employee?.employee_code) {
            params.search = selectedEmployee.employee.employee_code;
        } else if (selectedEmployee?.employee) {
            const name = selectedEmployee.employee.full_name
                || `${selectedEmployee.employee.first_name || ''} ${selectedEmployee.employee.last_name || ''}`.trim();

            if (name) {
                params.search = name;
            }
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
            renderEmployeeList(employees, pagination);
            renderPagination(pagination);
        } catch (error) {
            const message = escapeHtml(getErrorMessage(error));

            if (currentLayout === 'table') {
                tableBody.innerHTML = `<tr><td colspan="${columnCount()}" class="text-center text-danger py-5">${message}</td></tr>`;
            } else {
                cardGrid.innerHTML = `<div class="employees-card-empty text-center text-danger py-5">${message}</div>`;
            }
        }
    };

    employeeSearch = bindEmployeeSearchSelect({
        inputId: 'filterEmployeeInput',
        hiddenId: 'filterEmployeeId',
        onSelect: (item) => {
            selectedEmployee = item;
            loadEmployees(1);
        },
        onClear: () => {
            selectedEmployee = null;
            loadEmployees(1);
        },
    });

    layoutToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const layout = button.dataset.layout;

            if (!layout || layout === currentLayout) {
                return;
            }

            applyLayout(layout, { reload: true });
        });
    });

    filterDepartment?.addEventListener('change', () => loadEmployees(1));
    filterStatus?.addEventListener('change', () => loadEmployees(1));

    filterReset?.addEventListener('click', () => {
        selectedEmployee = null;
        employeeSearch?.clearSelection();

        if (filterDepartment) {
            filterDepartment.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        loadEmployees(1);
    });

    bindPagination(paginationList, loadEmployees);
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadEmployees(1);
    });

    listContainer?.addEventListener('change', async (event) => {
        const portalToggle = event.target.closest('[data-portal-toggle]');

        if (portalToggle && canManage) {
            const employeeId = portalToggle.dataset.portalToggle;
            const portalAccess = portalToggle.checked;
            const previousChecked = !portalToggle.checked;

            if (portalAccess && !window.confirm('Enable portal access? A welcome email with login credentials will be sent to the employee.')) {
                portalToggle.checked = previousChecked;
                return;
            }

            if (!portalAccess && !window.confirm('Disable portal access? The employee will be signed out and cannot log in until access is restored.')) {
                portalToggle.checked = previousChecked;
                return;
            }

            portalToggle.disabled = true;

            try {
                const { data } = await api.patch(`/employees/${employeeId}/portal-access`, {
                    portal_access: portalAccess,
                });
                setPortalToggle(employeeId, Boolean(data.data.employee.has_portal_access));
                showAlert(data.message || 'Portal access updated successfully.');
                await loadEmployees(currentPage);
            } catch (error) {
                portalToggle.checked = previousChecked;
                setPortalToggle(employeeId, previousChecked);
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                portalToggle.disabled = false;
            }

            return;
        }

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
            setStatusToggle(employeeId, data.data.employee.status);
            setPortalToggle(employeeId, Boolean(data.data.employee.has_portal_access));
            showAlert(data.message || 'Employee status updated successfully.');
            await loadEmployees(currentPage);
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusToggle(employeeId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    listContainer?.addEventListener('click', async (event) => {
        const makeAdminButton = event.target.closest('[data-make-admin]');

        if (makeAdminButton && canAssignAdmin) {
            const employeeId = makeAdminButton.dataset.makeAdmin;
            const employeeName = makeAdminButton.dataset.employeeName || 'this employee';
            const portalNote = makeAdminButton.title.includes('Portal access')
                ? ' Portal access will be enabled and a welcome email will be sent.'
                : '';

            if (!window.confirm(`Make "${employeeName}" a Company Administrator? They will get full access to all modules.${portalNote}`)) {
                return;
            }

            makeAdminButton.disabled = true;

            try {
                const { data } = await api.patch(`/employees/${employeeId}/make-admin`);
                showAlert(data.message || 'Employee is now a company administrator.');
                await loadEmployees(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
                makeAdminButton.disabled = false;
            }

            return;
        }

        const removeAdminButton = event.target.closest('[data-remove-admin]');

        if (removeAdminButton && canAssignAdmin) {
            const employeeId = removeAdminButton.dataset.removeAdmin;
            const employeeName = removeAdminButton.dataset.employeeName || 'this employee';

            if (!window.confirm(`Remove company administrator access from "${employeeName}"? They will be changed to the Employee role.`)) {
                return;
            }

            removeAdminButton.disabled = true;

            try {
                const { data } = await api.patch(`/employees/${employeeId}/remove-admin`);
                showAlert(data.message || 'Administrator access removed.');
                await loadEmployees(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
                removeAdminButton.disabled = false;
            }

            return;
        }

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
    });

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    applyLayout(currentLayout);
    void loadDepartments();
    await loadEmployees();
});
