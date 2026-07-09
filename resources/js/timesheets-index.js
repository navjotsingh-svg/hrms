import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';
import {
    buildRangeQueryParams,
    formatDisplayDate as formatRangeDisplayDate,
    monthStartDateInput,
    resolveClientDateRange,
    todayDateInput,
} from './date-range-utils';

const pageConfig = window.TIMESHEET_PAGE || {};

const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatToday = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const SUBMIT_BUTTON_LABEL = 'Submit';
const SUBMITTING_BUTTON_LABEL = 'Submitting...';

const formatDisplayDate = (value) => {
    if (!value) {
        return 'selected date';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const calculateHours = (startTime, endTime) => {
    if (!startTime || !endTime) {
        return null;
    }

    const [startHour, startMinute] = startTime.split(':').map(Number);
    const [endHour, endMinute] = endTime.split(':').map(Number);
    const startTotal = (startHour * 60) + startMinute;
    const endTotal = (endHour * 60) + endMinute;

    if (endTotal <= startTotal) {
        return null;
    }

    return Math.round(((endTotal - startTotal) / 60) * 100) / 100;
};

const formatHoursLabel = (hours) => {
    if (hours == null || Number.isNaN(hours)) {
        return '—';
    }

    const wholeHours = Math.floor(hours);
    const minutes = Math.round((hours - wholeHours) * 60);

    if (wholeHours === 0) {
        return `${minutes}m`;
    }

    if (minutes === 0) {
        return wholeHours === 1 ? '1h' : `${wholeHours}h`;
    }

    return `${wholeHours}h ${minutes}m`;
};

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('timesheetsAlert');
    const form = document.getElementById('timesheetForm');
    const formAlert = document.getElementById('timesheetFormAlert');
    const daySummary = document.getElementById('daySummary');
    const entriesBody = document.getElementById('timesheetEntriesBody');
    const addRowBtn = document.getElementById('addTimesheetRowBtn');
    const submitBtn = document.getElementById('submitTimesheetBtn');
    const formActions = document.getElementById('timesheetFormActions');
    const rowActions = document.getElementById('timesheetRowActions');
    const noProjectsNotice = document.getElementById('noProjectsNotice');
    const readOnlyNotice = document.getElementById('readOnlyNotice');
    const periodReportsContainer = document.getElementById('periodReportsContainer');
    const periodReportsCard = document.getElementById('periodReportsCard');
    const periodReportsSummary = document.getElementById('periodReportsSummary');
    const dailyReportCard = document.getElementById('dailyReportCard');
    const teamEmployeeSelect = document.getElementById('teamEmployeeSelect');
    const dailyReportTitle = document.getElementById('dailyReportTitle');
    const discussionsTitle = document.getElementById('timesheetDiscussionsTitle');
    const projectDiscussionsWrap = document.getElementById('timesheetProjectDiscussions');
    const projectDiscussionsList = document.getElementById('timesheetProjectDiscussionsList');
    const rangePresetEl = document.getElementById('timesheetRangePreset');
    const customRangeWrap = document.getElementById('timesheetCustomRange');
    const fromDateEl = document.getElementById('timesheetFromDate');
    const toDateEl = document.getElementById('timesheetToDate');
    const applyRangeBtn = document.getElementById('timesheetApplyRangeBtn');
    const rangeSummaryEl = document.getElementById('timesheetRangeSummary');
    const projectFilterWrap = document.getElementById('timesheetProjectFilterWrap');
    const projectFilterEl = document.getElementById('timesheetProjectFilter');

    let projectOptions = [];
    let rowCounter = 0;
    let loadedEntries = [];
    let commentsByProject = {};
    let pendingReply = { projectId: null, parentId: null };
    let selectedEmployeeId = pageConfig.ownEmployeeId ? Number(pageConfig.ownEmployeeId) : null;
    let capabilities = {
        can_submit: Boolean(pageConfig.canSubmit),
        can_comment: false,
        can_reply: false,
        is_viewing_team_member: false,
    };
    let customRangePending = false;
    let currentRange = resolveClientDateRange('today');
    let selectedProjectFilter = '';
    let loadedPeriodDays = [];
    let isSubmitting = false;

    if (!form || !entriesBody) {
        return;
    }

    const selectedWorkDate = () => currentRange.from_date;

    const isSingleDayView = () => currentRange.from_date === currentRange.to_date;

    const rangeParams = () => {
        const params = buildRangeQueryParams(currentRange);

        if (selectedEmployeeId) {
            params.employee_id = selectedEmployeeId;
        }

        return params;
    };

    const isCustomPickerActive = () => customRangePending || rangePresetEl?.value === 'custom';

    const updateRangeSummary = () => {
        if (!rangeSummaryEl) {
            return;
        }

        if (isCustomPickerActive() && currentRange.preset !== 'custom') {
            rangeSummaryEl.textContent = 'Select from and to dates, then click Apply.';
            return;
        }

        const presetLabel = rangePresetEl?.selectedOptions?.[0]?.textContent?.trim() || 'Today';

        rangeSummaryEl.textContent = currentRange.preset === 'custom'
            ? `Showing reports from ${formatRangeDisplayDate(currentRange.from_date)} to ${formatRangeDisplayDate(currentRange.to_date)}`
            : `Showing reports for ${presetLabel.toLowerCase()} (${formatRangeDisplayDate(currentRange.from_date)} – ${formatRangeDisplayDate(currentRange.to_date)})`;
    };

    const syncRangeControls = () => {
        if (isCustomPickerActive() && currentRange.preset !== 'custom') {
            customRangeWrap?.classList.remove('d-none');
            updateRangeSummary();
            return;
        }

        customRangePending = false;

        if (rangePresetEl) {
            rangePresetEl.value = currentRange.preset;
        }

        customRangeWrap?.classList.toggle('d-none', currentRange.preset !== 'custom');

        if (fromDateEl) {
            fromDateEl.value = currentRange.from_date || monthStartDateInput();
            fromDateEl.max = todayDateInput();
        }

        if (toDateEl) {
            toDateEl.value = currentRange.to_date || todayDateInput();
            toDateEl.max = todayDateInput();
        }

        updateRangeSummary();
    };

    const showCustomRangePicker = () => {
        customRangePending = true;

        if (rangePresetEl) {
            rangePresetEl.value = 'custom';
        }

        currentRange = resolveClientDateRange(
            'custom',
            fromDateEl?.value || currentRange.from_date || monthStartDateInput(),
            toDateEl?.value || currentRange.to_date || todayDateInput(),
        );

        customRangeWrap?.classList.remove('d-none');

        if (fromDateEl) {
            fromDateEl.value = currentRange.from_date;
        }

        if (toDateEl) {
            toDateEl.value = currentRange.to_date;
        }

        updateRangeSummary();
        fromDateEl?.focus();
    };

    const applyCurrentRangeSelection = async () => {
        const preset = rangePresetEl?.value || 'today';

        if (preset === 'custom') {
            if (!fromDateEl?.value || !toDateEl?.value) {
                showAlert('Custom range requires from and to dates.', 'warning');
                return;
            }

            currentRange = resolveClientDateRange('custom', fromDateEl.value, toDateEl.value);
        } else {
            currentRange = resolveClientDateRange(preset);
        }

        customRangePending = false;
        selectedProjectFilter = '';

        if (projectFilterEl) {
            projectFilterEl.value = '';
        }

        syncRangeControls();
        await reloadAll();
    };

    const updateViewLayout = () => {
        const singleDay = isSingleDayView();

        dailyReportCard?.classList.toggle('d-none', !singleDay);
        periodReportsCard?.classList.toggle('d-none', singleDay);
    };

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const showFormAlert = (message) => {
        formAlert.textContent = message;
        formAlert.classList.remove('d-none');
    };

    const hideFormAlert = () => {
        formAlert.classList.add('d-none');
    };

    const requestParams = () => {
        const params = { work_date: selectedWorkDate() };

        if (selectedEmployeeId) {
            params.employee_id = selectedEmployeeId;
        }

        return params;
    };

    const isPastDate = () => Boolean(selectedWorkDate()) && selectedWorkDate() < formatToday();

    const isFutureDate = () => Boolean(selectedWorkDate()) && selectedWorkDate() > formatToday();

    const isReadOnlyView = () => {
        if (capabilities.is_viewing_team_member) {
            return true;
        }

        if (!capabilities.can_submit) {
            return true;
        }

        return isFutureDate();
    };

    const updateSubmitButtonLabel = () => {
        if (!submitBtn || isReadOnlyView()) {
            return;
        }

        submitBtn.textContent = isSubmitting ? SUBMITTING_BUTTON_LABEL : SUBMIT_BUTTON_LABEL;
    };

    const setSubmittingState = (submitting) => {
        isSubmitting = submitting;

        if (!submitBtn) {
            return;
        }

        submitBtn.disabled = submitting || projectOptions.length === 0;
        submitBtn.textContent = submitting ? SUBMITTING_BUTTON_LABEL : SUBMIT_BUTTON_LABEL;
        submitBtn.setAttribute('aria-busy', submitting ? 'true' : 'false');
    };

    const applyViewMode = () => {
        const readOnly = isReadOnlyView();

        if (readOnlyNotice) {
            const showNotice = readOnly && (capabilities.is_viewing_team_member || isFutureDate());

            readOnlyNotice.classList.toggle('d-none', !showNotice);

            if (capabilities.is_viewing_team_member) {
                readOnlyNotice.textContent = 'You are viewing a team member\'s report for the selected work date.';
            } else if (isFutureDate()) {
                readOnlyNotice.textContent = 'Future dates are view-only. Select today or a past date to submit your report.';
            }
        }

        formActions?.classList.toggle('d-none', readOnly);
        rowActions?.classList.toggle('d-none', readOnly);
        addRowBtn?.classList.toggle('d-none', readOnly);
        submitBtn?.classList.toggle('d-none', readOnly);

        entriesBody.querySelectorAll('[data-row-id] input, [data-row-id] select, [data-row-id] textarea, [data-row-id] button[data-remove-row]').forEach((element) => {
            if (element.matches('button[data-remove-row]')) {
                element.classList.toggle('d-none', readOnly);
            } else {
                element.disabled = readOnly;
            }
        });

        if (dailyReportTitle) {
            const dateLabel = formatDisplayDate(selectedWorkDate());

            if (capabilities.is_viewing_team_member) {
                dailyReportTitle.textContent = `Team Daily Report · ${dateLabel}`;
            } else if (isFutureDate()) {
                dailyReportTitle.textContent = `Upcoming Daily Report · ${dateLabel}`;
            } else {
                dailyReportTitle.textContent = `Daily Report · ${dateLabel}`;
            }
        }

        updateSubmitButtonLabel();
    };

    const updateDaySummary = (summary) => {
        const dateLabel = formatDisplayDate(selectedWorkDate());

        if (!summary || !summary.entry_count) {
            daySummary.innerHTML = `<span class="text-muted">No report submitted for ${escapeHtml(dateLabel)} yet.</span>`;
            return;
        }

        daySummary.innerHTML = `
            <span class="badge text-bg-light border me-2">${escapeHtml(dateLabel)}</span>
            <span class="badge text-bg-light border me-2">${summary.entry_count} project${summary.entry_count === 1 ? '' : 's'}</span>
            <span class="fw-semibold">Total: ${formatHoursLabel(summary.total_hours)}</span>
        `;
    };

    const availableProjectsForRow = (currentRowId, selectedProjectId = '') => {
        const usedProjectIds = new Set(
            [...entriesBody.querySelectorAll('[data-row-id]')]
                .filter((row) => row.dataset.rowId !== String(currentRowId))
                .map((row) => row.querySelector('[data-project-select]')?.value)
                .filter(Boolean),
        );

        return projectOptions.filter((project) => {
            const id = String(project.id);

            return id === String(selectedProjectId) || !usedProjectIds.has(id);
        });
    };

    const renderProjectOptions = (currentRowId, selectedProjectId = '', entryProject = null) => {
        const options = availableProjectsForRow(currentRowId, selectedProjectId);

        if (
            selectedProjectId
            && entryProject?.name
            && !options.some((project) => String(project.id) === String(selectedProjectId))
        ) {
            options.unshift({
                id: Number(selectedProjectId),
                name: entryProject.name,
            });
        }

        return `
            <option value="">Select project</option>
            ${options.map((project) => `
                <option value="${project.id}" ${String(project.id) === String(selectedProjectId) ? 'selected' : ''}>
                    ${escapeHtml(project.name)}${project.name === 'Other' ? ' (Non-project work)' : ''}
                </option>
            `).join('')}
        `;
    };

    const syncRowHours = (row) => {
        const startInput = row.querySelector('[data-start-time]');
        const endInput = row.querySelector('[data-end-time]');
        const hoursLabel = row.querySelector('[data-hours-label]');
        const hours = calculateHours(startInput?.value, endInput?.value);

        if (hoursLabel) {
            hoursLabel.textContent = formatHoursLabel(hours);
        }
    };

    const refreshProjectSelects = () => {
        entriesBody.querySelectorAll('[data-row-id]').forEach((row) => {
            const select = row.querySelector('[data-project-select]');

            if (!select) {
                return;
            }

            const selected = select.value;
            select.innerHTML = renderProjectOptions(row.dataset.rowId, selected);

            if (selected && !select.value) {
                select.value = '';
            }
        });
    };

    const renderReadOnlyValue = (label, value) => `
        <div class="col-12">
            <div class="small text-muted mb-1">${escapeHtml(label)}</div>
            <div>${value ? escapeHtml(value) : '<span class="text-muted">—</span>'}</div>
        </div>
    `;

    const collectProjectsFromEntries = (entries = []) => {
        const projects = new Map();

        entries.forEach((entry) => {
            const projectId = entry.project_id ?? entry.project?.id;

            if (!projectId) {
                return;
            }

            projects.set(String(projectId), {
                id: Number(projectId),
                name: entry.project?.name || `Project #${projectId}`,
            });
        });

        return [...projects.values()].sort((left, right) => left.name.localeCompare(right.name));
    };

    const collectProjectsFromDays = (days = []) => collectProjectsFromEntries(
        days.flatMap((day) => day.entries || []),
    );

    const syncProjectFilterOptions = (projects = []) => {
        if (!projectFilterEl || !projectFilterWrap) {
            return;
        }

        if (!projects.length) {
            selectedProjectFilter = '';
            projectFilterWrap.classList.add('d-none');
            projectFilterEl.innerHTML = '<option value="">All projects</option>';
            return;
        }

        projectFilterWrap.classList.remove('d-none');

        const options = ['<option value="">All projects</option>']
            .concat(projects.map((project) => `
                <option value="${project.id}" ${String(project.id) === String(selectedProjectFilter) ? 'selected' : ''}>
                    ${escapeHtml(project.name)}
                </option>
            `));

        projectFilterEl.innerHTML = options.join('');

        if (selectedProjectFilter && !projects.some((project) => String(project.id) === String(selectedProjectFilter))) {
            selectedProjectFilter = '';
            projectFilterEl.value = '';
        }
    };

    const filterEntriesByProject = (entries = []) => {
        if (!selectedProjectFilter) {
            return entries;
        }

        return entries.filter((entry) => String(entry.project_id ?? entry.project?.id) === String(selectedProjectFilter));
    };

    const renderReadOnlyEntryCard = (entry) => {
        const projectId = Number(entry.project_id ?? entry.project?.id);
        const projectName = entry.project?.name || 'Project';
        const threads = commentsByProject[String(projectId)] || [];

        return `
            <div class="timesheet-entry-card border rounded mb-3" data-entry-project-id="${projectId}">
                <div class="p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="small text-muted mb-1">Project</div>
                            <div class="fw-semibold">${escapeHtml(projectName)}</div>
                        </div>
                        <div class="text-md-end">
                            <div class="small text-muted mb-1">Time</div>
                            <div>${escapeHtml(entry.start_time || '—')} – ${escapeHtml(entry.end_time || '—')}</div>
                            <div class="fw-semibold mt-1">${escapeHtml(entry.hours_label || formatHoursLabel(entry.hours))}</div>
                        </div>
                    </div>
                    <div class="row g-3 border-top pt-3">
                        ${renderReadOnlyValue('Completed on this project', entry.done_today || '')}
                        ${renderReadOnlyValue('Blockers or issues', entry.blockers || '')}
                        ${renderReadOnlyValue('Plan for tomorrow', entry.plan_tomorrow || '')}
                    </div>
                    ${capabilities.is_viewing_team_member ? `
                        <div class="border-top pt-3 mt-3" data-project-comment-threads="${projectId}">
                            <div class="small text-uppercase text-muted mb-2">Comments</div>
                            ${threads.length
                                ? threads.map((comment) => renderCommentThread(comment, projectId)).join('')
                                : '<div class="small text-muted">No comments on this project yet.</div>'}
                            ${renderProjectCommentForm(projectId)}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    };

    const renderEntryReportFields = (entry = null, readOnly = false) => {
        const doneToday = entry?.done_today || '';
        const blockers = entry?.blockers || '';
        const planTomorrow = entry?.plan_tomorrow || '';

        if (readOnly) {
            return `
                <div class="row g-3 p-2">
                    <div class="col-12">
                        <div class="small text-muted mb-1">Completed on this project</div>
                        <div>${doneToday ? escapeHtml(doneToday) : '<span class="text-muted">—</span>'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">Blockers or issues</div>
                        <div>${blockers ? escapeHtml(blockers) : '<span class="text-muted">—</span>'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">Plan for tomorrow</div>
                        <div>${planTomorrow ? escapeHtml(planTomorrow) : '<span class="text-muted">—</span>'}</div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="row g-3 p-2">
                <div class="col-12">
                    <label class="form-label small mb-1">Completed on this project <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" data-done-today rows="2" maxlength="5000" required placeholder="What did you complete on this project today?">${escapeHtml(doneToday)}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Blockers or issues</label>
                    <textarea class="form-control form-control-sm" data-blockers rows="2" maxlength="5000" placeholder="Any blockers for this project...">${escapeHtml(blockers)}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Plan for tomorrow</label>
                    <textarea class="form-control form-control-sm" data-plan-tomorrow rows="2" maxlength="5000" placeholder="What you plan to work on next for this project...">${escapeHtml(planTomorrow)}</textarea>
                </div>
            </div>
        `;
    };

    const createRow = (entry = null, { canRemove = true, readOnly = false } = {}) => {
        rowCounter += 1;
        const rowId = rowCounter;

        return `
            <div class="timesheet-entry-card border rounded mb-3" data-row-id="${rowId}">
                <div class="timesheet-entry-card-header row g-3 align-items-end p-3 pb-2 mb-0">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Project</label>
                        <select class="form-select" data-project-select required>
                            ${renderProjectOptions(rowId, entry?.project_id || '', entry?.project || null)}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Start time</label>
                        <input type="time" class="form-control" data-start-time value="${entry?.start_time || ''}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">End time</label>
                        <input type="time" class="form-control" data-end-time value="${entry?.end_time || ''}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Hours</label>
                        <div class="form-control-plaintext fw-semibold py-2" data-hours-label>${formatHoursLabel(entry?.hours ?? calculateHours(entry?.start_time, entry?.end_time))}</div>
                    </div>
                    <div class="col-md-2 text-end">
                        ${canRemove ? '<button type="button" class="btn btn-sm btn-outline-danger mt-4" data-remove-row aria-label="Remove project">Remove</button>' : ''}
                    </div>
                </div>
                <div class="timesheet-entry-card-body border-top px-3 pb-3 pt-2">
                    ${renderEntryReportFields(entry, readOnly)}
                </div>
            </div>
        `;
    };

    const removeRowGroup = (rowId) => {
        entriesBody.querySelector(`[data-row-id="${rowId}"]`)?.remove();
    };

    const syncRemoveButtons = () => {
        const rows = [...entriesBody.querySelectorAll('[data-row-id]')];

        rows.forEach((row, index) => {
            const removeButton = row.querySelector('[data-remove-row]');
            const shouldShowRemove = rows.length > 1 && index > 0;

            if (shouldShowRemove && !removeButton) {
                const actionsCol = row.querySelector('.col-md-2.text-end');
                if (actionsCol) {
                    actionsCol.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger mt-4" data-remove-row aria-label="Remove project">Remove</button>';
                }
            } else if (!shouldShowRemove && removeButton) {
                removeButton.remove();
            } else if (index === 0 && removeButton) {
                removeButton.remove();
            }
        });
    };

    const ensureInitialRow = () => {
        if (isReadOnlyView() || entriesBody.querySelector('[data-row-id]')) {
            return;
        }

        entriesBody.querySelector('[data-placeholder-row]')?.remove();
        entriesBody.insertAdjacentHTML('beforeend', createRow(null, { canRemove: false }));
        syncRemoveButtons();
    };

    const renderRows = (entries = []) => {
        const readOnly = isReadOnlyView();
        const filteredEntries = filterEntriesByProject(entries);

        syncProjectFilterOptions(collectProjectsFromEntries(entries));

        if (!entries.length) {
            entriesBody.innerHTML = readOnly
                ? `<div class="text-muted py-4 text-center border rounded" data-placeholder-row="1">No report submitted for ${escapeHtml(formatDisplayDate(selectedWorkDate()))}.</div>`
                : '';
            ensureInitialRow();
            applyViewMode();
            return;
        }

        if (!filteredEntries.length) {
            entriesBody.innerHTML = `<div class="text-muted py-4 text-center border rounded" data-placeholder-row="1">No entries match the selected project filter.</div>`;
            applyViewMode();
            return;
        }

        if (readOnly) {
            entriesBody.innerHTML = filteredEntries
                .map((entry) => renderReadOnlyEntryCard(entry))
                .join('');
            applyViewMode();
            return;
        }

        entriesBody.innerHTML = filteredEntries.map((entry, index) => createRow({
            project_id: entry.project_id,
            project: entry.project,
            start_time: entry.start_time,
            end_time: entry.end_time,
            hours: entry.hours,
            done_today: entry.done_today,
            blockers: entry.blockers,
            plan_tomorrow: entry.plan_tomorrow,
        }, { canRemove: index > 0 && !readOnly, readOnly })).join('');

        syncRemoveButtons();
        applyViewMode();
    };

    const collectRows = () => [...entriesBody.querySelectorAll('[data-row-id]')].map((row) => ({
        project_id: row.querySelector('[data-project-select]')?.value,
        start_time: row.querySelector('[data-start-time]')?.value,
        end_time: row.querySelector('[data-end-time]')?.value,
        done_today: row.querySelector('[data-done-today]')?.value?.trim() || '',
        blockers: row.querySelector('[data-blockers]')?.value?.trim() || null,
        plan_tomorrow: row.querySelector('[data-plan-tomorrow]')?.value?.trim() || null,
    }));

    const renderReply = (reply, projectId, canReply) => `
        <div class="border-start border-2 ps-3 ms-2 mt-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>${escapeHtml(reply.author_name || 'User')}</strong>
                    ${reply.author_role_label ? `<span class="badge text-bg-light border ms-1">${escapeHtml(reply.author_role_label)}</span>` : ''}
                </div>
                <span class="small text-muted">${renderDateTimeStackFromLabel(reply.created_at_label, { empty: '' })}</span>
            </div>
            <div class="mt-1">${escapeHtml(reply.body)}</div>
            ${canReply ? `<button type="button" class="btn btn-link btn-sm p-0 mt-1" data-reply-to="${reply.id}" data-reply-project="${projectId}">Reply</button>` : ''}
        </div>
    `;

    const renderCommentThread = (comment, projectId) => {
        const canReply = capabilities.can_reply;

        return `
            <div class="border rounded p-3 mb-2 bg-light" data-comment-id="${comment.id}">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong>${escapeHtml(comment.author_name || 'User')}</strong>
                        ${comment.author_role_label ? `<span class="badge text-bg-light border ms-1">${escapeHtml(comment.author_role_label)}</span>` : ''}
                    </div>
                    <span class="small text-muted">${renderDateTimeStackFromLabel(comment.created_at_label, { empty: '' })}</span>
                </div>
                <div class="mt-2">${escapeHtml(comment.body)}</div>
                ${canReply ? `<button type="button" class="btn btn-link btn-sm p-0 mt-2" data-reply-to="${comment.id}" data-reply-project="${projectId}">Reply</button>` : ''}
                ${(comment.replies || []).map((reply) => renderReply(reply, projectId, canReply)).join('')}
            </div>
        `;
    };

    const renderProjectCommentForm = (projectId) => {
        const isReplying = pendingReply.projectId === projectId && pendingReply.parentId;
        const canStartComment = capabilities.can_comment && capabilities.is_viewing_team_member;
        const showForm = canStartComment || (capabilities.can_reply && isReplying);

        if (!showForm) {
            return '';
        }

        const label = isReplying
            ? 'Write a reply'
            : 'Add a comment on this project submission';

        return `
            <div class="mt-3" data-project-comment-form="${projectId}">
                <label class="form-label small mb-1">${label}</label>
                <textarea class="form-control form-control-sm mb-2" rows="2" maxlength="5000" data-project-comment-input="${projectId}" placeholder="Write your comment...">${isReplying ? '' : ''}</textarea>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-post-project-comment="${projectId}">Post comment</button>
                    ${isReplying ? `<button type="button" class="btn btn-link btn-sm" data-cancel-project-reply="${projectId}">Cancel reply</button>` : ''}
                </div>
            </div>
        `;
    };

    const renderEntryReportSummary = (entry) => `
        <div class="timesheet-entry-report-summary small border-top pt-2 mt-2">
            <div class="mb-2">
                <span class="text-muted">Completed:</span>
                <div class="mt-1">${entry.done_today ? escapeHtml(entry.done_today) : '<span class="text-muted">—</span>'}</div>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <span class="text-muted">Blockers:</span>
                    <div class="mt-1">${entry.blockers ? escapeHtml(entry.blockers) : '<span class="text-muted">—</span>'}</div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted">Plan for tomorrow:</span>
                    <div class="mt-1">${entry.plan_tomorrow ? escapeHtml(entry.plan_tomorrow) : '<span class="text-muted">—</span>'}</div>
                </div>
            </div>
        </div>
    `;

    const renderProjectDiscussionPanel = (entry) => {
        const projectId = Number(entry.project_id);
        const projectName = entry.project?.name || 'Project';
        const threads = commentsByProject[String(projectId)] || [];
        const hasThreads = threads.length > 0;
        const isManagerView = capabilities.is_viewing_team_member;

        if (!isManagerView && !hasThreads) {
            return '';
        }

        return `
            <div class="border rounded p-3 mb-3" data-project-discussion="${projectId}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${escapeHtml(projectName)}</div>
                        <div class="small text-muted">${escapeHtml(entry.start_time)} – ${escapeHtml(entry.end_time)} · ${escapeHtml(entry.hours_label || formatHoursLabel(entry.hours))}</div>
                        ${renderEntryReportSummary(entry)}
                    </div>
                </div>
                <div data-project-comment-threads="${projectId}">
                    ${hasThreads
                        ? threads.map((comment) => renderCommentThread(comment, projectId)).join('')
                        : '<div class="small text-muted">No comments on this project yet.</div>'}
                </div>
                ${renderProjectCommentForm(projectId)}
            </div>
        `;
    };

    const renderProjectDiscussions = () => {
        if (!projectDiscussionsWrap || !projectDiscussionsList) {
            return;
        }

        if (capabilities.is_viewing_team_member) {
            projectDiscussionsWrap.classList.add('d-none');
            projectDiscussionsList.innerHTML = '';
            return;
        }

        if (!loadedEntries.length) {
            projectDiscussionsWrap.classList.add('d-none');
            projectDiscussionsList.innerHTML = '';
            return;
        }

        const panels = loadedEntries
            .map((entry) => renderProjectDiscussionPanel(entry))
            .filter(Boolean);

        if (!panels.length) {
            projectDiscussionsWrap.classList.add('d-none');
            projectDiscussionsList.innerHTML = '';
            return;
        }

        projectDiscussionsWrap.classList.remove('d-none');
        projectDiscussionsList.innerHTML = panels.join('');
    };

    const clearPendingReply = () => {
        pendingReply = { projectId: null, parentId: null };
    };

    const loadComments = async () => {
        if (!selectedEmployeeId || !projectDiscussionsList) {
            commentsByProject = {};
            renderProjectDiscussions();
            if (isReadOnlyView()) {
                renderRows(loadedEntries);
            }
            return;
        }

        if (!loadedEntries.length) {
            commentsByProject = {};
            renderProjectDiscussions();
            if (isReadOnlyView()) {
                renderRows(loadedEntries);
            }
            return;
        }

        try {
            const response = await api.get('/timesheets/comments', { params: requestParams() });
            const payload = response.data?.data || {};

            commentsByProject = payload.by_project || {};

            if (payload.capabilities) {
                capabilities = { ...capabilities, ...payload.capabilities };
            }

            renderProjectDiscussions();

            if (isReadOnlyView()) {
                renderRows(loadedEntries);
            }
        } catch (error) {
            if (capabilities.is_viewing_team_member) {
                showAlert(getErrorMessage(error), 'danger');
            } else {
                projectDiscussionsList.innerHTML = `<div class="text-danger py-2">${escapeHtml(getErrorMessage(error))}</div>`;
                projectDiscussionsWrap?.classList.remove('d-none');
            }
        }
    };

    const postProjectComment = async (projectId) => {
        const input = document.querySelector(`[data-project-comment-input="${projectId}"]`);
        const body = input?.value?.trim();

        if (!body || !selectedEmployeeId) {
            return;
        }

        const payload = {
            employee_id: selectedEmployeeId,
            work_date: selectedWorkDate(),
            project_id: projectId,
            body,
        };

        if (pendingReply.projectId === projectId && pendingReply.parentId) {
            payload.parent_id = pendingReply.parentId;
            delete payload.project_id;
        }

        try {
            const response = await api.post('/timesheets/comments', payload);
            showAlert(response.data?.message || 'Comment posted.');
            clearPendingReply();
            await loadComments();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const renderEmployeeOption = (employee) => {
        const label = employee.employee_code
            ? `${employee.full_name} (${employee.employee_code})`
            : employee.full_name;

        return `<option value="${employee.id}">${escapeHtml(label)}</option>`;
    };

    const loadTeamEmployees = async () => {
        if (!pageConfig.canReviewTeam || !teamEmployeeSelect) {
            return;
        }

        try {
            const response = await api.get('/timesheets/team-employees');
            const payload = response.data?.data || {};
            const employees = payload.employees || [];
            const groups = payload.groups || [];
            const employeeById = new Map(employees.map((employee) => [Number(employee.id), employee]));
            const ownId = pageConfig.ownEmployeeId ? String(pageConfig.ownEmployeeId) : null;
            const options = [];

            if (pageConfig.canSubmit && ownId) {
                options.push(`<option value="${ownId}" selected>My timesheet</option>`);
            }

            if (groups.length) {
                groups.forEach((group) => {
                    const groupOptions = (group.employee_ids || [])
                        .map((id) => employeeById.get(Number(id)))
                        .filter(Boolean)
                        .filter((employee) => !(ownId && String(employee.id) === ownId))
                        .map((employee) => renderEmployeeOption(employee))
                        .join('');

                    if (groupOptions) {
                        options.push(`<optgroup label="${escapeHtml(group.label)}">${groupOptions}</optgroup>`);
                    }
                });
            } else {
                employees.forEach((employee) => {
                    if (ownId && String(employee.id) === ownId) {
                        return;
                    }

                    options.push(renderEmployeeOption(employee));
                });
            }

            if (!pageConfig.canSubmit && employees.length && !selectedEmployeeId) {
                selectedEmployeeId = Number(employees[0].id);
            }

            teamEmployeeSelect.innerHTML = options.join('');

            if (selectedEmployeeId) {
                teamEmployeeSelect.value = String(selectedEmployeeId);
            }
        } catch (error) {
            showAlert(getErrorMessage(error, 'Unable to load team employees.'), 'danger');
        }
    };

    const loadProjects = async () => {
        if (!pageConfig.canSubmit || isReadOnlyView()) {
            noProjectsNotice?.classList.add('d-none');
            if (addRowBtn) {
                addRowBtn.disabled = false;
            }
            return;
        }

        try {
            const response = await api.get('/timesheets/project-options');
            projectOptions = response.data?.data?.projects || [];
            const hasAssignedProjects = projectOptions.some((project) => project.name !== 'Other');
            noProjectsNotice?.classList.toggle('d-none', hasAssignedProjects);
            if (addRowBtn) {
                addRowBtn.disabled = false;
            }
            submitBtn.disabled = isSubmitting || projectOptions.length === 0;
            if (!isSubmitting) {
                updateSubmitButtonLabel();
            }
            refreshProjectSelects();
        } catch (error) {
            if (addRowBtn) {
                addRowBtn.disabled = false;
            }
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadDayEntries = async () => {
        hideFormAlert();

        if (!selectedEmployeeId) {
            renderRows([]);
            loadedEntries = [];
            updateDaySummary(null);
            renderProjectDiscussions();
            return;
        }

        try {
            const response = await api.get('/timesheets', { params: requestParams() });
            const payload = response.data?.data || {};
            const entries = payload.entries || [];
            loadedEntries = entries;

            if (payload.capabilities) {
                capabilities = { ...capabilities, ...payload.capabilities };
            }

            await loadProjects();
            renderRows(entries);
            updateDaySummary(payload.summary);
            applyViewMode();
            await loadComments();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const renderPeriodDayCard = (day) => {
        const filteredEntries = filterEntriesByProject(day.entries || []);

        if (!filteredEntries.length) {
            return '';
        }

        return `
        <div class="border rounded p-3 mb-3 timesheet-period-day-card" data-day-project-ids="${filteredEntries.map((entry) => entry.project_id).join(',')}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none" data-load-date="${escapeHtml(day.work_date)}">
                    ${escapeHtml(formatDisplayDate(day.work_date))}
                </button>
                <span class="badge text-bg-light border">${formatHoursLabel(filteredEntries.reduce((total, entry) => total + Number(entry.hours || 0), 0))} total · ${filteredEntries.length} project${filteredEntries.length === 1 ? '' : 's'}</span>
            </div>
            <div class="timesheet-entries-list">
                ${filteredEntries.map((entry) => `
                    <div class="timesheet-entry-card border rounded mb-2" data-entry-project-id="${entry.project_id}">
                        <div class="p-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="small text-muted mb-1">Project</div>
                                    <div class="fw-semibold">${escapeHtml(entry.project?.name || 'Project')}</div>
                                </div>
                                <div class="text-md-end">
                                    <div class="small text-muted mb-1">Time</div>
                                    <div>${escapeHtml(entry.start_time)} – ${escapeHtml(entry.end_time)}</div>
                                    <div class="fw-semibold mt-1">${escapeHtml(entry.hours_label || formatHoursLabel(entry.hours))}</div>
                                </div>
                            </div>
                            ${renderEntryReportSummary(entry)}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    };

    const renderPeriodReports = () => {
        syncProjectFilterOptions(collectProjectsFromDays(loadedPeriodDays));

        const visibleDays = loadedPeriodDays
            .map((day) => ({
                ...day,
                entries: filterEntriesByProject(day.entries || []),
            }))
            .filter((day) => day.entries.length);

        if (!visibleDays.length) {
            periodReportsContainer.innerHTML = selectedProjectFilter
                ? '<div class="text-muted py-3">No entries match the selected project filter.</div>'
                : '<div class="text-muted py-3">No timesheet submissions in this period.</div>';
            periodReportsSummary.textContent = '0 days · 0h';
            return;
        }

        const totalHours = visibleDays.reduce(
            (total, day) => total + day.entries.reduce((dayTotal, entry) => dayTotal + Number(entry.hours || 0), 0),
            0,
        );

        periodReportsSummary.textContent = `${visibleDays.length} day${visibleDays.length === 1 ? '' : 's'} · ${formatHoursLabel(totalHours)} total`;
        periodReportsContainer.innerHTML = visibleDays.map((day) => renderPeriodDayCard(day)).join('');
    };

    const loadPeriodReports = async () => {
        if (isSingleDayView()) {
            return;
        }

        if (!selectedEmployeeId) {
            periodReportsContainer.innerHTML = '<div class="text-muted py-3">Select a team member to view reports.</div>';
            periodReportsSummary.textContent = '';
            return;
        }

        periodReportsContainer.innerHTML = '<div class="text-muted py-3">Loading reports...</div>';

        try {
            const response = await api.get('/timesheets/range', { params: rangeParams() });
            const payload = response.data?.data || {};
            const days = payload.days || [];
            const summary = payload.summary || {};

            if (payload.capabilities) {
                capabilities = { ...capabilities, ...payload.capabilities };
            }

            loadedPeriodDays = days;

            if (!days.length) {
                loadedPeriodDays = [];
                syncProjectFilterOptions([]);
                periodReportsContainer.innerHTML = '<div class="text-muted py-3">No timesheet submissions in this period.</div>';
                periodReportsSummary.textContent = '0 days · 0h';
                return;
            }

            renderPeriodReports();
        } catch (error) {
            periodReportsContainer.innerHTML = `<div class="text-danger py-3">${escapeHtml(getErrorMessage(error))}</div>`;
            periodReportsSummary.textContent = '';
        }
    };

    const reloadAll = async () => {
        updateViewLayout();

        if (isSingleDayView()) {
            await loadDayEntries();
            return;
        }

        await loadPeriodReports();
    };

    addRowBtn?.addEventListener('click', () => {
        if (isReadOnlyView()) {
            return;
        }

        entriesBody.querySelector('[data-placeholder-row]')?.remove();
        entriesBody.insertAdjacentHTML('beforeend', createRow(null, { canRemove: true }));
        refreshProjectSelects();
        syncRemoveButtons();
    });

    const refreshProjectDiscussionUi = () => {
        if (capabilities.is_viewing_team_member) {
            renderRows(loadedEntries);
            return;
        }

        renderProjectDiscussions();
    };

    const handleProjectDiscussionClick = (event) => {
        const replyButton = event.target.closest('[data-reply-to]');
        const postButton = event.target.closest('[data-post-project-comment]');
        const cancelButton = event.target.closest('[data-cancel-project-reply]');

        if (replyButton) {
            pendingReply = {
                projectId: Number(replyButton.dataset.replyProject),
                parentId: Number(replyButton.dataset.replyTo),
            };
            refreshProjectDiscussionUi();
            document.querySelector(`[data-project-comment-input="${pendingReply.projectId}"]`)?.focus();
            return;
        }

        if (cancelButton) {
            clearPendingReply();
            refreshProjectDiscussionUi();
            return;
        }

        if (postButton) {
            postProjectComment(Number(postButton.dataset.postProjectComment));
        }
    };

    entriesBody.addEventListener('input', (event) => {
        const row = event.target.closest('[data-row-id]');

        if (row && (event.target.matches('[data-start-time]') || event.target.matches('[data-end-time]'))) {
            syncRowHours(row);
        }
    });

    entriesBody.addEventListener('change', (event) => {
        if (event.target.matches('[data-project-select]')) {
            refreshProjectSelects();
        }
    });

    entriesBody.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-row]');

        if (removeButton) {
            const rowId = removeButton.closest('[data-row-id]')?.dataset.rowId;
            if (rowId) {
                removeRowGroup(rowId);
            }

            if (!entriesBody.querySelector('[data-row-id]')) {
                ensureInitialRow();
            } else {
                syncRemoveButtons();
                refreshProjectSelects();
            }
            return;
        }

        handleProjectDiscussionClick(event);
    });

    periodReportsContainer?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-load-date]');

        if (!button) {
            return;
        }

        const date = button.dataset.loadDate;
        currentRange = resolveClientDateRange('custom', date, date);
        customRangePending = false;
        syncRangeControls();
        await reloadAll();
        dailyReportCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    rangePresetEl?.addEventListener('change', async () => {
        if (rangePresetEl.value === 'custom') {
            showCustomRangePicker();
            return;
        }

        await applyCurrentRangeSelection();
    });

    applyRangeBtn?.addEventListener('click', async () => {
        await applyCurrentRangeSelection();
    });

    fromDateEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyCurrentRangeSelection();
        }
    });

    toDateEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyCurrentRangeSelection();
        }
    });

    teamEmployeeSelect?.addEventListener('change', async () => {
        selectedEmployeeId = teamEmployeeSelect.value ? Number(teamEmployeeSelect.value) : null;
        selectedProjectFilter = '';

        if (projectFilterEl) {
            projectFilterEl.value = '';
        }

        clearPendingReply();
        await reloadAll();
    });

    projectDiscussionsList?.addEventListener('click', (event) => {
        handleProjectDiscussionClick(event);
    });

    projectFilterEl?.addEventListener('change', () => {
        selectedProjectFilter = projectFilterEl.value;

        if (isSingleDayView()) {
            renderRows(loadedEntries);
            return;
        }

        renderPeriodReports();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideFormAlert();

        if (isReadOnlyView()) {
            return;
        }

        if (isFutureDate()) {
            showFormAlert('You cannot submit a report for a future date.');
            return;
        }

        const entries = collectRows().filter((entry) => entry.project_id && entry.start_time && entry.end_time);

        const missingDone = entries.find((entry) => !entry.done_today);

        if (missingDone) {
            showFormAlert('Please describe what you completed for each project.');
            const row = [...entriesBody.querySelectorAll('[data-row-id]')].find((element) => {
                const doneField = element.querySelector('[data-done-today]');
                return doneField && !doneField.value?.trim();
            });
            row?.querySelector('[data-done-today]')?.focus();
            return;
        }

        if (!entries.length) {
            showFormAlert('Add at least one complete project row before submitting.');
            return;
        }

        setSubmittingState(true);

        try {
            const response = await api.post('/timesheets', {
                work_date: selectedWorkDate(),
                entries,
            });

            const payload = response.data?.data || {};
            loadedEntries = payload.entries || [];
            renderRows(payload.entries || []);
            updateDaySummary(payload.summary);
            showAlert(response.data?.message || 'Timesheet submitted successfully.');
            await loadComments();
            await reloadAll();
        } catch (error) {
            showFormAlert(getErrorMessage(error));
        } finally {
            setSubmittingState(false);
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    const presetEmployeeId = urlParams.get('employee_id');
    const presetWorkDate = urlParams.get('work_date');
    const presetRange = urlParams.get('range');

    if (presetWorkDate) {
        currentRange = resolveClientDateRange('custom', presetWorkDate, presetWorkDate);
    } else if (presetRange) {
        currentRange = resolveClientDateRange(presetRange);
    }

    syncRangeControls();

    try {
        await loadTeamEmployees();

        if (presetEmployeeId && teamEmployeeSelect) {
            selectedEmployeeId = Number(presetEmployeeId);
            teamEmployeeSelect.value = presetEmployeeId;
        }

        if (!selectedEmployeeId && pageConfig.ownEmployeeId) {
            selectedEmployeeId = Number(pageConfig.ownEmployeeId);
        }

        await loadProjects();
        await reloadAll();
        ensureInitialRow();
        applyViewMode();
    } catch (error) {
        showAlert(getErrorMessage(error, 'Unable to load timesheets. Please refresh the page.'), 'danger');
    }
});
