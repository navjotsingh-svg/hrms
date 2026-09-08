import { Modal, Offcanvas } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { setSubmitLoading, renderDateTimeStackFromLabel } from './form-utils';

const formatTimezoneLabel = () => {
    const parts = new Intl.DateTimeFormat('en-IN', {
        timeZoneName: 'shortOffset',
    }).formatToParts(new Date());
    const offset = parts.find((part) => part.type === 'timeZoneName')?.value || '';
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local';

    return `Timezone (${offset}) ${zone.replace('_', ' ')}`;
};

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

const formatPendingOriginalTimes = (item) => {
    const parts = [];
    if (item.original_punch_in_label && item.original_punch_in_label !== '—') {
        parts.push(`In ${item.original_punch_in_label}`);
    }
    if (item.original_punch_out_label && item.original_punch_out_label !== '—') {
        parts.push(`Out ${item.original_punch_out_label}`);
    }
    return parts.join(' · ') || '—';
};

const formatPendingRequestedTimes = (item) => {
    const parts = [];
    if (item.requested_punch_in_label && item.requested_punch_in_label !== '—') {
        parts.push(`In ${item.requested_punch_in_label}`);
    }
    if (item.requested_punch_out_label && item.requested_punch_out_label !== '—') {
        parts.push(`Out ${item.requested_punch_out_label}`);
    }
    return parts.join(' · ') || '—';
};

export function initAttendanceRegularizePanel(options = {}) {
    const {
        getMonth = () => null,
        getEmployeeId = () => null,
        onSubmitted = async () => {},
        onAlert = () => {},
    } = options;

    const panelEl = document.getElementById('attendanceRegularizePanel');
    if (!panelEl) {
        return null;
    }

    const regularizeForm = document.getElementById('regularizeForm');
    const regularizePanelTimezone = document.getElementById('regularizePanelTimezone');
    const regularizePolicyBanner = document.getElementById('regularizePolicyBanner');
    const regularizeSelectedDatesList = document.getElementById('regularizeSelectedDatesList');
    const regularizeSubmitBtn = document.getElementById('regularizeSubmitBtn');
    const addRegularizeDateBtn = document.getElementById('addRegularizeDateBtn');
    const addRegularizeRangeBtn = document.getElementById('addRegularizeRangeBtn');
    const pickRegularizeDateModalEl = document.getElementById('pickRegularizeDateModal');
    const pickRegularizeDateSelect = document.getElementById('pickRegularizeDateSelect');
    const confirmPickRegularizeDateBtn = document.getElementById('confirmPickRegularizeDateBtn');
    const pickRegularizeRangeModalEl = document.getElementById('pickRegularizeRangeModal');
    const regularizeRangeFrom = document.getElementById('regularizeRangeFrom');
    const regularizeRangeTo = document.getElementById('regularizeRangeTo');
    const confirmRegularizeRangeBtn = document.getElementById('confirmRegularizeRangeBtn');
    const regularizePanelTabs = document.getElementById('regularizePanelTabs');
    const regularizePanelFooter = document.getElementById('regularizePanelFooter');
    const regularizeSubmittedList = document.getElementById('regularizeSubmittedList');
    const regularizeSubmittedBadge = document.getElementById('regularizeSubmittedBadge');
    const regularizeTabButtons = Array.from(document.querySelectorAll('[data-regularize-panel-tab]'));
    const regularizeTabPanels = Array.from(document.querySelectorAll('[data-regularize-panel-panel]'));

    const panel = Offcanvas.getOrCreateInstance(panelEl);
    const pickRegularizeDateModal = pickRegularizeDateModalEl
        ? Modal.getOrCreateInstance(pickRegularizeDateModalEl)
        : null;
    const pickRegularizeRangeModal = pickRegularizeRangeModalEl
        ? Modal.getOrCreateInstance(pickRegularizeRangeModalEl)
        : null;

    let eligibleDatesByKey = {};
    let selectedDates = [];
    let selectedDateEntries = {};
    let regularizationPolicy = null;
    let pendingRequests = [];
    let activePanelTab = 'new';

    if (regularizePanelTimezone) {
        regularizePanelTimezone.textContent = formatTimezoneLabel();
    }

    const defaultEntryForDate = (date) => {
        const item = eligibleDatesByKey[date];

        return {
            punch_in_time: item?.suggested_punch_in || '',
            punch_out_time: item?.suggested_punch_out || '',
            expanded: true,
        };
    };

    const getDateEntry = (date) => selectedDateEntries[date] || defaultEntryForDate(date);

    const remainingDayLimit = () => {
        const remaining = regularizationPolicy?.remaining_days ?? regularizationPolicy?.remaining_requests;

        return remaining === null || remaining === undefined ? null : Number(remaining);
    };

    const canAddMoreDates = () => {
        const remaining = remainingDayLimit();

        return remaining === null || selectedDates.length < remaining;
    };

    const exceedsRemainingDayLimit = () => {
        const remaining = remainingDayLimit();

        return remaining !== null && selectedDates.length > remaining;
    };

    const setActivePanelTab = (tab) => {
        activePanelTab = tab === 'submitted' ? 'submitted' : 'new';

        regularizeTabButtons.forEach((button) => {
            const isActive = button.dataset.regularizePanelTab === activePanelTab;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        regularizeTabPanels.forEach((panelEl) => {
            const isActive = panelEl.dataset.regularizePanelPanel === activePanelTab;
            panelEl.classList.toggle('d-none', !isActive);
        });

        regularizePanelFooter?.classList.toggle('d-none', activePanelTab !== 'new');
    };

    const updateSubmittedBadge = () => {
        if (!regularizeSubmittedBadge) {
            return;
        }

        const count = pendingRequests.length;
        regularizeSubmittedBadge.textContent = String(count);
        regularizeSubmittedBadge.classList.toggle('d-none', count === 0);
    };

    const renderSubmittedRequests = () => {
        if (!regularizeSubmittedList) {
            return;
        }

        updateSubmittedBadge();

        if (!pendingRequests.length) {
            regularizeSubmittedList.innerHTML = `
                <div class="text-muted small py-3">No pending regularization requests.</div>
            `;
            return;
        }

        regularizeSubmittedList.innerHTML = pendingRequests.map((item) => {
            const submittedAt = item.submitted_at_label
                ? renderDateTimeStackFromLabel(item.submitted_at_label)
                : '';
            const cancelButton = item.can_cancel
                ? `<button type="button" class="btn btn-sm btn-outline-danger" data-cancel-regularize="${item.id}">Cancel</button>`
                : '';
            const viewLink = `<a href="/requests/regularization/${item.id}" class="btn btn-sm btn-outline-primary">View</a>`;

            return `
                <div class="attendance-regularize-panel-submitted-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${item.date_label || item.date_short_label || item.date}</div>
                            <div class="attendance-regularize-panel-submitted-meta">Pending approval</div>
                            <div class="attendance-regularize-panel-submitted-meta mt-1">Current: ${formatPendingOriginalTimes(item)}</div>
                            <div class="attendance-regularize-panel-submitted-meta">Requested: ${formatPendingRequestedTimes(item)}</div>
                            ${submittedAt ? `<div class="attendance-regularize-panel-submitted-meta mt-1">Submitted ${submittedAt}</div>` : ''}
                            ${item.reason ? `<div class="small mt-2">${item.reason}</div>` : ''}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            ${viewLink}
                            ${cancelButton}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const handleCancelRequest = async (id) => {
        if (!window.confirm('Cancel this regularization request?')) {
            return;
        }

        try {
            await api.patch(`/attendance-regularizations/${id}/cancel`);
            onAlert('Regularization request cancelled.');
            await loadEligibleDates(getMonth(), getEmployeeId());
            await onSubmitted();
        } catch (error) {
            onAlert(getErrorMessage(error), 'danger');
        }
    };

    const renderPolicyBanner = (policy) => {
        regularizationPolicy = policy || null;

        if (!regularizePolicyBanner) {
            return;
        }

        if (!policy?.summary) {
            regularizePolicyBanner.classList.add('d-none');
            regularizePolicyBanner.textContent = '';
            return;
        }

        regularizePolicyBanner.textContent = policy.summary;
        regularizePolicyBanner.classList.remove('d-none');
        updateSelectedDatesUi();
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
                const entry = getDateEntry(date);
                const label = item?.date_short_label || item?.date_label || date;
                const meta = item
                    ? (item.is_update_request
                        ? `Approved: ${formatOriginalTimesFromEligible(item).replace(/^Approved: /, '')}`
                        : `Current: ${formatOriginalTimesFromEligible(item)}`)
                    : '';
                const expanded = entry.expanded !== false;

                return `
                    <li class="regularize-dates-list-item regularize-dates-list-item--expandable${expanded ? ' is-expanded' : ''}" data-regularize-date-row="${date}">
                        <div class="regularize-dates-list-item-main">
                            <button
                                type="button"
                                class="regularize-dates-toggle-btn"
                                data-toggle-regularize-date="${date}"
                                aria-expanded="${expanded ? 'true' : 'false'}"
                                aria-label="Toggle times for ${label}"
                            >
                                <span class="regularize-dates-toggle-icon" aria-hidden="true">${expanded ? '▾' : '▸'}</span>
                            </button>
                            <div class="regularize-dates-list-item-copy">
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
                        </div>
                        <div class="regularize-dates-list-item-fields">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="regularize_in_${date}">Clock In</label>
                                    <input
                                        type="time"
                                        class="form-control form-control-sm"
                                        id="regularize_in_${date}"
                                        data-regularize-in="${date}"
                                        value="${entry.punch_in_time || ''}"
                                        required
                                    >
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="regularize_out_${date}">Clock Out</label>
                                    <input
                                        type="time"
                                        class="form-control form-control-sm"
                                        id="regularize_out_${date}"
                                        data-regularize-out="${date}"
                                        value="${entry.punch_out_time || ''}"
                                    >
                                </div>
                            </div>
                        </div>
                    </li>
                `;
            }).join('');
        }

        if (regularizeSubmitBtn) {
            const count = selectedDates.length;
            const remaining = remainingDayLimit();
            const limitReached = remaining === 0;
            const overLimit = exceedsRemainingDayLimit();
            const disabledByPolicy = regularizationPolicy?.enabled === false || limitReached || overLimit;
            regularizeSubmitBtn.disabled = count === 0 || disabledByPolicy;
            regularizeSubmitBtn.textContent = limitReached
                ? 'Monthly day limit reached'
                : overLimit
                    ? `Only ${remaining} day(s) remaining this month`
                    : `Submit for ${count} day(s)`;
        }
    };

    const addSelectedDate = (date) => {
        if (!date || !eligibleDatesByKey[date] || selectedDates.includes(date)) {
            return false;
        }

        if (!canAddMoreDates()) {
            onAlert('You have reached the monthly day limit for regularization.', 'warning');
            return false;
        }

        selectedDates.push(date);
        selectedDateEntries[date] = defaultEntryForDate(date);
        selectedDates.sort();
        updateSelectedDatesUi();
        return true;
    };

    const addSelectedDates = (dates) => dates.filter((date) => addSelectedDate(date)).length;

    const removeSelectedDate = (date) => {
        selectedDates = selectedDates.filter((item) => item !== date);
        delete selectedDateEntries[date];
        updateSelectedDatesUi();
    };

    const resetForm = () => {
        selectedDates = [];
        selectedDateEntries = {};
        regularizeForm?.reset();
        updateSelectedDatesUi();
    };

    const availableEligibleDates = () => Object.values(eligibleDatesByKey)
        .filter((item) => !selectedDates.includes(item.date));

    const loadEligibleDates = async (month, employeeId = null) => {
        const params = { month: month || getMonth() };
        const resolvedEmployeeId = employeeId ?? getEmployeeId();

        if (resolvedEmployeeId) {
            params.employee_id = Number(resolvedEmployeeId);
        }

        const { data } = await api.get('/attendance-regularizations/eligible-dates', { params });
        eligibleDatesByKey = {};
        (data.data?.dates || []).forEach((item) => {
            eligibleDatesByKey[item.date] = item;
        });
        pendingRequests = data.data?.pending_requests || [];
        renderPolicyBanner(data.data?.policy || null);
        renderSubmittedRequests();

        return data.data || {};
    };

    const openPanel = async ({ dates = [], selectAllEligible = false, employeeId = null, tab = 'new' } = {}) => {
        resetForm();
        setActivePanelTab(tab);

        const employeeField = document.getElementById('regularize_employee_id');
        const resolvedEmployeeId = employeeId ?? getEmployeeId();
        if (employeeField) {
            employeeField.value = resolvedEmployeeId || '';
        }

        try {
            await loadEligibleDates(getMonth(), resolvedEmployeeId);

            if (regularizationPolicy?.enabled === false) {
                onAlert('Attendance regularization is disabled for your company.', 'warning');
                return;
            }

            if (tab !== 'submitted' && selectAllEligible) {
                const remaining = remainingDayLimit();
                const eligibleDates = Object.keys(eligibleDatesByKey).sort();
                const datesToAdd = remaining === null
                    ? eligibleDates
                    : eligibleDates.slice(0, remaining);

                addSelectedDates(datesToAdd);

                if (remaining !== null && eligibleDates.length > remaining) {
                    onAlert(`Only ${remaining} day(s) can be added this month based on your company limit.`, 'warning');
                }
            } else if (dates.length) {
                addSelectedDates(dates.filter((date) => eligibleDatesByKey[date]));
            }

            if (tab === 'submitted') {
                setActivePanelTab('submitted');
            }

            panel.show();
        } catch (error) {
            onAlert(getErrorMessage(error), 'danger');
        }
    };

    const openPickDateModal = () => {
        const available = availableEligibleDates();

        if (!available.length) {
            onAlert('No more eligible dates available to add.', 'warning');
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
            onAlert('No more eligible dates available to add.', 'warning');
            return;
        }

        const sortedDates = available.map((item) => item.date).sort();
        if (regularizeRangeFrom) regularizeRangeFrom.value = sortedDates[0] || '';
        if (regularizeRangeTo) regularizeRangeTo.value = sortedDates[sortedDates.length - 1] || '';

        pickRegularizeRangeModal?.show();
    };

    addRegularizeDateBtn?.addEventListener('click', openPickDateModal);
    addRegularizeRangeBtn?.addEventListener('click', openPickRangeModal);

    regularizePanelTabs?.addEventListener('click', (event) => {
        const tabButton = event.target.closest('[data-regularize-panel-tab]');
        if (!tabButton) {
            return;
        }

        setActivePanelTab(tabButton.dataset.regularizePanelTab);
    });

    regularizeSubmittedList?.addEventListener('click', (event) => {
        const cancelButton = event.target.closest('[data-cancel-regularize]');
        if (cancelButton) {
            handleCancelRequest(cancelButton.dataset.cancelRegularize);
        }
    });

    confirmPickRegularizeDateBtn?.addEventListener('click', () => {
        const date = pickRegularizeDateSelect?.value;
        if (!date) return;

        if (addSelectedDate(date)) {
            pickRegularizeDateModal?.hide();
        }
    });

    confirmRegularizeRangeBtn?.addEventListener('click', () => {
        const from = regularizeRangeFrom?.value;
        const to = regularizeRangeTo?.value;

        if (!from || !to) {
            onAlert('Select both start and end dates.', 'warning');
            return;
        }

        if (from > to) {
            onAlert('End date must be on or after start date.', 'warning');
            return;
        }

        const eligibleInRange = availableEligibleDates()
            .filter((item) => item.date >= from && item.date <= to)
            .map((item) => item.date);

        if (!eligibleInRange.length) {
            onAlert('No eligible dates found in the selected range.', 'warning');
            return;
        }

        addSelectedDates(eligibleInRange);
        pickRegularizeRangeModal?.hide();
    });

    regularizeSelectedDatesList?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-regularize-date]');
        const toggleButton = event.target.closest('[data-toggle-regularize-date]');

        if (removeButton) {
            removeSelectedDate(removeButton.dataset.removeRegularizeDate);
            return;
        }

        if (toggleButton) {
            const date = toggleButton.dataset.toggleRegularizeDate;
            const entry = getDateEntry(date);
            selectedDateEntries[date] = {
                ...entry,
                expanded: !entry.expanded,
            };
            updateSelectedDatesUi();
        }
    });

    regularizeSelectedDatesList?.addEventListener('input', (event) => {
        const inField = event.target.closest('[data-regularize-in]');
        const outField = event.target.closest('[data-regularize-out]');

        if (inField) {
            const date = inField.dataset.regularizeIn;
            selectedDateEntries[date] = {
                ...getDateEntry(date),
                punch_in_time: inField.value,
            };
        }

        if (outField) {
            const date = outField.dataset.regularizeOut;
            selectedDateEntries[date] = {
                ...getDateEntry(date),
                punch_out_time: outField.value,
            };
        }
    });

    regularizeForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedDates.length) {
            onAlert('Add at least one date before submitting.', 'warning');
            return;
        }

        if (exceedsRemainingDayLimit()) {
            const remaining = remainingDayLimit();
            onAlert(`You can only regularize ${remaining} more day(s) this month. Remove extra dates before submitting.`, 'warning');
            return;
        }

        setSubmitLoading(regularizeSubmitBtn, true, { submittingText: 'Submitting...' });
        try {
            const entries = selectedDates.map((date) => {
                const entry = getDateEntry(date);

                return {
                    date,
                    punch_in_time: entry.punch_in_time || null,
                    punch_out_time: entry.punch_out_time || null,
                };
            });

            const payload = {
                entries,
                reason: document.getElementById('regularizeReason')?.value?.trim(),
            };

            const employeeField = document.getElementById('regularize_employee_id');
            if (employeeField?.value) {
                payload.employee_id = Number(employeeField.value);
            }

            const { data } = await api.post('/attendance-regularizations', payload);
            onAlert(data.message || `Regularization submitted for ${selectedDates.length} day(s).`);
            resetForm();
            await loadEligibleDates(getMonth(), getEmployeeId());
            setActivePanelTab('submitted');
            await onSubmitted();
        } catch (error) {
            onAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(regularizeSubmitBtn, false);
        }
    });

    panelEl.addEventListener('hidden.bs.offcanvas', () => {
        resetForm();
        setActivePanelTab('new');
    });

    return {
        open: openPanel,
        reloadEligibleDates: loadEligibleDates,
    };
}
