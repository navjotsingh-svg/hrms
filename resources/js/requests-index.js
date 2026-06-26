import api, { getErrorMessage } from './api';

import { renderActionGroup } from './action-icons';

import {

    cancelRequest,

    renderRequestActions,

    reviewSingleRequest,

} from './request-review';

import { bindExpenseRequestViewModal, renderExpenseViewButton } from './expense-modals';

import { bindEmployeeSearchSelect } from './employee-autocomplete';

import { renderEmployeeNameBlock } from './request-display';



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



const countByStatus = (items) => ({

    total: items.length,

    pending: items.filter((item) => item.status === 'pending').length,

    approved: items.filter((item) => item.status === 'approved').length,

    rejected: items.filter((item) => item.status === 'rejected').length,

    cancelled: items.filter((item) => item.status === 'cancelled').length,

});



document.addEventListener('DOMContentLoaded', async () => {

    const tableBody = document.getElementById('requestsTableBody');

    const alertBox = document.getElementById('requestsAlert');

    const statusFilter = document.getElementById('requestsStatusFilter');

    const dateFromFilter = document.getElementById('requestsDateFrom');

    const dateToFilter = document.getElementById('requestsDateTo');

    const pendingBadge = document.getElementById('requestsPendingBadge');

    const tabButtons = Array.from(document.querySelectorAll('[data-requests-tab]'));

    const employeeFilterWrap = document.getElementById('requestsEmployeeFilterWrap');

    const filterReset = document.getElementById('requestsFilterReset');

    const summaryCards = document.getElementById('requestsSummaryCards');

    const countTotal = document.getElementById('requestsCountTotal');

    const countPending = document.getElementById('requestsCountPending');

    const countApproved = document.getElementById('requestsCountApproved');

    const countRejected = document.getElementById('requestsCountRejected');

    const countCancelled = document.getElementById('requestsCountCancelled');

    const countMeta = document.getElementById('requestsCountMeta');

    const statusCardButtons = Array.from(document.querySelectorAll('[data-requests-status]'));



    const hasApprovalTab = Boolean(document.getElementById('requestsTabApproval'));

    const hasTeamTab = Boolean(document.getElementById('requestsTabTeam'));

    let activeTab = hasApprovalTab ? 'approval' : 'mine';

    let approvalRequests = [];

    let teamRequests = [];

    let mineRequests = [];

    let employeeSearch = null;



    const expenseViewModal = bindExpenseRequestViewModal({

        onError: (message) => showAlert(message, 'danger'),

    });



    const showAlert = (message, type = 'success') => {

        if (!alertBox) return;

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;

        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;

        alertBox.classList.remove('d-none');

    };



    const selectedEmployeeId = () => employeeSearch?.getSelectedId?.()

        || document.getElementById('requestsEmployeeId')?.value

        || null;



    const currentSource = () => {

        if (activeTab === 'approval') {

            return approvalRequests;

        }



        if (activeTab === 'team') {

            return teamRequests;

        }



        return mineRequests;

    };



    const applyEmployeeFilter = (items) => {

        const employeeId = selectedEmployeeId();



        if (!employeeId || activeTab === 'mine') {

            return items;

        }



        return items.filter((item) => Number(item.employee_id) === Number(employeeId));

    };



    const applyStatusFilter = (items) => {

        if (activeTab === 'approval' || !statusFilter?.value) {

            return items;

        }



        return items.filter((item) => item.status === statusFilter.value);

    };



    const applyDateFilter = (items) => {

        const dateFrom = dateFromFilter?.value || '';

        const dateTo = dateToFilter?.value || '';



        if (!dateFrom && !dateTo) {

            return items;

        }



        return items.filter((item) => {

            const submittedOn = item.submitted_on;



            if (!submittedOn) {

                return false;

            }



            if (dateFrom && submittedOn < dateFrom) {

                return false;

            }



            if (dateTo && submittedOn > dateTo) {

                return false;

            }



            return true;

        });

    };



    const filteredRequests = () => applyDateFilter(applyStatusFilter(applyEmployeeFilter(currentSource())));



    const dateFilterParams = () => {

        const params = {};



        if (dateFromFilter?.value) {

            params.date_from = dateFromFilter.value;

        }



        if (dateToFilter?.value) {

            params.date_to = dateToFilter.value;

        }



        return params;

    };



    const tabLabel = () => {

        if (activeTab === 'approval') {

            return 'Awaiting your approval';

        }



        if (activeTab === 'team') {

            return 'Employee requests';

        }



        return 'My requests';

    };



    const updateEmployeeFilterVisibility = () => {

        if (!employeeFilterWrap) {

            return;

        }



        const showEmployeeFilter = (activeTab === 'approval' || activeTab === 'team') && (hasApprovalTab || hasTeamTab);

        employeeFilterWrap.classList.toggle('d-none', !showEmployeeFilter);

    };



    const renderSummaryCards = () => {

        const counts = countByStatus(applyEmployeeFilter(currentSource()));



        if (countTotal) countTotal.textContent = String(counts.total);

        if (countPending) countPending.textContent = String(counts.pending);

        if (countApproved) countApproved.textContent = String(counts.approved);

        if (countRejected) countRejected.textContent = String(counts.rejected);

        if (countCancelled) countCancelled.textContent = String(counts.cancelled);

        if (countMeta) countMeta.textContent = tabLabel();



        statusCardButtons.forEach((button) => {

            const status = button.dataset.requestsStatus || '';

            const isActive = activeTab === 'approval'

                ? status === 'pending'

                : (statusFilter?.value || '') === status;

            button.classList.toggle('is-active', isActive);

        });

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



        updateEmployeeFilterVisibility();

        renderSummaryCards();

        renderTable();

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



    const renderAttachmentHint = (item) => {
        const count = item.attachments?.length || 0;

        if (!count) {
            return '';
        }

        return `<div class="small text-muted mt-1">${count} attachment${count > 1 ? 's' : ''}</div>`;
    };

    const renderRow = (item) => {
        const requesterLine = renderEmployeeNameBlock(item.requester_name, item.requester_code);

        return `<tr>

            <td><span class="requests-type-pill ${categoryClass(item.category)}">${item.category_label || 'Request'}</span></td>

            <td>

                ${requesterLine}

                <div class="small mt-1">${item.subject || '—'}</div>

            </td>

            <td>

                ${item.detail ? `<div>${item.detail}</div>` : ''}

                ${renderAttachmentHint(item)}

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

        if (selectedEmployeeId()) {

            return 'No requests found for the selected employee.';

        }



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



        renderSummaryCards();

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

            const params = { ...dateFilterParams() };

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

            const params = { ...dateFilterParams() };

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



    const resetFilters = async () => {

        if (statusFilter) {

            statusFilter.value = activeTab === 'team' ? 'approved' : '';

        }



        if (dateFromFilter) dateFromFilter.value = '';

        if (dateToFilter) dateToFilter.value = '';



        employeeSearch?.clearSelection?.();



        if (activeTab === 'team') {

            await loadTeamRequests();

        } else if (activeTab === 'mine') {

            await loadMineRequests();

        }



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



    if (hasApprovalTab || hasTeamTab) {

        employeeSearch = bindEmployeeSearchSelect({

            inputId: 'requestsEmployeeInput',

            hiddenId: 'requestsEmployeeId',

            onSelect: () => renderTable(),

            onClear: () => renderTable(),

        });

    }



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

            renderTable();

            return;

        }



        renderTable();

    });



    const handleDateFilterChange = async () => {

        if (activeTab === 'mine') {

            await loadMineRequests();

        } else if (activeTab === 'team') {

            await loadTeamRequests();

        }



        renderTable();

    };



    dateFromFilter?.addEventListener('change', handleDateFilterChange);

    dateToFilter?.addEventListener('change', handleDateFilterChange);



    statusCardButtons.forEach((button) => {

        button.addEventListener('click', async () => {

            const status = button.dataset.requestsStatus || '';



            if (activeTab === 'approval') {

                if (status && status !== 'pending') {

                    setActiveTab('team');

                    if (statusFilter) {

                        statusFilter.value = status;

                    }

                    await loadTeamRequests();

                }



                renderTable();

                return;

            }



            if (!statusFilter) {

                renderTable();

                return;

            }



            statusFilter.value = status;



            if (activeTab === 'mine') {

                await loadMineRequests();

            } else if (activeTab === 'team') {

                await loadTeamRequests();

            }



            renderTable();

        });

    });



    filterReset?.addEventListener('click', resetFilters);



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



    updateEmployeeFilterVisibility();

    await reload();

});

