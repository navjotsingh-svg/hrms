import api, { getErrorMessage } from './api';

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
    const workDateInput = document.getElementById('workDate');
    const daySummary = document.getElementById('daySummary');
    const entriesBody = document.getElementById('timesheetEntriesBody');
    const addRowBtn = document.getElementById('addTimesheetRowBtn');
    const submitBtn = document.getElementById('submitTimesheetBtn');
    const formActions = document.getElementById('timesheetFormActions');
    const noProjectsNotice = document.getElementById('noProjectsNotice');
    const readOnlyNotice = document.getElementById('readOnlyNotice');
    const recentContainer = document.getElementById('recentTimesheetsContainer');
    const teamEmployeeSelect = document.getElementById('teamEmployeeSelect');
    const dailyReportTitle = document.getElementById('dailyReportTitle');
    const projectDiscussionsWrap = document.getElementById('timesheetProjectDiscussions');
    const projectDiscussionsList = document.getElementById('timesheetProjectDiscussionsList');

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

    if (!form || !entriesBody || !workDateInput) {
        return;
    }

    workDateInput.max = formatToday();
    workDateInput.value = formatToday();

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
        const params = { work_date: workDateInput.value };

        if (selectedEmployeeId) {
            params.employee_id = selectedEmployeeId;
        }

        return params;
    };

    const isPastDate = () => Boolean(workDateInput.value) && workDateInput.value < formatToday();

    const isToday = () => workDateInput.value === formatToday();

    const isReadOnlyView = () => {
        if (capabilities.is_viewing_team_member) {
            return true;
        }

        if (!capabilities.can_submit) {
            return true;
        }

        return isPastDate();
    };

    const applyViewMode = () => {
        const readOnly = isReadOnlyView();

        if (readOnlyNotice) {
            const showNotice = readOnly && (capabilities.is_viewing_team_member || isPastDate());

            readOnlyNotice.classList.toggle('d-none', !showNotice);

            if (capabilities.is_viewing_team_member) {
                readOnlyNotice.textContent = 'You are viewing a team member\'s report. Add feedback on each project submission below.';
            } else if (isPastDate()) {
                readOnlyNotice.textContent = 'Past dates are view-only. Select today\'s date to submit or update your day report.';
            }
        }

        formActions?.classList.toggle('d-none', readOnly);
        addRowBtn?.classList.toggle('d-none', readOnly);
        submitBtn?.classList.toggle('d-none', readOnly);

        entriesBody.querySelectorAll('input, select, button[data-remove-row]').forEach((element) => {
            if (element.matches('button[data-remove-row]')) {
                element.classList.toggle('d-none', readOnly);
            } else {
                element.disabled = readOnly;
            }
        });

        if (dailyReportTitle) {
            if (capabilities.is_viewing_team_member) {
                dailyReportTitle.textContent = 'Team Daily Report';
            } else if (isPastDate()) {
                dailyReportTitle.textContent = 'Past Daily Report';
            } else {
                dailyReportTitle.textContent = 'Daily Report';
            }
        }
    };

    const updateDaySummary = (summary) => {
        if (!summary || !summary.entry_count) {
            daySummary.innerHTML = '<span class="text-muted">No report submitted for this date yet.</span>';
            return;
        }

        daySummary.innerHTML = `
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

    const renderProjectOptions = (currentRowId, selectedProjectId = '') => {
        const options = availableProjectsForRow(currentRowId, selectedProjectId);

        return `
            <option value="">Select project</option>
            ${options.map((project) => `
                <option value="${project.id}" ${String(project.id) === String(selectedProjectId) ? 'selected' : ''}>
                    ${escapeHtml(project.name)}
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

    const createRow = (entry = null) => {
        rowCounter += 1;
        const rowId = rowCounter;

        const safeNotes = entry?.notes
            ? String(entry.notes).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
            : '';

        return `
            <tr data-row-id="${rowId}">
                <td>
                    <select class="form-select" data-project-select required>
                        ${renderProjectOptions(rowId, entry?.project_id || '')}
                    </select>
                </td>
                <td>
                    <input type="time" class="form-control" data-start-time value="${entry?.start_time || ''}" required>
                </td>
                <td>
                    <input type="time" class="form-control" data-end-time value="${entry?.end_time || ''}" required>
                </td>
                <td>
                    <span class="fw-semibold" data-hours-label>${formatHoursLabel(entry?.hours ?? calculateHours(entry?.start_time, entry?.end_time))}</span>
                </td>
                <td>
                    <input type="text" class="form-control" data-notes maxlength="2000" value="${safeNotes}">
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="Remove row">&times;</button>
                </td>
            </tr>
        `;
    };

    const renderRows = (entries = []) => {
        if (!entries.length) {
            entriesBody.innerHTML = `
                <tr data-placeholder-row="1">
                    <td colspan="6" class="text-muted py-4 text-center">${isReadOnlyView() ? 'No report submitted for this date.' : 'Add at least one project row to submit your day report.'}</td>
                </tr>
            `;
            applyViewMode();
            return;
        }

        entriesBody.innerHTML = entries.map((entry) => createRow({
            project_id: entry.project_id,
            start_time: entry.start_time,
            end_time: entry.end_time,
            hours: entry.hours,
            notes: entry.notes,
        })).join('');

        applyViewMode();
    };

    const collectRows = () => [...entriesBody.querySelectorAll('[data-row-id]')].map((row) => ({
        project_id: row.querySelector('[data-project-select]')?.value,
        start_time: row.querySelector('[data-start-time]')?.value,
        end_time: row.querySelector('[data-end-time]')?.value,
        notes: row.querySelector('[data-notes]')?.value?.trim() || null,
    }));

    const renderReply = (reply, projectId, canReply) => `
        <div class="border-start border-2 ps-3 ms-2 mt-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>${escapeHtml(reply.author_name || 'User')}</strong>
                    ${reply.author_role_label ? `<span class="badge text-bg-light border ms-1">${escapeHtml(reply.author_role_label)}</span>` : ''}
                </div>
                <span class="small text-muted">${escapeHtml(reply.created_at_label || '')}</span>
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
                    <span class="small text-muted">${escapeHtml(comment.created_at_label || '')}</span>
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

    const renderProjectDiscussionPanel = (entry) => {
        const projectId = Number(entry.project_id);
        const projectName = entry.project?.name || 'Project';
        const threads = commentsByProject[String(projectId)] || [];
        const hasThreads = threads.length > 0;
        const canStartComment = capabilities.can_comment && capabilities.is_viewing_team_member;

        if (!canStartComment && !hasThreads) {
            return '';
        }

        return `
            <div class="border rounded p-3 mb-3" data-project-discussion="${projectId}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-semibold">${escapeHtml(projectName)}</div>
                        <div class="small text-muted">${escapeHtml(entry.start_time)} – ${escapeHtml(entry.end_time)} · ${escapeHtml(entry.hours_label || formatHoursLabel(entry.hours))}</div>
                        ${entry.notes ? `<div class="small mt-1">Notes: ${escapeHtml(entry.notes)}</div>` : ''}
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
        projectDiscussionsList.innerHTML = panels.length
            ? panels.join('')
            : '<div class="text-muted small">Submit project entries to enable manager feedback.</div>';
    };

    const clearPendingReply = () => {
        pendingReply = { projectId: null, parentId: null };
    };

    const loadComments = async () => {
        if (!selectedEmployeeId || !projectDiscussionsList) {
            return;
        }

        if (!loadedEntries.length) {
            commentsByProject = {};
            renderProjectDiscussions();
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
        } catch (error) {
            projectDiscussionsList.innerHTML = `<div class="text-danger py-2">${escapeHtml(getErrorMessage(error))}</div>`;
            projectDiscussionsWrap?.classList.remove('d-none');
        }
    };

    const postProjectComment = async (projectId) => {
        const input = projectDiscussionsList?.querySelector(`[data-project-comment-input="${projectId}"]`);
        const body = input?.value?.trim();

        if (!body || !selectedEmployeeId) {
            return;
        }

        const payload = {
            employee_id: selectedEmployeeId,
            work_date: workDateInput.value,
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
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadProjects = async () => {
        if (!pageConfig.canSubmit || isReadOnlyView()) {
            noProjectsNotice?.classList.add('d-none');
            addRowBtn.disabled = true;
            submitBtn.disabled = true;
            return;
        }

        try {
            const response = await api.get('/timesheets/project-options');
            projectOptions = response.data?.data?.projects || [];
            noProjectsNotice?.classList.toggle('d-none', projectOptions.length > 0);
            addRowBtn.disabled = projectOptions.length === 0;
            submitBtn.disabled = projectOptions.length === 0;
        } catch (error) {
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

            renderRows(entries);
            updateDaySummary(payload.summary);
            applyViewMode();
            await loadProjects();
            await loadComments();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadRecent = async () => {
        if (!selectedEmployeeId) {
            recentContainer.innerHTML = '<div class="text-muted py-3">Select a team member to view recent submissions.</div>';
            return;
        }

        try {
            const response = await api.get('/timesheets/recent', {
                params: { limit: 20, employee_id: selectedEmployeeId },
            });
            const days = response.data?.data?.days || [];

            if (!days.length) {
                recentContainer.innerHTML = '<div class="text-muted py-3">No timesheet submissions yet.</div>';
                return;
            }

            recentContainer.innerHTML = days.map((day) => `
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none" data-load-date="${day.work_date}">
                            ${escapeHtml(day.work_date)}
                        </button>
                        <span class="badge text-bg-light border">${formatHoursLabel(day.total_hours)} total</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Time</th>
                                    <th>Hours</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${day.entries.map((entry) => `
                                    <tr>
                                        <td>${escapeHtml(entry.project?.name || 'Project')}</td>
                                        <td>${escapeHtml(entry.start_time)} – ${escapeHtml(entry.end_time)}</td>
                                        <td>${escapeHtml(entry.hours_label || formatHoursLabel(entry.hours))}</td>
                                        <td>${entry.notes ? escapeHtml(entry.notes) : '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            recentContainer.innerHTML = '<div class="text-danger py-3">Unable to load recent timesheets.</div>';
        }
    };

    const reloadAll = async () => {
        await loadDayEntries();
        await loadRecent();
    };

    addRowBtn?.addEventListener('click', () => {
        entriesBody.querySelector('[data-placeholder-row]')?.remove();
        entriesBody.insertAdjacentHTML('beforeend', createRow());
        refreshProjectSelects();
    });

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

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-row-id]')?.remove();

        if (!entriesBody.querySelector('[data-row-id]')) {
            renderRows([]);
        } else {
            refreshProjectSelects();
        }
    });

    recentContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-load-date]');

        if (!button) {
            return;
        }

        workDateInput.value = button.dataset.loadDate;
        loadDayEntries();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    workDateInput.addEventListener('change', () => {
        clearPendingReply();
        loadDayEntries();
    });

    teamEmployeeSelect?.addEventListener('change', async () => {
        selectedEmployeeId = teamEmployeeSelect.value ? Number(teamEmployeeSelect.value) : null;
        clearPendingReply();
        await reloadAll();
    });

    projectDiscussionsList?.addEventListener('click', (event) => {
        const replyButton = event.target.closest('[data-reply-to]');
        const postButton = event.target.closest('[data-post-project-comment]');
        const cancelButton = event.target.closest('[data-cancel-project-reply]');

        if (replyButton) {
            pendingReply = {
                projectId: Number(replyButton.dataset.replyProject),
                parentId: Number(replyButton.dataset.replyTo),
            };
            renderProjectDiscussions();
            projectDiscussionsList.querySelector(`[data-project-comment-input="${pendingReply.projectId}"]`)?.focus();
            return;
        }

        if (cancelButton) {
            clearPendingReply();
            renderProjectDiscussions();
            return;
        }

        if (postButton) {
            postProjectComment(Number(postButton.dataset.postProjectComment));
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideFormAlert();

        if (isReadOnlyView()) {
            return;
        }

        if (!isToday()) {
            showFormAlert('You can only submit a day report for today.');
            return;
        }

        const entries = collectRows().filter((entry) => entry.project_id && entry.start_time && entry.end_time);

        if (!entries.length) {
            showFormAlert('Add at least one complete project row before submitting.');
            return;
        }

        submitBtn.disabled = true;

        try {
            const response = await api.post('/timesheets', {
                work_date: workDateInput.value,
                entries,
            });

            const payload = response.data?.data || {};
            loadedEntries = payload.entries || [];
            renderRows(payload.entries || []);
            updateDaySummary(payload.summary);
            showAlert(response.data?.message || 'Timesheet submitted successfully.');
            await loadComments();
            await loadRecent();
        } catch (error) {
            showFormAlert(getErrorMessage(error));
        } finally {
            submitBtn.disabled = projectOptions.length === 0;
        }
    });

    await loadTeamEmployees();
    await loadProjects();
    await reloadAll();
});
