import api, { getErrorMessage } from './api';
import { renderActionGroup, renderViewLink } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'text-bg-danger',
    cancelled: 'text-bg-secondary',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('leavesTableBody');
    const alertBox = document.getElementById('leavesAlert');
    const pendingContainer = document.getElementById('pendingLeavesContainer');
    const filterStatus = document.getElementById('filterStatus');
    const filterYear = document.getElementById('filterYear');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('leavesPaginationInfo');
    const paginationList = document.getElementById('leavesPaginationList');
    let currentPage = 1;
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

    const renderPending = (requests) => {
        if (!pendingContainer) return;
        if (!requests.length) {
            pendingContainer.innerHTML = '<div class="text-muted">No pending leave requests.</div>';
            return;
        }
        pendingContainer.innerHTML = requests.map((item) => `
            <div class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">${item.employee?.full_name || 'Employee'} · ${item.leave_type?.name}</div>
                        <div class="small text-muted">${item.from_date_label}${item.from_date === item.to_date ? '' : ` to ${item.to_date_label}`} · ${item.total_days_label || `${item.total_days} day(s)`}</div>
                        <div class="small mt-1">${item.reason}</div>
                    </div>
                    <div class="table-action-group">
                        ${renderViewLink(`${routes().leaveShow || '/leave'}/${item.id}`, 'View leave request')}
                        ${item.can_review ? `
                            ${renderApproveIconButton('data-approve-leave', item.id, 'Approve leave')}
                            ${renderRejectIconButton('data-reject-leave', item.id, 'Reject leave')}
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    };

    const renderRow = (item, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        return `<tr>
            <td>${serial}</td>
            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
            <td>${item.leave_type?.name || '—'}</td>
            <td>${item.from_date_label} - ${item.to_date_label}</td>
            <td>${item.total_days}</td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            <td>${renderActionGroup(renderViewLink(`${routes().leaveShow || '/leave'}/${item.id}`, 'View leave request'))}</td>
        </tr>`;
    };

    const loadPending = async () => {
        if (!pendingContainer) return;
        try {
            const { data } = await api.get('/leave-requests/pending');
            renderPending(data.data.leave_requests || []);
        } catch {
            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending requests.</div>';
        }
    };

    const loadLeaves = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: 10, year: filterYear?.value || currentYear };
        if (filterStatus?.value) params.status = filterStatus.value;
        try {
            const { data } = await api.get('/leave-requests', { params });
            const requests = data.data.leave_requests || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = requests.length
                ? requests.map((item, i) => renderRow(item, i, pagination)).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-5">No leave requests found.</td></tr>';
            paginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No leave requests found';
            paginationList.innerHTML = pagination?.last_page
                ? Array.from({ length: pagination.last_page }, (_, i) => {
                    const p = i + 1;
                    return `<li class="page-item ${p === pagination.current_page ? 'active' : ''}"><button type="button" class="page-link" data-page="${p}">${p}</button></li>`;
                }).join('')
                : '';
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const handleReview = async (id, action) => {
        try {
            if (action === 'approve') {
                await api.patch(`/leave-requests/${id}/approve`);
                showAlert('Leave request approved.');
            } else {
                const notes = prompt('Rejection reason:');
                if (!notes?.trim()) return;
                await api.patch(`/leave-requests/${id}/reject`, { notes: notes.trim() });
                showAlert('Leave request rejected.');
            }
            await Promise.all([loadPending(), loadLeaves(currentPage)]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    pendingContainer?.addEventListener('click', (event) => {
        const approve = event.target.closest('[data-approve-leave]');
        const reject = event.target.closest('[data-reject-leave]');
        if (approve) handleReview(approve.dataset.approveLeave, 'approve');
        if (reject) handleReview(reject.dataset.rejectLeave, 'reject');
    });

    filterStatus?.addEventListener('change', () => loadLeaves(1));
    filterYear?.addEventListener('change', () => loadLeaves(1));
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        if (filterYear) filterYear.value = String(currentYear);
        loadLeaves(1);
    });
    paginationList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn) loadLeaves(Number(btn.dataset.page));
    });

    const urlStatus = new URLSearchParams(window.location.search).get('status');
    if (urlStatus && filterStatus) {
        filterStatus.value = urlStatus;
    }

    await Promise.all([loadPending(), loadLeaves()]);
});
