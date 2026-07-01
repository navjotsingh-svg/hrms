import api, { getErrorMessage } from './api';

import {

    cancelRequest,

    renderRequestActions,

    reviewSingleRequest,

} from './request-review';

import { bindExpenseRequestViewModal, renderExpenseViewButton } from './expense-modals';

import { bindEmployeeSearchSelect } from './employee-autocomplete';

import { renderEmployeeNameBlock } from './request-display';

import {
    DATE_RANGE_PRESET_CUSTOM,
    dateRangeForPreset,
    detectDateRangePreset,
    resolveDateRange,
    saveRequestsListState,
    showAutoDismissAlert,
} from './form-utils';



const statusClass = (status) => ({

    pending: 'company-status-pill--inactive',

    approved: 'company-status-pill--active',

    rejected: 'company-status-pill--rejected',

    cancelled: 'company-status-pill--cancelled',

}[status] || '');



const categoryClass = (category) => ({

    leave: 'requests-type-pill--leave',

    wfh: 'requests-type-pill--regularization',

    asset: 'requests-type-pill--document',

    regularization: 'requests-type-pill--regularization',

    document: 'requests-type-pill--document',

    payment_method: 'requests-type-pill--payment',

    profile_photo: 'requests-type-pill--profile-photo',

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



const dedupeRequests = (items) => {

    const map = new Map();



    items.forEach((item) => {

        const key = item.key || `${item.category}:${item.entity_id}:${item.batch_id || ''}`;

        if (!map.has(key)) {

            map.set(key, item);

        }

    });



    return Array.from(map.values());

};



document.addEventListener('DOMContentLoaded', async () => {

    const tableBody = document.getElementById('requestsTableBody');

    const alertBox = document.getElementById('requestsAlert');

    const statusFilter = document.getElementById('requestsStatusFilter');

    const typeFilter = document.getElementById('requestsTypeFilter');

    const datePresetFilter = document.getElementById('requestsDatePreset');

    const customDateRangeWrap = document.getElementById('requestsCustomDateRange');

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



    const activeDateRange = () => resolveDateRange({

        preset: datePresetFilter?.value || '',

        from: dateFromFilter?.value || '',

        to: dateToFilter?.value || '',

    });



    const updateCustomDateVisibility = () => {

        if (!customDateRangeWrap) {

            return;

        }

        customDateRangeWrap.classList.toggle(

            'd-none',

            datePresetFilter?.value !== DATE_RANGE_PRESET_CUSTOM,

        );

    };



    const applyDatePresetState = (state = {}) => {

        if (!datePresetFilter) {

            return;

        }

        const preset = state.date_preset

            || detectDateRangePreset(state.date_from || '', state.date_to || '');

        datePresetFilter.value = preset;

        if (preset === DATE_RANGE_PRESET_CUSTOM) {

            if (dateFromFilter) dateFromFilter.value = state.date_from || '';

            if (dateToFilter) dateToFilter.value = state.date_to || '';

        } else if (preset) {

            const range = dateRangeForPreset(preset);

            if (dateFromFilter) dateFromFilter.value = range.from;

            if (dateToFilter) dateToFilter.value = range.to;

        } else {

            if (dateFromFilter) dateFromFilter.value = '';

            if (dateToFilter) dateToFilter.value = '';

        }

        updateCustomDateVisibility();

    };



    const parseListStateFromUrl = () => {

        const params = new URLSearchParams(window.location.search);



        return {

            tab: params.get('tab') || '',

            status: params.get('status') || '',

            type: params.get('type') || '',

            date_preset: params.get('date_preset') || '',

            date_from: params.get('date_from') || '',

            date_to: params.get('date_to') || '',

            employee_id: params.get('employee_id') || '',

            employee_label: params.get('employee_label') || '',

        };

    };



    const buildListState = () => {

        const range = activeDateRange();



        return {

            tab: activeTab,

            status: statusFilter?.value || '',

            type: typeFilter?.value || '',

            date_preset: range.preset,

            date_from: range.preset === DATE_RANGE_PRESET_CUSTOM ? range.from : '',

            date_to: range.preset === DATE_RANGE_PRESET_CUSTOM ? range.to : '',

            employee_id: selectedEmployeeId() ? String(selectedEmployeeId()) : '',

            employee_label: document.getElementById('requestsEmployeeInput')?.value || '',

        };

    };



    const syncListStateToUrl = () => {

        const state = buildListState();

        saveRequestsListState(state);



        const params = new URLSearchParams();

        Object.entries(state).forEach(([key, value]) => {

            if (value) {

                params.set(key, value);

            }

        });



        const query = params.toString();

        const url = `${window.location.pathname}${query ? `?${query}` : ''}`;

        window.history.replaceState({}, '', url);

    };



    const resolveTab = (tab) => {

        if (tab === 'approval' && hasApprovalTab) {

            return 'approval';

        }



        if (tab === 'team' && hasTeamTab) {

            return 'team';

        }



        if (tab === 'mine') {

            return 'mine';

        }



        return hasApprovalTab ? 'approval' : 'mine';

    };



    const applyListState = (state) => {

        activeTab = resolveTab(state.tab);



        tabButtons.forEach((button) => {

            const isActive = button.dataset.requestsTab === activeTab;

            button.classList.toggle('active', isActive);

            button.setAttribute('aria-selected', isActive ? 'true' : 'false');

        });



        if (statusFilter) {

            statusFilter.disabled = activeTab === 'approval';



            if (activeTab === 'approval') {

                statusFilter.value = '';

            } else if (state.status) {

                statusFilter.value = state.status;

            } else if (activeTab === 'team') {

                statusFilter.value = 'approved';

            }

        }



        applyDatePresetState(state);



        if (typeFilter && state.type) {

            typeFilter.value = state.type;

        }



        updateEmployeeFilterVisibility();

    };



    const expenseViewModal = bindExpenseRequestViewModal({

        onError: (message) => showAlert(message, 'danger'),

    });



    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        showAutoDismissAlert(alertBox, message, type);
    };



    const selectedEmployeeId = () => employeeSearch?.getSelectedId?.()

        || document.getElementById('requestsEmployeeId')?.value

        || null;



    const summarySource = () => {

        const pools = [mineRequests];

        if (hasApprovalTab) {

            pools.push(approvalRequests);

        }

        if (hasTeamTab) {

            pools.push(teamRequests);

        }



        return dedupeRequests(pools.flat());

    };



    const summaryFilteredRequests = () => applyDateFilter(

        applyCategoryFilter(applyEmployeeFilter(summarySource())),

    );



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



    const applyCategoryFilter = (items) => {

        const type = typeFilter?.value || '';



        if (!type) {

            return items;

        }



        return items.filter((item) => item.category === type);

    };



    const applyStatusFilter = (items) => {

        if (activeTab === 'approval' || !statusFilter?.value) {

            return items;

        }



        return items.filter((item) => item.status === statusFilter.value);

    };



    const applyDateFilter = (items) => {

        const { from: dateFrom, to: dateTo } = activeDateRange();



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



    const filteredRequests = () => applyDateFilter(applyStatusFilter(applyCategoryFilter(applyEmployeeFilter(currentSource()))));



    const dateFilterParams = () => {

        const { from, to } = activeDateRange();

        const params = {};



        if (from) {

            params.date_from = from;

        }



        if (to) {

            params.date_to = to;

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

        const counts = countByStatus(summaryFilteredRequests());



        if (countTotal) countTotal.textContent = String(counts.total);

        if (countPending) countPending.textContent = String(counts.pending);

        if (countApproved) countApproved.textContent = String(counts.approved);

        if (countRejected) countRejected.textContent = String(counts.rejected);

        if (countCancelled) countCancelled.textContent = String(counts.cancelled);

        if (countMeta) countMeta.textContent = 'All requests';



        statusCardButtons.forEach((button) => {

            const status = button.dataset.requestsStatus || '';

            const isActive = (statusFilter?.value || '') === status;

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

                statusFilter.value = '';

            }

        }



        updateEmployeeFilterVisibility();

        renderSummaryCards();

        renderTable();

        syncListStateToUrl();

    };



    const renderReviewedLine = (item) => {

        if (!item.reviewed_at_label && !item.reviewed_by_name) {

            return '';

        }



        const by = item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : '';



        return `<div class="small text-muted mt-1">${item.reviewed_at_label || 'Reviewed'}${by}</div>`;

    };



    const renderActions = (item) => {
        const actionOptions = { includeReview: activeTab === 'approval' };

        if (item.category === 'expense' || item.category === 'expense_group') {
            const viewButton = item.category === 'expense'
                ? renderExpenseViewButton('expense', item.entity_id)
                : renderExpenseViewButton('expense_group', item.entity_id, 'View expense group');

            return renderRequestActions({ ...item, view_url: null }, { viewOverride: viewButton, ...actionOptions });
        }

        return renderRequestActions(item, actionOptions);
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

        if (selectedEmployeeId() || typeFilter?.value) {

            return 'No requests found for the selected filters.';

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

            const { data } = await api.get('/request-hub/pending', { params: { per_page: 50 } });

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

            statusFilter.value = '';

        }



        if (dateFromFilter) dateFromFilter.value = '';

        if (dateToFilter) dateToFilter.value = '';

        if (datePresetFilter) datePresetFilter.value = '';

        updateCustomDateVisibility();

        if (typeFilter) typeFilter.value = '';



        employeeSearch?.clearSelection?.();



        if (activeTab === 'team') {

            await loadTeamRequests();

        } else if (activeTab === 'mine') {

            await loadMineRequests();

        }



        renderTable();

        syncListStateToUrl();

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

            onSelect: () => {

                renderTable();

                syncListStateToUrl();

            },

            onClear: () => {

                renderTable();

                syncListStateToUrl();

            },

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



    statusFilter?.addEventListener('change', () => {

        renderTable();

        syncListStateToUrl();

    });



    typeFilter?.addEventListener('change', () => {

        renderTable();

        syncListStateToUrl();

    });



    const handleDateFilterChange = async () => {

        if (datePresetFilter) {

            const from = dateFromFilter?.value || '';

            const to = dateToFilter?.value || '';

            datePresetFilter.value = from || to

                ? detectDateRangePreset(from, to)

                : '';

            updateCustomDateVisibility();

        }



        updateCustomDateVisibility();



        if (activeTab === 'mine') {

            await loadMineRequests();

        } else if (activeTab === 'team') {

            await loadTeamRequests();

        }



        renderTable();

        syncListStateToUrl();

    };



    dateFromFilter?.addEventListener('change', handleDateFilterChange);

    dateToFilter?.addEventListener('change', handleDateFilterChange);



    datePresetFilter?.addEventListener('change', async () => {

        const preset = datePresetFilter.value;



        if (preset === DATE_RANGE_PRESET_CUSTOM) {

            updateCustomDateVisibility();

            syncListStateToUrl();

            return;

        }



        if (preset) {

            const range = dateRangeForPreset(preset);

            if (dateFromFilter) dateFromFilter.value = range.from;

            if (dateToFilter) dateToFilter.value = range.to;

        } else {

            if (dateFromFilter) dateFromFilter.value = '';

            if (dateToFilter) dateToFilter.value = '';

        }



        updateCustomDateVisibility();

        await handleDateFilterChange();

    });



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

                } else if (statusFilter) {

                    statusFilter.value = 'pending';

                }



                renderTable();

                syncListStateToUrl();

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

        const viewLink = event.target.closest('a.table-action-btn--view[href]');

        if (viewLink?.href) {

            syncListStateToUrl();

        }

    }, true);



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



    const initialListState = parseListStateFromUrl();

    applyListState(initialListState);



    if (employeeSearch && initialListState.employee_id) {

        employeeSearch.setSelection({

            id: Number(initialListState.employee_id),

            label: initialListState.employee_label || '',

        });

    }



    updateEmployeeFilterVisibility();

    syncListStateToUrl();

    await reload();

});

