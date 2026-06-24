import api, { getErrorMessage } from './api';
import { renderActionGroup, renderDeleteButton, renderEditLink } from './action-icons';
import { consumePageFlashMessage } from './form-utils';
import { confirmLeaveTypeDelete } from './swal-utils';

const routes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('leaveTypesTableBody');
    const alertBox = document.getElementById('leaveTypesAlert');
    const paginationInfo = document.getElementById('leaveTypesPaginationInfo');
    const paginationList = document.getElementById('leaveTypesPaginationList');
    const filterSearch = document.getElementById('filterSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    let currentPage = 1;
    let searchTimeout = null;

    if (!tableBody) return;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderRow = (type, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const editUrl = `${routes().leaveTypeEdit || '/masters/leave-types'}/${type.id}/edit`;
        return `<tr>
            <td>${serial}</td>
            <td><span class="fw-semibold">${type.name}</span> <span class="text-muted">(${type.code})</span></td>
            <td>${type.annual_quota ?? 'Unlimited'}${type.quota_unit === 'hours' ? ' hrs' : type.annual_quota != null ? ' days' : ''}</td>
            <td class="small">${type.application_policy_label || '—'}</td>
            <td>${type.is_paid ? 'Yes' : 'No'}</td>
            <td>${type.requires_proof ? 'Yes' : 'No'}</td>
            <td><span class="company-status-pill ${type.status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${type.status}</span></td>
            <td>${renderActionGroup(`
                ${renderEditLink(editUrl, `Edit ${type.name}`)}
                ${renderDeleteButton('data-delete-type', type.id, `Delete ${type.name}`, type.name)}
            `)}</td>
        </tr>`;
    };

    const loadTypes = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: 10 };
        if (filterSearch?.value.trim()) params.search = filterSearch.value.trim();
        if (filterStatus?.value) params.status = filterStatus.value;

        try {
            const { data } = await api.get('/leave-types', { params });
            const types = data.data.leave_types || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = types.length
                ? types.map((type, i) => renderRow(type, i, pagination)).join('')
                : '<tr><td colspan="8" class="text-center text-muted py-5">No leave types found.</td></tr>';
            paginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No leave types found';
            paginationList.innerHTML = pagination?.last_page
                ? Array.from({ length: pagination.last_page }, (_, i) => {
                    const p = i + 1;
                    return `<li class="page-item ${p === pagination.current_page ? 'active' : ''}"><button type="button" class="page-link" data-page="${p}">${p}</button></li>`;
                }).join('')
                : '';
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadTypes(1), 400);
    });
    filterStatus?.addEventListener('change', () => loadTypes(1));
    filterReset?.addEventListener('click', () => {
        if (filterSearch) filterSearch.value = '';
        if (filterStatus) filterStatus.value = '';
        loadTypes(1);
    });
    paginationList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn) loadTypes(Number(btn.dataset.page));
    });
    tableBody.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-delete-type]');
        if (!btn) return;

        const typeName = btn.dataset.deleteName || 'this leave type';

        if (!await confirmLeaveTypeDelete(typeName)) {
            return;
        }

        try {
            const { data } = await api.delete(`/leave-types/${btn.dataset.deleteType}`);
            showAlert(data.message || 'Leave type has been deleted successfully.');
            loadTypes(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const flash = consumePageFlashMessage();
    if (flash?.message) showAlert(flash.message, flash.type || 'success');
    await loadTypes();
});
