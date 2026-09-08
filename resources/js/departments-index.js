import api, { getErrorMessage } from './api';
import { consumePageFlashMessage } from './form-utils';
import { bindPagination, bindPerPageSelect, getSerialNumber, readPerPage, renderListPagination } from './pagination';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('departmentsTableBody');
    const alertBox = document.getElementById('departmentsAlert');
    const paginationInfo = document.getElementById('departmentsPaginationInfo');
    const paginationList = document.getElementById('departmentsPaginationList');
    const perPageSelect = document.getElementById('departmentsPerPage');
    const filterSearch = document.getElementById('filterSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const routes = webRoutes();

    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let searchTimeout = null;

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

    const renderStatusToggle = (department) => {
        const isActive = department.status === 'active';
        const switchId = `department-status-${department.id}`;

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${department.id}"
                        ${isActive ? 'checked' : ''}
                        aria-label="Toggle department status"
                    >
                </div>
            </div>
        `;
    };

    const setStatusToggle = (departmentId, status) => {
        const toggle = tableBody.querySelector(`[data-status-toggle="${departmentId}"]`);

        if (toggle) {
            toggle.checked = status === 'active';
        }
    };

    const renderRow = (department, index, pagination) => {
        const serial = getSerialNumber(index, pagination);
        const editUrl = `${routes.departmentEdit || '/masters/departments'}/${department.id}/edit`;

        return `
            <tr class="companies-data-row">
                <td class="companies-td-serial"><span class="companies-serial">${serial}</span></td>
                <td>
                    <div class="companies-company-info">
                        <span class="companies-company-name">${department.name}</span>
                        ${department.description ? `<span class="companies-company-meta">${department.description}</span>` : ''}
                    </div>
                </td>
                <td>${department.code || '—'}</td>
                <td>${renderStatusToggle(department)}</td>
                <td class="companies-td-actions">
                    <div class="table-action-group">
                        <a href="${editUrl}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${department.name}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                        </a>
                        <button type="button" class="table-action-btn table-action-btn--delete" title="Delete" aria-label="Delete ${department.name}" data-delete-department="${department.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    };

    const renderPagination = (pagination) => {
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination,
            itemLabel: 'departments',
            emptyMessage: 'No departments found',
        });
    };

    const loadDepartments = async (page = 1) => {
        currentPage = page;

        const params = { page, per_page: currentPerPage };

        if (filterSearch?.value.trim()) {
            params.search = filterSearch.value.trim();
        }

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        try {
            const { data } = await api.get('/departments', { params });
            const departments = data.data.departments || [];
            const pagination = data.data.pagination;

            if (departments.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5">No departments found.</td></tr>';
            } else {
                tableBody.innerHTML = departments.map((department, index) => renderRow(department, index, pagination)).join('');
            }

            renderPagination(pagination);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadDepartments(1), 400);
    });

    filterStatus?.addEventListener('change', () => loadDepartments(1));

    filterReset?.addEventListener('click', () => {
        if (filterSearch) {
            filterSearch.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        loadDepartments(1);
    });

    bindPagination(paginationList, loadDepartments);
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadDepartments(1);
    });

    tableBody.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-status-toggle]');

        if (!toggle) {
            return;
        }

        const departmentId = toggle.dataset.statusToggle;
        const status = toggle.checked ? 'active' : 'inactive';
        const previousChecked = !toggle.checked;

        toggle.disabled = true;

        try {
            const { data } = await api.patch(`/departments/${departmentId}/status`, { status });
            setStatusToggle(departmentId, data.data.department.status);
            showAlert(data.message || 'Department status updated successfully.');
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusToggle(departmentId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-department]');

        if (!button) {
            return;
        }

        if (!window.confirm('Delete this department?')) {
            return;
        }

        try {
            const { data } = await api.delete(`/departments/${button.dataset.deleteDepartment}`);
            showAlert(data.message || 'Department deleted successfully.');
            await loadDepartments(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    await loadDepartments();
});
