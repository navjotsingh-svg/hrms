import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { renderActionGroup, renderCancelIconButton } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { setSubmitLoading } from './form-utils';
import { bindEmployeeSearchSelect, formatEmployeeLabel } from './employee-autocomplete';

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'text-bg-danger',
    cancelled: 'text-bg-secondary',
}[status] || '');

const eligibleStatusClass = (status) => ({
    absent: 'regularize-eligible-card--absent',
    half_day: 'regularize-eligible-card--half-day',
    short_leave: 'regularize-eligible-card--short-leave',
    incomplete: 'regularize-eligible-card--incomplete',
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
    const tableBody = document.getElementById('regularizeTableBody');
    const alertBox = document.getElementById('regularizeAlert');
    const pendingContainer = document.getElementById('pendingRegularizeContainer');
    const eligibleDatesContainer = document.getElementById('eligibleDatesContainer');
    const eligibleEmployeeId = document.getElementById('eligibleEmployeeId');
    const filterStatus = document.getElementById('filterStatus');
    const filterYear = document.getElementById('filterYear');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('regularizePaginationInfo');
    const paginationList = document.getElementById('regularizePaginationList');
    const regularizeForm = document.getElementById('regularizeForm');
    const regularizeModalEl = document.getElementById('regularizeModal');
    const regularizeModalTimezone = document.getElementById('regularizeModalTimezone');
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
    const currentYear = new Date().getFullYear();
    let canViewAll = false;
    let eligibleDatesByKey = {};
    let employeeSearch = null;
    let selectedDates = [];
    let selectedEligibleDates = new Set();

    const selectedEmployeeId = () => employeeSearch?.getSelectedId?.() || eligibleEmployeeId?.value || null;

    if (filterYear) {
        filterYear.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`).join('');
    }

    if (regularizeModalTimezone) {
        regularizeModalTimezone.textContent = formatTimezoneLabel();
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const formatTimes = (item) => {
        const parts = [];
        if (item.requested_punch_in_label) parts.push(`In ${item.requested_punch_in_label}`);
        if (item.requested_punch_out_label) parts.push(`Out ${item.requested_punch_out_label}`);
        return parts.join(' · ') || '—';
    };

    const selectableEligibleDates = () => Object.values(eligibleDatesByKey)
        .filter((item) => !item.has_pending_request)
        .map((item) => item.date);

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
        if (!eligibleDatesByKey[date] || eligibleDatesByKey[date].has_pending_request) {
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
            dates.filter((date) => eligibleDatesByKey[date] && !eligibleDatesByKey[date].has_pending_request),
        );
        updateEligibleSelectionUi();
    };

    const clearEligibleSelection = () => {
        selectedEligibleDates.clear();
        updateEligibleSelectionUi();
    };

    const availableEligibleDates = () => Object.values(eligibleDatesByKey)
        .filter((item) => !item.has_pending_request && !selectedDates.includes(item.date));

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

        if (punchOutField && !punchOutField.value) {
            punchOutField.value = firstSelected.suggested_punch_out || '';
        }
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
                    ? `${item.status_label} · In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}`
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
    };

    const addSelectedDate = (date) => {
        if (!date || !eligibleDatesByKey[date] || eligibleDatesByKey[date].has_pending_request) {
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
            employeeField.value = employeeId || selectedEmployeeId() || '';
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

    const renderEligibleDates = (payload) => {
        if (!eligibleDatesContainer) return;

        const dates = payload.dates || [];
        eligibleDatesByKey = Object.fromEntries(dates.map((item) => [item.date, item]));
        selectedEligibleDates = new Set(
            [...selectedEligibleDates].filter((date) => eligibleDatesByKey[date] && !eligibleDatesByKey[date].has_pending_request),
        );

        if (!dates.length) {
            eligibleDatesContainer.innerHTML = '<div class="text-muted py-3">No dates need regularization.</div>';
            updateEligibleSelectionUi();
            return;
        }

        eligibleDatesContainer.innerHTML = dates.map((item) => {
            if (item.has_pending_request) {
                return `
                    <div class="regularize-eligible-card regularize-eligible-card--pending ${eligibleStatusClass(item.status)}">
                        <div class="regularize-eligible-card-date">${item.date_label}</div>
                        <div class="regularize-eligible-card-status">${item.status_label}</div>
                        <div class="regularize-eligible-card-meta">In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}</div>
                        <span class="badge text-bg-warning mt-2">Pending approval</span>
                    </div>
                `;
            }

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
                        In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}
                    </div>
                    <div class="regularize-eligible-card-meta">Worked ${item.worked_hours_label} / ${item.required_hours_label}</div>
                </div>
            `;
        }).join('');

        updateEligibleSelectionUi();
    };

    const initEmployeeFilter = async () => {
        if (!document.getElementById('eligibleEmployeeInput')) {
            return;
        }

        try {
            const { data } = await api.get('/employees', { params: { per_page: 100, status: 'active' } });
            const employees = data.data.employees || [];
            canViewAll = true;
            const defaultId = window.REGULARIZE_DEFAULT_EMPLOYEE_ID;
            const defaultEmployee = employees.find((employee) => String(employee.id) === String(defaultId))
                || employees[0]
                || null;

            employeeSearch = bindEmployeeSearchSelect({
                inputId: 'eligibleEmployeeInput',
                hiddenId: 'eligibleEmployeeId',
                onSelect: () => {
                    loadEligibleDates();
                },
            });

            if (defaultEmployee) {
                employeeSearch.setSelection({
                    id: defaultEmployee.id,
                    label: formatEmployeeLabel(defaultEmployee),
                });
            }
        } catch (error) {
            console.error(getErrorMessage(error));
        }
    };

    const loadEligibleDates = async () => {
        if (!eligibleDatesContainer) return;

        eligibleDatesContainer.innerHTML = '<div class="text-muted py-3">Loading eligible dates...</div>';

        try {
            const params = {};
            const employeeId = selectedEmployeeId();

            if (employeeId) {
                params.employee_id = employeeId;
            }

            const { data } = await api.get('/attendance-regularizations/eligible-dates', { params });
            renderEligibleDates(data.data || {});
        } catch (error) {
            eligibleDatesContainer.innerHTML = `<div class="text-danger py-3">${getErrorMessage(error)}</div>`;
        }
    };

    const renderPending = (groups) => {
        if (!pendingContainer) return;
        if (!groups.length) {
            pendingContainer.innerHTML = '<div class="text-muted">No pending regularization requests.</div>';
            return;
        }

        pendingContainer.innerHTML = groups.map((group) => {
            const employeeName = group.employee?.full_name || 'Employee';
            const employeeCode = group.employee?.employee_code || '';
            const dayCount = group.day_count || group.dates?.length || 0;
            const isBatch = dayCount > 1 && group.batch_id;
            const title = isBatch
                ? `${employeeName} · ${dayCount} day(s)`
                : `${employeeName} · ${group.dates?.[0]?.attendance_date_label || '—'}`;
            const times = formatTimes(group);
            const dateList = (group.dates || [])
                .map((date) => `<span class="regularize-pending-date-chip">${date.attendance_date_short_label || date.attendance_date_label}</span>`)
                .join('');
            const reviewActions = group.can_review ? (isBatch && group.batch_id ? `
                ${renderApproveIconButton('data-approve-regularize-batch', group.batch_id, `Approve ${dayCount} day(s)`)}
                ${renderRejectIconButton('data-reject-regularize-batch', group.batch_id, `Reject ${dayCount} day(s)`)}
            ` : `
                ${renderApproveIconButton('data-approve-regularize', group.request_ids?.[0], 'Approve regularization')}
                ${renderRejectIconButton('data-reject-regularize', group.request_ids?.[0], 'Reject regularization')}
            `) : '';

            return `
                <div class="regularize-pending-group border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${title}</div>
                            ${employeeCode ? `<div class="small text-muted">${employeeCode}</div>` : ''}
                            <div class="small text-muted mt-1">${times}</div>
                            ${group.created_at_label ? `<div class="small text-muted">Submitted ${group.created_at_label}</div>` : ''}
                            <div class="regularize-pending-dates mt-2">${dateList}</div>
                            <div class="small mt-2">${group.reason || ''}</div>
                        </div>
                        <div class="table-action-group">
                            ${reviewActions}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderRow = (item, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const actions = [];
        if (item.can_cancel && item.status === 'pending') {
            actions.push(renderCancelIconButton('data-cancel-regularize', item.id, 'Cancel request'));
        }
        return `<tr>
            <td>${serial}</td>
            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
            <td>
                ${item.attendance_date_label || '—'}
                ${item.batch_id ? '<div><span class="badge text-bg-light border mt-1">Multi-day request</span></div>' : ''}
            </td>
            <td>${formatTimes(item)}</td>
            <td class="text-truncate" style="max-width:220px">${item.reason}</td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            <td>${actions.length ? renderActionGroup(actions.join('')) : '—'}</td>
        </tr>`;
    };

    const groupHistoryRows = (requests) => {
        const groups = [];
        const seenBatchIds = new Set();

        requests.forEach((item) => {
            if (item.batch_id) {
                if (seenBatchIds.has(item.batch_id)) {
                    return;
                }

                const batchItems = requests.filter((request) => request.batch_id === item.batch_id);
                seenBatchIds.add(item.batch_id);

                if (batchItems.length > 1) {
                    groups.push({ type: 'batch', items: batchItems });
                    return;
                }
            }

            groups.push({ type: 'single', items: [item] });
        });

        return groups;
    };

    const renderBatchRow = (items, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const first = items[0];
        const dateChips = items
            .map((item) => `<span class="regularize-pending-date-chip">${item.attendance_date_label || '—'}</span>`)
            .join('');
        const actions = items
            .filter((item) => item.can_cancel && item.status === 'pending')
            .map((item) => renderCancelIconButton('data-cancel-regularize', item.id, `Cancel ${item.attendance_date_label}`));

        return `<tr>
            <td>${serial}</td>
            <td>${first.employee?.full_name || '—'}<div class="small text-muted">${first.employee?.employee_code || ''}</div></td>
            <td>
                <div class="regularize-pending-dates">${dateChips}</div>
                <div><span class="badge text-bg-light border mt-1">${items.length} day(s)</span></div>
            </td>
            <td>${formatTimes(first)}</td>
            <td class="text-truncate" style="max-width:220px">${first.reason}</td>
            <td><span class="company-status-pill ${statusClass(first.status)}">${first.status_label}</span></td>
            <td>${actions.length ? renderActionGroup(actions.join('')) : '—'}</td>
        </tr>`;
    };

    const renderHistoryRows = (requests, pagination) => {
        let rowIndex = 0;

        return groupHistoryRows(requests).map((group) => {
            const row = group.type === 'batch'
                ? renderBatchRow(group.items, rowIndex, pagination)
                : renderRow(group.items[0], rowIndex, pagination);
            rowIndex += 1;
            return row;
        }).join('');
    };

    const loadPending = async () => {
        if (!pendingContainer) return;
        try {
            const { data } = await api.get('/attendance-regularizations/pending');
            renderPending(data.data.pending_groups || []);
        } catch {
            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending requests.</div>';
        }
    };

    const loadRequests = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: 10, year: filterYear?.value || currentYear };
        if (filterStatus?.value) params.status = filterStatus.value;
        try {
            const { data } = await api.get('/attendance-regularizations', { params });
            const requests = data.data.regularization_requests || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = requests.length
                ? renderHistoryRows(requests, pagination)
                : '<tr><td colspan="7" class="text-center text-muted py-5">No regularization requests found.</td></tr>';
            paginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No requests found';
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
                await api.patch(`/attendance-regularizations/${id}/approve`);
                showAlert('Regularization request approved.');
            } else {
                const notes = prompt('Rejection reason:');
                if (!notes?.trim()) return;
                await api.patch(`/attendance-regularizations/${id}/reject`, { notes: notes.trim() });
                showAlert('Regularization request rejected.');
            }
            await Promise.all([loadPending(), loadRequests(currentPage), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleBatchReview = async (batchId, action) => {
        try {
            if (action === 'approve') {
                const { data } = await api.patch(`/attendance-regularizations/batch/${batchId}/approve`);
                showAlert(data.message || 'Regularization requests approved.');
            } else {
                const notes = prompt('Rejection reason:');
                if (!notes?.trim()) return;
                const { data } = await api.patch(`/attendance-regularizations/batch/${batchId}/reject`, { notes: notes.trim() });
                showAlert(data.message || 'Regularization requests rejected.');
            }
            await Promise.all([loadPending(), loadRequests(currentPage), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleCancel = async (id) => {
        if (!window.confirm('Cancel this regularization request?')) return;
        try {
            await api.patch(`/attendance-regularizations/${id}/cancel`);
            showAlert('Regularization request cancelled.');
            await Promise.all([loadPending(), loadRequests(currentPage), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    openRegularizeRequestBtn?.addEventListener('click', () => {
        if (!selectedEligibleDates.size) {
            showAlert('Select at least one date to regularize.', 'warning');
            return;
        }

        openRegularizeModal([...selectedEligibleDates].sort(), selectedEmployeeId());
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
        if (cancel) handleCancel(cancel.dataset.cancelRegularize);
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

            const employeeId = document.getElementById('regularize_employee_id')?.value;
            if (canViewAll && employeeId) {
                payload.employee_id = Number(employeeId);
            }

            const { data } = await api.post('/attendance-regularizations', payload);
            showAlert(data.message || `Regularization submitted for ${selectedDates.length} day(s).`);
            resetRegularizeForm();
            clearEligibleSelection();
            regularizeModal?.hide();
            await Promise.all([loadPending(), loadRequests(1), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(regularizeSubmitBtn, false);
        }
    });

    regularizeModalEl?.addEventListener('hidden.bs.modal', () => {
        resetRegularizeForm();
    });

    if (document.getElementById('eligibleEmployeeInput')) {
        await initEmployeeFilter();
    }

    const urlDate = new URLSearchParams(window.location.search).get('date');
    await Promise.all([loadPending(), loadRequests(), loadEligibleDates()]);

    filterStatus?.addEventListener('change', () => loadRequests(1));
    filterYear?.addEventListener('change', () => loadRequests(1));
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        if (filterYear) filterYear.value = String(currentYear);
        loadRequests(1);
    });
    paginationList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn) loadRequests(Number(btn.dataset.page));
    });

    if (urlDate && eligibleDatesByKey[urlDate] && !eligibleDatesByKey[urlDate].has_pending_request) {
        setEligibleSelection([urlDate]);
        openRegularizeModal([urlDate], selectedEmployeeId());
    } else {
        updateEligibleSelectionUi();
    }
});
