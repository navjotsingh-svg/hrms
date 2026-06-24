import api, { getErrorMessage } from './api';
import { renderActionGroup } from './action-icons';
import {
    cancelRequest,
    renderRequestActions,
    reviewSingleRequest,
} from './request-review';
import { bindExpenseRequestViewModal, renderExpenseViewButton } from './expense-modals';

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

const categoryClass = (category) => ({
    leave: 'requests-type-pill--leave',
    regularization: 'requests-type-pill--regularization',
    document: 'requests-type-pill--document',
    payment_method: 'requests-type-pill--payment',
    family_member: 'requests-type-pill--family',
    personal_section: 'requests-type-pill--personal',
    compliance_field: 'requests-type-pill--compliance',
    expense: 'requests-type-pill--leave',
    expense_group: 'requests-type-pill--regularization',
    job_requisition: 'requests-type-pill--document',
}[category] || 'requests-type-pill--default');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('requestsTableBody');
    const alertBox = document.getElementById('requestsAlert');
    const statusFilter = document.getElementById('requestsStatusFilter');
    const pendingBadge = document.getElementById('requestsPendingBadge');
    const tabButtons = Array.from(document.querySelectorAll('[data-requests-tab]'));

    const hasApprovalTab = Boolean(document.getElementById('requestsTabApproval'));
    const hasTeamTab = Boolean(document.getElementById('requestsTabTeam'));
    let activeTab = hasApprovalTab ? 'approval' : 'mine';
    let approvalRequests = [];
    let teamRequests = [];
    let mineRequests = [];

    const expenseViewModal = bindExpenseRequestViewModal({
        onError: (message) => showAlert(message, 'danger'),
    });

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const setActiveTab = (tab) => {
        activeTab = tab;
        tabButtons.forEach((button) => {
            const isActive = button.dataset.requestsTab === tab;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (statusFilter) {
            statusFilter.disabled = tab === 'approval';

            if (tab === 'approval') {
                statusFilter.value = '';
            } else if (tab === 'team' && !statusFilter.value) {
                statusFilter.value = 'approved';
            }
        }

        renderTable();
    };

    const filteredRequests = () => {
        if (activeTab === 'approval') {
            return approvalRequests;
        }

        if (activeTab === 'team') {
            const source = teamRequests;

            if (!statusFilter?.value) {
                return source;
            }

            return source.filter((item) => item.status === statusFilter.value);
        }

        const source = mineRequests;

        if (!statusFilter?.value) {
            return source;
        }

        return source.filter((item) => item.status === statusFilter.value);
    };

    const renderReviewedLine = (item) => {
        if (!item.reviewed_at_label && !item.reviewed_by_name) {
            return '';
        }

        const by = item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : '';

        return `<div class="small text-muted mt-1">${item.reviewed_at_label || 'Reviewed'}${by}</div>`;
    };

    const renderActions = (item) => {
        if (item.category === 'expense' || item.category === 'expense_group') {
            const viewButton = item.category === 'expense'
                ? renderExpenseViewButton('expense', item.entity_id)
                : renderExpenseViewButton('expense_group', item.entity_id, 'View expense group');
            const reviewHtml = renderRequestActions({ ...item, view_url: null });

            if (reviewHtml === '<span class="text-muted">—</span>') {
                return renderActionGroup(viewButton);
            }

            return reviewHtml.replace('</div>', `${viewButton}</div>`).replace('<div class="table-action-group">', '<div class="table-action-group">');
        }

        return renderRequestActions(item);
    };

    const renderRow = (item) => {
        const showRequester = activeTab === 'approval' || activeTab === 'team';
        const requesterLine = showRequester
            ? `<div class="fw-semibold">${item.requester_name || 'Employee'}</div>${item.requester_code ? `<div class="small text-muted">${item.requester_code}</div>` : ''}`
            : `<div class="fw-semibold">${item.subject || '—'}</div>`;

        return `<tr>
            <td><span class="requests-type-pill ${categoryClass(item.category)}">${item.category_label || 'Request'}</span></td>
            <td>
                ${requesterLine}
                ${showRequester ? `<div class="small mt-1">${item.subject || '—'}</div>` : ''}
            </td>
            <td>
                ${item.detail ? `<div>${item.detail}</div>` : ''}
                ${item.reason ? `<div class="small text-muted text-truncate" style="max-width:260px">${item.reason}</div>` : ''}
            </td>
            <td class="text-nowrap">${item.submitted_at_label || '—'}</td>
            <td>
                <span class="company-status-pill ${statusClass(item.status)}">${item.status_label || '—'}</span>
                ${renderReviewedLine(item)}
            </td>
            <td class="text-end">${renderActions(item)}</td>
        </tr>`;
    };

    const emptyMessage = () => {
        if (activeTab === 'approval') {
            return 'No requests waiting for your approval.';
        }

        if (activeTab === 'team') {
            return 'No reviewed employee requests found.';
        }

        return 'No requests found.';
    };

    const renderTable = () => {
        if (!tableBody) return;

        const requests = filteredRequests();

        tableBody.innerHTML = requests.length
            ? requests.map((item) => renderRow(item)).join('')
            : `<tr><td colspan="6" class="text-center text-muted py-5">${emptyMessage()}</td></tr>`;
    };

    const loadSummary = async () => {
        try {
            const { data } = await api.get('/request-hub/summary');
            const count = data.data?.pending_count || 0;

            if (pendingBadge) {
                pendingBadge.textContent = String(count);
                pendingBadge.classList.toggle('d-none', count === 0);
            }
        } catch {
            pendingBadge?.classList.add('d-none');
        }
    };

    const loadApprovalRequests = async () => {
        if (!hasApprovalTab) return;

        try {
            const { data } = await api.get('/request-hub/pending');
            approvalRequests = data.data?.requests || [];
        } catch (error) {
            approvalRequests = [];
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadTeamRequests = async () => {
        if (!hasTeamTab) return;

        try {
            const params = {};
            if (statusFilter?.value && activeTab === 'team') {
                params.status = statusFilter.value;
            }

            const { data } = await api.get('/request-hub/team', { params });
            teamRequests = data.data?.requests || [];
        } catch (error) {
            teamRequests = [];
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadMineRequests = async () => {
        try {
            const params = {};
            if (statusFilter?.value && activeTab === 'mine') {
                params.status = statusFilter.value;
            }

            const { data } = await api.get('/request-hub/mine', { params });
            mineRequests = data.data?.requests || [];
        } catch (error) {
            mineRequests = [];
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const reload = async () => {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>';

        await Promise.all([
            loadSummary(),
            loadApprovalRequests(),
            loadTeamRequests(),
            loadMineRequests(),
        ]);

        renderTable();
    };

    const handleReview = async (token, action) => {
        try {
            const message = await reviewSingleRequest(token, action);
            if (!message) return;
            showAlert(message);
            await reload();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleCancel = async (token) => {
        try {
            const message = await cancelRequest(token);
            if (!message) return;
            showAlert(message);
            await reload();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            setActiveTab(button.dataset.requestsTab);

            if (button.dataset.requestsTab === 'team' && hasTeamTab) {
                await loadTeamRequests();
                renderTable();
            }
        });
    });

    statusFilter?.addEventListener('change', async () => {
        if (activeTab === 'mine') {
            await loadMineRequests();
        } else if (activeTab === 'team') {
            await loadTeamRequests();
        } else {
            return;
        }

        renderTable();
    });

    tableBody?.addEventListener('click', (event) => {
        const approve = event.target.closest('[data-approve-request]');
        const reject = event.target.closest('[data-reject-request]');
        const cancel = event.target.closest('[data-cancel-request]');
        const viewRequest = event.target.closest('[data-view-request]');

        if (viewRequest) {
            const [category, id] = viewRequest.dataset.viewRequest.split(':');

            if (category === 'expense') {
                expenseViewModal.openExpense(id);
            } else if (category === 'expense_group') {
                expenseViewModal.openExpenseGroup(id);
            }

            return;
        }

        if (approve) handleReview(approve.dataset.approveRequest, 'approve');
        if (reject) handleReview(reject.dataset.rejectRequest, 'reject');
        if (cancel) handleCancel(cancel.dataset.cancelRequest);
    });

    await reload();
});
