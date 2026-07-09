import api, { getErrorMessage } from './api';
import { composeActionGroup, renderCancelIconButton, renderViewLink } from './action-icons';
import { bindPagination, bindPerPageSelect, getSerialNumber, readPerPage, renderListPagination } from './pagination';
import { cancelRequest } from './request-review';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('leavesTableBody');
    const alertBox = document.getElementById('leavesAlert');
    const filterStatus = document.getElementById('filterStatus');
    const filterYear = document.getElementById('filterYear');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('leavesPaginationInfo');
    const paginationList = document.getElementById('leavesPaginationList');
    const perPageSelect = document.getElementById('leavesPerPage');
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    const currentYear = new Date().getFullYear();

    if (filterYear) {
        filterYear.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`).join('');
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderRow = (item, index, pagination) => {
        const serial = getSerialNumber(index, pagination);
        return `<tr>
            <td>${serial}</td>
            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
            <td>${item.leave_type?.name || '—'}</td>
            <td>${item.dates_label || item.from_date_label || '—'}</td>
            <td>${item.total_days_label || item.total_days}</td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            <td>${composeActionGroup({
                view: renderViewLink(`${routes().leaveShow || '/leave'}/${item.id}`, 'View leave request'),
                cancel: item.can_cancel && item.status === 'pending'
                    ? renderCancelIconButton('data-cancel-leave', item.id, 'Cancel leave request')
                    : '',
            })}</td>
        </tr>`;
    };

    const loadLeaves = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: currentPerPage, year: filterYear?.value || currentYear };
        if (filterStatus?.value) params.status = filterStatus.value;
        try {
            const { data } = await api.get('/leave-requests', { params });
            const requests = data.data.leave_requests || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = requests.length
                ? requests.map((item, i) => renderRow(item, i, pagination)).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-5">No leave requests found.</td></tr>';
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination,
                itemLabel: 'leave requests',
                emptyMessage: 'No leave requests found',
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    filterStatus?.addEventListener('change', () => loadLeaves(1));
    filterYear?.addEventListener('change', () => loadLeaves(1));
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        if (filterYear) filterYear.value = String(currentYear);
        loadLeaves(1);
    });
    bindPagination(paginationList, loadLeaves);
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadLeaves(1);
    });

    tableBody?.addEventListener('click', async (event) => {
        const cancel = event.target.closest('[data-cancel-leave]');

        if (!cancel) {
            return;
        }

        try {
            const message = await cancelRequest(`leave:${cancel.dataset.cancelLeave}`);

            if (message) {
                showAlert(message);
                await loadLeaves(currentPage);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const urlStatus = new URLSearchParams(window.location.search).get('status');
    if (urlStatus && filterStatus) {
        filterStatus.value = urlStatus;
    }

    await loadLeaves();
});
