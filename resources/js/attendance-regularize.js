import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { renderCancelIconButton, renderViewLink, composeActionGroup } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { setSubmitLoading, showAutoDismissAlert } from './form-utils';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { formatOriginalPunchLine, formatRequestedPunchLine, renderEmployeeNameBlock } from './request-display';
import { reviewSingleRequest } from './request-review';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const pad = (value) => String(value).padStart(2, '0');

const currentMonthValue = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

const eligibleStatusClass = (status) => ({
    absent: 'regularize-eligible-card--absent',
    half_day: 'regularize-eligible-card--half-day',
    short_leave: 'regularize-eligible-card--short-leave',
    incomplete: 'regularize-eligible-card--incomplete',
    approved_update: 'regularize-eligible-card--approved-update',
}[status] || 'regularize-eligible-card--default');

const formatTimezoneLabel = () => {
    const parts = new Intl.DateTimeFormat('en-IN', {
        timeZoneName: 'shortOffset',
    }).formatToParts(new Date());
    const offset = parts.find((part) => part.type === 'timeZoneName')?.value || '';
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local';

    return `Timezone (${offset}) ${zone.replace('_', ' ')}`;
};

document.addEventListener('DOMContentLoaded', async () => {
    const pageConfig = window.regularizePageConfig || {};
    const isHrView = Boolean(pageConfig.isHrView);

    const tableBody = document.getElementById('regularizeTableBody');
    const myRequestsTableBody = document.getElementById('myRequestsTableBody');
    const alertBox = document.getElementById('regularizeAlert');
    const pendingContainer = document.getElementById('pendingRegularizeContainer');
    const pendingBadge = document.getElementById('regularizePendingBadge');
    const myPendingCard = document.getElementById('myPendingRegularizeCard');
    const myPendingContainer = document.getElementById('myPendingRegularizeContainer');
    const eligibleDatesContainer = document.getElementById('eligibleDatesContainer');
    const filterMonth = document.getElementById('filterMonth');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const summaryTotal = document.getElementById('regularizeSummaryTotal');
    const summaryPending = document.getElementById('regularizeSummaryPending');
    const summaryApproved = document.getElementById('regularizeSummaryApproved');
    const summaryRejected = document.getElementById('regularizeSummaryRejected');
    const summaryMonthLabel = document.getElementById('regularizeSummaryMonthLabel');
    const paginationInfo = document.getElementById('regularizePaginationInfo');
    const paginationList = document.getElementById('regularizePaginationList');
    const myRequestsPaginationInfo = document.getElementById('myRequestsPaginationInfo');
    const myRequestsPaginationList = document.getElementById('myRequestsPaginationList');
    const myRequestsPerPageSelect = document.getElementById('myRequestsPerPage');
    const regularizePerPageSelect = document.getElementById('regularizePerPage');
    const tabButtons = Array.from(document.querySelectorAll('[data-regularize-tab]'));
    const tabPanels = {
        'my-requests': document.getElementById('regularizeTabMyRequests'),
        'pending-approvals': document.getElementById('regularizeTabPendingApprovals'),
        history: document.getElementById('regularizeTabHistory'),
    };
    const regularizeForm = document.getElementById('regularizeForm');
    const regularizeModalEl = document.getElementById('regularizeModal');
    const regularizeModalTimezone = document.getElementById('regularizeModalTimezone');
    const regularizeOriginalTimes = document.getElementById('regularizeOriginalTimes');
    const regularizeSelectedDatesList = document.getElementById('regularizeSelectedDatesList');
    const regularizeSubmitBtn = document.getElementById('regularizeSubmitBtn');
    const openRegularizeRequestBtn = document.getElementById('openRegularizeRequestBtn');
    const selectAllEligibleBtn = document.getElementById('selectAllEligibleBtn');
    const clearEligibleSelectionBtn = document.getElementById('clearEligibleSelectionBtn');
    const addRegularizeDateBtn = document.getElementById('addRegularizeDateBtn');
    const addRegularizeRangeBtn = document.getElementById('addRegularizeRangeBtn');
    const pickRegularizeDateModalEl = document.getElementById('pickRegularizeDateModal');
    const pickRegularizeDateSelect = document.getElementById('pickRegularizeDateSelect');
    const confirmPickRegularizeDateBtn = document.getElementById('confirmPickRegularizeDateBtn');
    const pickRegularizeRangeModalEl = document.getElementById('pickRegularizeRangeModal');
    const regularizeRangeFrom = document.getElementById('regularizeRangeFrom');
    const regularizeRangeTo = document.getElementById('regularizeRangeTo');
    const confirmRegularizeRangeBtn = document.getElementById('confirmRegularizeRangeBtn');

    const regularizeModal = regularizeModalEl ? Modal.getOrCreateInstance(regularizeModalEl) : null;
    const pickRegularizeDateModal = pickRegularizeDateModalEl
        ? Modal.getOrCreateInstance(pickRegularizeDateModalEl)
        : null;
    const pickRegularizeRangeModal = pickRegularizeRangeModalEl
        ? Modal.getOrCreateInstance(pickRegularizeRangeModalEl)
        : null;

    let currentPage = 1;
    let myRequestsPage = 1;
    let myRequestsPerPage = readPerPage(myRequestsPerPageSelect);
    let regularizePerPage = readPerPage(regularizePerPageSelect);
    let activeTab = pageConfig.defaultTab || 'my-requests';
    let eligibleDatesByKey = {};
    let employeeSearch = null;
    let selectedDates = [];
    let selectedEligibleDates = new Set();
    let pendingGroupsCache = [];
    const urlDate = new URLSearchParams(window.location.search).get('date');

    if (filterMonth) {
        filterMonth.value = currentMonthValue();
        filterMonth.max = currentMonthValue();
    }

    const selectedEmployeeId = () => employeeSearch?.getSelectedId?.()
        || document.getElementById('regularizeEmployeeId')?.value
        || null;

    const filterParams = () => {
        const params = {};

        if (filterMonth?.value) {
            params.month = filterMonth.value;
        }

        const employeeId = selectedEmployeeId();
        if (employeeId) {
            params.employee_id = Number(employeeId);
        }

        return params;
    };

    if (regularizeModalTimezone) {
        regularizeModalTimezone.textContent = formatTimezoneLabel();
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        showAutoDismissAlert(alertBox, message, type);
    };

const formatTimes = (item) => formatRequestedPunchLine(item);

const formatOriginalTimes = (item) => formatOriginalPunchLine(item);

const formatOriginalTimesFromEligible = (item) => {
    if (item.is_update_request) {
        const parts = [];
        if (item.punch_in_label && item.punch_in_label !== '—') parts.push(`In ${item.punch_in_label}`);
        if (item.has_punch_out && item.punch_out_label && item.punch_out_label !== 'Not recorded') {
            parts.push(`Out ${item.punch_out_label}`);
        } else if (item.punch_in_label && item.punch_in_label !== '—') {
            parts.push('Out not recorded');
        }
        return `Approved: ${parts.join(' · ') || '—'}`;
    }

    const parts = [];
    if (item.punch_in_label && item.punch_in_label !== '—') parts.push(`In ${item.punch_in_label}`);
    if (item.has_punch_out && item.punch_out_label && item.punch_out_label !== '—') {
        parts.push(`Out ${item.punch_out_label}`);
    } else if (item.punch_in_label && item.punch_in_label !== '—') {
        parts.push('Out not recorded');
    }
    return parts.join(' · ') || '—';
};

const formatEligiblePunchMeta = (item) => {
    const prefix = item.is_update_request ? 'Approved login / logout' : 'Current';
    const inLabel = item.punch_in_label || '—';
    const outLabel = item.has_punch_out && item.punch_out_label
        ? item.punch_out_label
        : 'Not recorded';

    return `${prefix}: In ${inLabel} · Out ${outLabel}`;
};

    const selectableEligibleDates = () => Object.values(eligibleDatesByKey).map((item) => item.date);

    const updateEligibleSelectionUi = () => {
        const count = selectedEligibleDates.size;

        if (openRegularizeRequestBtn) {
            openRegularizeRequestBtn.disabled = count === 0;
            openRegularizeRequestBtn.textContent = count
                ? `Regularize selected (${count})`
                : 'Regularize selected (0)';
        }

        clearEligibleSelectionBtn?.classList.toggle('d-none', count === 0);

        eligibleDatesContainer?.querySelectorAll('[data-eligible-date]').forEach((card) => {
            const date = card.dataset.eligibleDate;
            const isSelected = selectedEligibleDates.has(date);
            card.classList.toggle('regularize-eligible-card--selected', isSelected);
            card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

            const checkbox = card.querySelector('.regularize-eligible-card-check');
            if (checkbox) {
                checkbox.checked = isSelected;
            }
        });
    };

    const toggleEligibleDate = (date) => {
        if (!eligibleDatesByKey[date]) {
            return;
        }

        if (selectedEligibleDates.has(date)) {
            selectedEligibleDates.delete(date);
        } else {
            selectedEligibleDates.add(date);
        }

        updateEligibleSelectionUi();
    };

    const setEligibleSelection = (dates) => {
        selectedEligibleDates = new Set(
            dates.filter((date) => eligibleDatesByKey[date]),
        );
        updateEligibleSelectionUi();
    };

    const clearEligibleSelection = () => {
        selectedEligibleDates.clear();
        updateEligibleSelectionUi();
    };

    const availableEligibleDates = () => Object.values(eligibleDatesByKey)
        .filter((item) => !selectedDates.includes(item.date));

    const applySuggestedTimes = () => {
        const firstSelected = selectedDates
            .map((date) => eligibleDatesByKey[date])
            .find(Boolean);

        if (!firstSelected) {
            return;
        }

        const punchInField = document.getElementById('punch_in_time');
        const punchOutField = document.getElementById('punch_out_time');

        if (punchInField && !punchInField.value) {
            punchInField.value = firstSelected.suggested_punch_in || '';
        }

        if (punchOutField && !punchOutField.value && firstSelected.suggested_punch_out) {
            punchOutField.value = firstSelected.suggested_punch_out;
        }
    };

    const updateOriginalTimesUi = () => {
        if (!regularizeOriginalTimes) {
            return;
        }

        const labelEl = document.querySelector('#regularizeOriginalTimesWrap .small.text-muted');
        const selectedItems = selectedDates
            .map((date) => eligibleDatesByKey[date])
            .filter(Boolean);
        const hasUpdate = selectedItems.some((item) => item.is_update_request);

        if (labelEl) {
            labelEl.textContent = hasUpdate
                ? 'Previously approved login / logout'
                : 'Current login / logout on record';
        }

        if (!selectedItems.length) {
            regularizeOriginalTimes.textContent = '—';
            return;
        }

        if (selectedItems.length === 1) {
            regularizeOriginalTimes.textContent = formatOriginalTimesFromEligible(selectedItems[0]);
            return;
        }

        regularizeOriginalTimes.innerHTML = selectedItems.map((item) => {
            const label = item.date_short_label || item.date_label || item.date;
            return `<div>${label}: ${formatOriginalTimesFromEligible(item)}</div>`;
        }).join('');
    };

    const updateSelectedDatesUi = () => {
        if (!regularizeSelectedDatesList) return;

        if (!selectedDates.length) {
            regularizeSelectedDatesList.innerHTML = `
                <li class="regularize-dates-empty text-muted small py-3 px-3">Add at least one date to continue.</li>
            `;
        } else {
            regularizeSelectedDatesList.innerHTML = selectedDates.map((date) => {
                const item = eligibleDatesByKey[date];
                const label = item?.date_short_label || item?.date_label || date;
                const meta = item
                    ? (item.is_update_request
                        ? `Approved: ${formatOriginalTimesFromEligible(item).replace(/^Approved: /, '')}`
                        : `Current: ${formatOriginalTimesFromEligible(item)}`)
                    : '';

                return `
                    <li class="regularize-dates-list-item">
                        <div>
                            <div class="regularize-dates-list-item-label">${label}</div>
                            ${meta ? `<div class="regularize-dates-list-item-meta">${meta}</div>` : ''}
                        </div>
                        <button
                            type="button"
                            class="regularize-dates-remove-btn"
                            data-remove-regularize-date="${date}"
                            aria-label="Remove ${label}"
                            title="Remove date"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                            </svg>
                        </button>
                    </li>
                `;
            }).join('');
        }

        if (regularizeSubmitBtn) {
            const count = selectedDates.length;
            regularizeSubmitBtn.disabled = count === 0;
            regularizeSubmitBtn.textContent = `Submit for ${count} day(s)`;
        }

        updateOriginalTimesUi();
    };

    const addSelectedDate = (date) => {
        if (!date || !eligibleDatesByKey[date]) {
            return false;
        }

        if (selectedDates.includes(date)) {
            return false;
        }

        selectedDates.push(date);
        selectedDates.sort();
        updateSelectedDatesUi();
        applySuggestedTimes();
        return true;
    };

    const addSelectedDates = (dates) => {
        const added = dates.filter((date) => addSelectedDate(date));
        return added.length;
    };

    const removeSelectedDate = (date) => {
        selectedDates = selectedDates.filter((item) => item !== date);
        updateSelectedDatesUi();
    };

    const resetRegularizeForm = () => {
        selectedDates = [];
        regularizeForm?.reset();
        updateSelectedDatesUi();
    };

    const openRegularizeModal = (initialDates = [], employeeId = null) => {
        resetRegularizeForm();

        const employeeField = document.getElementById('regularize_employee_id');
        if (employeeField) {
            employeeField.value = employeeId || '';
        }

        addSelectedDates(initialDates);
        regularizeModal?.show();
    };

    const openPickDateModal = () => {
        const available = availableEligibleDates();

        if (!available.length) {
            showAlert('No more eligible dates available to add.', 'warning');
            return;
        }

        if (pickRegularizeDateSelect) {
            pickRegularizeDateSelect.innerHTML = available
                .map((item) => `<option value="${item.date}">${item.date_label}</option>`)
                .join('');
        }

        pickRegularizeDateModal?.show();
    };

    const openPickRangeModal = () => {
        const available = availableEligibleDates();

        if (!available.length) {
            showAlert('No more eligible dates available to add.', 'warning');
            return;
        }

        const sortedDates = available.map((item) => item.date).sort();
        if (regularizeRangeFrom) regularizeRangeFrom.value = sortedDates[0] || '';
        if (regularizeRangeTo) regularizeRangeTo.value = sortedDates[sortedDates.length - 1] || '';

        pickRegularizeRangeModal?.show();
    };

    const renderMyPendingRequests = (requests) => {
        if (!myPendingContainer) {
            return;
        }

        if (!requests.length) {
            myPendingCard?.classList.add('d-none');
            myPendingContainer.innerHTML = '<div class="text-muted">No pending requests.</div>';
            return;
        }

        myPendingCard?.classList.remove('d-none');
        myPendingContainer.innerHTML = requests.map((item) => {
            const viewLink = renderViewLink(`/requests/regularization/${item.id}`, 'View request');
            const cancelAction = item.can_cancel
                ? renderCancelIconButton('data-cancel-regularize', item.id, `Cancel ${item.date_label}`)
                : '';

            return `
                <div class="regularize-pending-group border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${item.date_label}</div>
                            <div class="small text-muted">${item.status_label}</div>
                            <div class="small text-muted mt-1">Current: ${formatOriginalTimes(item)}</div>
                            <div class="small text-muted">Requested: ${formatTimes(item)}</div>
                            ${item.submitted_at_label ? `<div class="small text-muted">Submitted ${item.submitted_at_label}</div>` : ''}
                            <div class="small mt-2">${item.reason || ''}</div>
                        </div>
                        ${composeActionGroup({ view: viewLink, cancel: cancelAction })}
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderEligibleDates = (payload, showSubmitGrid = true) => {
        if (!eligibleDatesContainer) {
            renderMyPendingRequests(payload.pending_requests || []);
            (payload.dates || []).forEach((item) => {
                eligibleDatesByKey[item.date] = item;
            });
            return;
        }

        renderMyPendingRequests(payload.pending_requests || []);

        const dates = payload.dates || [];
        dates.forEach((item) => {
            eligibleDatesByKey[item.date] = item;
        });

        if (!showSubmitGrid) {
            return;
        }
        selectedEligibleDates = new Set(
            [...selectedEligibleDates].filter((date) => eligibleDatesByKey[date]),
        );

        if (!dates.length) {
            const monthHint = filterMonth?.value
                ? ` for ${new Date(`${filterMonth.value}-01`).toLocaleString('en-IN', { month: 'long', year: 'numeric' })}`
                : '';
            eligibleDatesContainer.innerHTML = `<div class="text-muted py-3">No eligible days${monthHint}. Try another month or check attendance for absent, incomplete, or half-day records.</div>`;
            updateEligibleSelectionUi();
            return;
        }

        eligibleDatesContainer.innerHTML = dates.map((item) => {
            const isSelected = selectedEligibleDates.has(item.date);

            return `
                <div class="regularize-eligible-card ${eligibleStatusClass(item.status)} ${isSelected ? 'regularize-eligible-card--selected' : ''}" data-eligible-date="${item.date}" role="button" tabindex="0" aria-pressed="${isSelected ? 'true' : 'false'}">
                    <div class="regularize-eligible-card-top">
                        <div>
                            <div class="regularize-eligible-card-date">${item.date_short_label || item.date_label}</div>
                            <div class="regularize-eligible-card-status">${item.status_label}</div>
                        </div>
                        <input type="checkbox" class="form-check-input regularize-eligible-card-check" ${isSelected ? 'checked' : ''} tabindex="-1" aria-hidden="true" readonly>
                    </div>
                    <div class="regularize-eligible-card-meta">
                        ${formatEligiblePunchMeta(item)}
                    </div>
                    ${item.is_update_request
                        ? '<div class="regularize-eligible-card-meta">Select to request a time change</div>'
                        : `<div class="regularize-eligible-card-meta">Worked ${item.worked_hours_label} / ${item.required_hours_label}</div>`}
                </div>
            `;
        }).join('');

        updateEligibleSelectionUi();
    };

    const loadEligibleDates = async (date = null) => {
        if (!eligibleDatesContainer) return;

        eligibleDatesContainer.innerHTML = '<div class="text-muted py-3">Loading eligible days...</div>';

        try {
            const params = {};
            if (date) {
                params.date = date;
            } else if (filterMonth?.value) {
                params.month = filterMonth.value;
            }

            const { data } = await api.get('/attendance-regularizations/eligible-dates', { params });
            renderEligibleDates(data.data || {}, !date);
        } catch (error) {
            eligibleDatesContainer.innerHTML = `<div class="text-danger py-3">${getErrorMessage(error)}</div>`;
        }
    };

    const loadMyPending = async () => {
        if (!myPendingContainer) {
            return;
        }

        try {
            const { data } = await api.get('/attendance-regularizations/eligible-dates');
            renderMyPendingRequests(data.data?.pending_requests || []);
        } catch {
            myPendingContainer.innerHTML = '<div class="text-danger py-3">Unable to load your pending requests.</div>';
        }
    };

    const renderPending = (groups) => {
        if (!pendingContainer) return;

        const employeeId = selectedEmployeeId();
        const filtered = employeeId
            ? groups.filter((group) => Number(group.employee?.id) === Number(employeeId))
            : groups;

        if (!filtered.length) {
            pendingContainer.innerHTML = '<div class="text-muted">No pending regularization requests.</div>';
            return;
        }

        pendingContainer.innerHTML = filtered.map((group) => {
            const employeeName = group.employee?.full_name || 'Employee';
            const employeeCode = group.employee?.employee_code || '';
            const dayCount = group.day_count || group.dates?.length || 0;
            const isBatch = dayCount > 1 && group.batch_id;
            const title = isBatch
                ? `${employeeName} · ${dayCount} day(s)`
                : `${employeeName} · ${group.dates?.[0]?.attendance_date_label || '—'}`;
            const times = formatTimes(group);
            const originalTimes = formatOriginalTimes(group);
            const dateList = (group.dates || [])
                .map((date) => {
                    const label = date.attendance_date_short_label || date.attendance_date_label;
                    if ((group.day_count || 1) <= 1) {
                        return `<span class="regularize-pending-date-chip">${label}</span>`;
                    }

                    const original = formatOriginalTimes(date);
                    const requested = formatTimes(date);

                    return `
                        <div class="regularize-pending-date-chip">
                            <div>${label}</div>
                            <div class="small text-muted">Login / Logout: ${original} → Requested: ${requested}</div>
                        </div>
                    `;
                })
                .join('');
            const viewHref = isBatch && group.batch_id
                ? `/requests/regularization-batch/${group.batch_id}`
                : (group.request_ids?.[0] ? `/requests/regularization/${group.request_ids[0]}` : '');
            const viewLink = viewHref ? renderViewLink(viewHref, 'View request') : '';
            const approveAction = group.can_review
                ? (isBatch && group.batch_id
                    ? renderApproveIconButton('data-approve-regularize-batch', group.batch_id, `Approve ${dayCount} day(s)`)
                    : renderApproveIconButton('data-approve-regularize', group.request_ids?.[0], 'Approve regularization'))
                : '';
            const rejectAction = group.can_review
                ? (isBatch && group.batch_id
                    ? renderRejectIconButton('data-reject-regularize-batch', group.batch_id, `Reject ${dayCount} day(s)`)
                    : renderRejectIconButton('data-reject-regularize', group.request_ids?.[0], 'Reject regularization'))
                : '';

            return `
                <div class="regularize-pending-group border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${title}</div>
                            ${employeeCode ? `<div class="small text-muted">${employeeCode}</div>` : ''}
                            <div class="small text-muted mt-1">Login / Logout: ${originalTimes}</div>
                            <div class="small text-muted">Requested: ${times}</div>
                            ${group.created_at_label ? `<div class="small text-muted">Submitted ${group.created_at_label}</div>` : ''}
                            <div class="regularize-pending-dates mt-2">${dateList}</div>
                            <div class="small mt-2">${group.reason || ''}</div>
                        </div>
                        ${composeActionGroup({ view: viewLink, approve: approveAction, reject: rejectAction })}
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderRow = (item, serial) => {
        const viewLink = renderViewLink(`/requests/regularization/${item.id}`, 'View request');
        const cancelAction = item.can_cancel && item.status === 'pending'
            ? renderCancelIconButton('data-cancel-regularize', item.id, 'Cancel request')
            : '';
        const updateAction = item.can_request_update
            ? `<button type="button" class="btn btn-sm btn-outline-primary" data-update-regularize="${item.attendance_date}">Update time</button>`
            : '';

        return `<tr>
            <td>${serial}</td>
            <td>${renderEmployeeNameBlock(item.employee?.full_name, item.employee?.employee_code)}</td>
            <td>
                ${item.attendance_date_label || '—'}
                ${item.batch_id ? '<div class="small text-muted mt-1">Part of a multi-day request</div>' : ''}
                ${item.is_update_request ? '<div class="small text-muted mt-1">Update request</div>' : ''}
            </td>
            <td>
                ${item.is_update_request ? '<div class="small text-muted">Previously approved</div>' : ''}
                ${formatOriginalTimes(item)}
            </td>
            <td>${formatTimes(item)}</td>
            <td class="text-truncate" style="max-width:220px">${item.reason}</td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            <td>${composeActionGroup({ view: viewLink, cancel: cancelAction })}${updateAction}</td>
        </tr>`;
    };

    const renderHistoryRows = (requests, pagination) => {
        const offset = pagination ? ((pagination.current_page - 1) * pagination.per_page) : 0;

        return requests.map((item, index) => renderRow(item, offset + index + 1)).join('');
    };

    const loadSummary = async () => {
        if (!summaryTotal) {
            return;
        }

        try {
            const { data } = await api.get('/attendance-regularizations/summary', { params: filterParams() });
            const summary = data.data || {};

            summaryTotal.textContent = String(summary.total ?? 0);
            summaryPending.textContent = String(summary.pending ?? 0);
            summaryApproved.textContent = String(summary.approved ?? 0);
            summaryRejected.textContent = String(summary.rejected ?? 0);

            if (summaryMonthLabel) {
                summaryMonthLabel.textContent = summary.month_label || filterMonth?.value || '—';
            }
        } catch {
            summaryTotal.textContent = '—';
            summaryPending.textContent = '—';
            summaryApproved.textContent = '—';
            summaryRejected.textContent = '—';
        }
    };

    const updatePendingBadge = (groups) => {
        if (!pendingBadge) {
            return;
        }

        const count = groups?.length || 0;
        pendingBadge.textContent = String(count);
        pendingBadge.classList.toggle('d-none', count === 0);
    };

    const loadPending = async () => {
        if (!pendingContainer && !isHrView) {
            return;
        }

        try {
            const { data } = await api.get('/attendance-regularizations/pending');
            pendingGroupsCache = data.data.pending_groups || [];
            updatePendingBadge(pendingGroupsCache);

            if (pendingContainer) {
                renderPending(pendingGroupsCache);
            }
        } catch {
            if (pendingContainer) {
                pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending requests.</div>';
            }
            updatePendingBadge([]);
        }
    };

    const renderPagination = (pagination, infoEl, listEl, perPageSelectEl, itemLabel = 'requests') => {
        renderListPagination({
            infoEl,
            listEl,
            perPageSelectEl,
            pagination,
            itemLabel,
            emptyMessage: 'No requests found',
        });
    };

    const loadMyRequests = async (page = 1) => {
        if (!myRequestsTableBody) {
            return;
        }

        myRequestsPage = page;
        const params = {
            page,
            per_page: myRequestsPerPage,
        };

        if (filterMonth?.value) {
            params.month = filterMonth.value;
        }

        try {
            const { data } = await api.get('/attendance-regularizations', { params });
            const requests = data.data.regularization_requests || [];
            const pagination = data.data.pagination;

            myRequestsTableBody.innerHTML = requests.length
                ? renderHistoryRows(requests, pagination)
                : '<tr><td colspan="8" class="text-center text-muted py-5">No requests found.</td></tr>';

            renderPagination(pagination, myRequestsPaginationInfo, myRequestsPaginationList, myRequestsPerPageSelect);
        } catch (error) {
            myRequestsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const setActiveTab = (tab) => {
        if (!isHrView) {
            return;
        }

        activeTab = tab;
        tabButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.regularizeTab === tab);
        });

        Object.entries(tabPanels).forEach(([key, panel]) => {
            panel?.classList.toggle('d-none', key !== tab);
        });

        loadActiveTabData();
    };

    const loadActiveTabData = async (page = 1) => {
        if (!isHrView) {
            return;
        }

        if (activeTab === 'my-requests') {
            await loadMyRequests(page);
            if (eligibleDatesContainer) {
                await loadEligibleDates(urlDate || null);
            }
            return;
        }

        if (activeTab === 'pending-approvals') {
            await loadPending();
            return;
        }

        await loadRequests(page);
    };

    const loadRequests = async (page = 1) => {
        if (!tableBody) {
            return;
        }

        currentPage = page;
        const params = { page, per_page: regularizePerPage, ...filterParams() };

        if (isHrView) {
            params.scope = 'history';
        }

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        try {
            const { data } = await api.get('/attendance-regularizations', { params });
            const requests = data.data.regularization_requests || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = requests.length
                ? renderHistoryRows(requests, pagination)
                : '<tr><td colspan="8" class="text-center text-muted py-5">No regularization requests found.</td></tr>';
            renderPagination(pagination, paginationInfo, paginationList, regularizePerPageSelect);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const reloadDashboard = async (page = 1) => {
        await loadSummary();

        if (isHrView) {
            await loadPending();
            await loadActiveTabData(page);
            return;
        }

        await Promise.all([
            loadRequests(page),
            loadPending(),
            loadMyPending(),
            eligibleDatesContainer ? loadEligibleDates(urlDate || null) : Promise.resolve(),
        ]);
    };

    const initEmployeeFilter = () => {
        if (!document.getElementById('regularizeEmployeeInput')) {
            return;
        }

        employeeSearch = bindEmployeeSearchSelect({
            inputId: 'regularizeEmployeeInput',
            hiddenId: 'regularizeEmployeeId',
            onSelect: () => reloadDashboard(1),
            onClear: () => reloadDashboard(1),
        });
    };

    const handleReview = async (id, action) => {
        try {
            const message = await reviewSingleRequest(`regularization:${id}`, action);
            if (!message) {
                return;
            }

            showAlert(message);
            await reloadDashboard(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleBatchReview = async (batchId, action) => {
        try {
            const message = await reviewSingleRequest(`regularization_batch:${batchId}`, action);
            if (!message) {
                return;
            }

            showAlert(message);
            await reloadDashboard(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleCancel = async (id) => {
        if (!window.confirm('Cancel this regularization request?')) return;
        try {
            await api.patch(`/attendance-regularizations/${id}/cancel`);
            showAlert('Regularization request cancelled.');
            await reloadDashboard(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleUpdateRequest = async (date) => {
        if (!date) return;

        try {
            const { data } = await api.get('/attendance-regularizations/eligible-dates', { params: { date } });
            renderEligibleDates(data.data || {}, false);

            if (!eligibleDatesByKey[date]) {
                showAlert('This date is not available for update right now.', 'warning');
                return;
            }

            if (eligibleDatesContainer) {
                clearEligibleSelection();
                selectedEligibleDates.add(date);
                updateEligibleSelectionUi();
            }

            openRegularizeModal([date]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    openRegularizeRequestBtn?.addEventListener('click', () => {
        if (!selectedEligibleDates.size) {
            showAlert('Select at least one date to regularize.', 'warning');
            return;
        }

        openRegularizeModal([...selectedEligibleDates].sort());
    });

    selectAllEligibleBtn?.addEventListener('click', () => {
        setEligibleSelection(selectableEligibleDates());
    });

    clearEligibleSelectionBtn?.addEventListener('click', clearEligibleSelection);

    addRegularizeDateBtn?.addEventListener('click', openPickDateModal);
    addRegularizeRangeBtn?.addEventListener('click', openPickRangeModal);

    confirmPickRegularizeDateBtn?.addEventListener('click', () => {
        const date = pickRegularizeDateSelect?.value;
        if (!date) return;

        addSelectedDate(date);
        pickRegularizeDateModal?.hide();
    });

    confirmRegularizeRangeBtn?.addEventListener('click', () => {
        const from = regularizeRangeFrom?.value;
        const to = regularizeRangeTo?.value;

        if (!from || !to) {
            showAlert('Select both start and end dates.', 'warning');
            return;
        }

        if (from > to) {
            showAlert('End date must be on or after start date.', 'warning');
            return;
        }

        const eligibleInRange = availableEligibleDates()
            .filter((item) => item.date >= from && item.date <= to)
            .map((item) => item.date);

        if (!eligibleInRange.length) {
            showAlert('No eligible dates found in the selected range.', 'warning');
            return;
        }

        addSelectedDates(eligibleInRange);
        pickRegularizeRangeModal?.hide();
    });

    regularizeSelectedDatesList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-regularize-date]');
        if (!button) return;
        removeSelectedDate(button.dataset.removeRegularizeDate);
    });

    eligibleDatesContainer?.addEventListener('click', (event) => {
        const card = event.target.closest('[data-eligible-date]');
        if (!card) return;

        toggleEligibleDate(card.dataset.eligibleDate);
    });

    myPendingContainer?.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-cancel-regularize]');
        if (cancel) handleCancel(cancel.dataset.cancelRegularize);
    });

    myRequestsTableBody?.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-cancel-regularize]');
        const update = event.target.closest('[data-update-regularize]');
        if (cancel) handleCancel(cancel.dataset.cancelRegularize);
        if (update) handleUpdateRequest(update.dataset.updateRegularize);
    });

    pendingContainer?.addEventListener('click', (event) => {
        const approveBatch = event.target.closest('[data-approve-regularize-batch]');
        const rejectBatch = event.target.closest('[data-reject-regularize-batch]');
        const approve = event.target.closest('[data-approve-regularize]');
        const reject = event.target.closest('[data-reject-regularize]');

        if (approveBatch) handleBatchReview(approveBatch.dataset.approveRegularizeBatch, 'approve');
        if (rejectBatch) handleBatchReview(rejectBatch.dataset.rejectRegularizeBatch, 'reject');
        if (approve) handleReview(approve.dataset.approveRegularize, 'approve');
        if (reject) handleReview(reject.dataset.rejectRegularize, 'reject');
    });

    tableBody?.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-cancel-regularize]');
        const update = event.target.closest('[data-update-regularize]');
        if (cancel) handleCancel(cancel.dataset.cancelRegularize);
        if (update) handleUpdateRequest(update.dataset.updateRegularize);
    });

    regularizeForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedDates.length) {
            showAlert('Add at least one date before submitting.', 'warning');
            return;
        }

        setSubmitLoading(regularizeSubmitBtn, true, { submittingText: 'Submitting...' });
        try {
            const payload = {
                dates: selectedDates,
                punch_in_time: document.getElementById('punch_in_time')?.value || null,
                punch_out_time: document.getElementById('punch_out_time')?.value || null,
                reason: document.getElementById('reason')?.value?.trim(),
            };

            const { data } = await api.post('/attendance-regularizations', payload);
            showAlert(data.message || `Regularization submitted for ${selectedDates.length} day(s).`);
            resetRegularizeForm();
            clearEligibleSelection();
            regularizeModal?.hide();
            await reloadDashboard(1);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(regularizeSubmitBtn, false);
        }
    });

    regularizeModalEl?.addEventListener('hidden.bs.modal', () => {
        resetRegularizeForm();
    });

    initEmployeeFilter();

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setActiveTab(button.dataset.regularizeTab);
        });
    });

    if (isHrView) {
        setActiveTab(activeTab);
        await loadSummary();
    } else {
        await reloadDashboard();
    }

    filterMonth?.addEventListener('change', () => {
        reloadDashboard(1);
        if (eligibleDatesContainer) {
            clearEligibleSelection();
            loadEligibleDates(urlDate || null).catch((error) => showAlert(getErrorMessage(error), 'danger'));
        }
    });
    filterStatus?.addEventListener('change', () => {
        if (isHrView && activeTab !== 'history') {
            return;
        }

        loadRequests(1);
    });
    filterReset?.addEventListener('click', () => {
        employeeSearch?.clearSelection?.();
        if (filterStatus) filterStatus.value = '';
        if (filterMonth) filterMonth.value = currentMonthValue();
        reloadDashboard(1);
    });
    bindPagination(myRequestsPaginationList, (page) => loadMyRequests(page));
    bindPerPageSelect(myRequestsPerPageSelect, (perPage) => {
        myRequestsPerPage = perPage;
        loadMyRequests(1);
    });

    bindPagination(paginationList, (page) => loadRequests(page));
    bindPerPageSelect(regularizePerPageSelect, (perPage) => {
        regularizePerPage = perPage;
        loadRequests(1);
    });

    if (urlDate && eligibleDatesByKey[urlDate]) {
        setEligibleSelection([urlDate]);
        openRegularizeModal([urlDate]);
    } else if (eligibleDatesContainer) {
        updateEligibleSelectionUi();
    }
});
